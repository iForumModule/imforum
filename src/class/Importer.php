<?php
/**
 * Copies the data of a running iForum installation into imforum
 *
 * The import only reads the bb_* tables of iForum and writes to the tables of
 * imforum, keeping every id, so old links keep working once they are
 * redirected. Nothing in the source changes. Without transactions (the
 * servers may run MyISAM) the way back is rollback(): it empties the tables
 * of imforum again. Every run starts by emptying them, so an interrupted
 * import is repeated from the start.
 *
 * check() reads and reports, run() writes, verify() compares source and
 * target afterwards, rollback() undoes a run.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_Importer {

	/** The text is copied as the database sees it */
	public const ENCODING_AS_IS = 'as_is';

	/** The bytes are UTF-8, whatever the columns of the old tables claim to be (old XOOPS sites often have that) */
	public const ENCODING_UTF8_BYTES = 'utf8_bytes';

	public const SOURCE_DIRNAME = 'iforum';

	/** Rows per INSERT in the steps that loop in PHP */
	private const BATCH = 500;

	/** Posts compared by checksum */
	private const SAMPLE = 200;

	/** Tables of imforum that the import fills, in the order they are emptied */
	private const TARGETS = ['category', 'forum', 'moderator', 'topic', 'post', 'attachment', 'report', 'suspension', 'readmark', 'digest', 'archive', 'permtemplate'];

	/** Notifications that exist in both modules: category, event, whether the item is a forum, a topic or none */
	private const NOTIFICATIONS = [
		['thread', 'new_post', 'topic'],
		['forum', 'new_thread', 'forum'],
		['forum', 'new_post', 'forum'],
		['global', 'new_thread', 'none'],
		['global', 'new_post', 'none'],
		['global', 'digest', 'none'],
	];

	/** Columns every source table must have, per table without the bb_ prefix */
	private const REQUIRED = [
		'categories' => ['cat_id', 'cat_title', 'cat_description', 'cat_order', 'cat_image', 'cat_url'],
		'forums' => ['forum_id', 'forum_name', 'forum_desc', 'parent_forum', 'forum_moderator', 'cat_id', 'forum_type', 'allow_html', 'allow_sig', 'allow_subject_prefix', 'hot_threshold', 'forum_order', 'attach_maxkb', 'attach_ext'],
		'topics' => ['topic_id', 'topic_title', 'topic_poster', 'topic_time', 'topic_views', 'topic_replies', 'topic_last_post_id', 'forum_id', 'topic_status', 'topic_subject', 'topic_sticky', 'topic_digest', 'digest_time', 'approved', 'poster_name'],
		'posts' => ['post_id', 'pid', 'topic_id', 'forum_id', 'post_time', 'uid', 'poster_name', 'poster_ip', 'subject', 'dohtml', 'dosmiley', 'doxcode', 'dobr', 'doimage', 'icon', 'attachsig', 'approved', 'post_karma', 'attachment', 'require_reply'],
		'posts_text' => ['post_id', 'post_text', 'post_edit'],
	];

	/** Tables iForum may not have; a missing one is skipped, one with other columns is an error */
	private const OPTIONAL = [
		'attachments' => ['attach_id', 'post_id', 'name_saved', 'name_disp', 'mimetype', 'online', 'attach_time', 'download'],
		'report' => ['report_id', 'post_id', 'reporter_uid', 'reporter_ip', 'report_time', 'report_text', 'report_result', 'report_memo'],
		'moderates' => ['mod_id', 'mod_start', 'mod_end', 'mod_desc', 'uid', 'ip', 'forum_id'],
		'reads_forum' => ['uid', 'read_time', 'read_item', 'post_id'],
		'reads_topic' => ['uid', 'read_time', 'read_item', 'post_id'],
		'digest' => ['digest_id', 'digest_time', 'digest_content'],
		'archive' => ['topic_id', 'post_id', 'post_text'],
	];

	/** Preferences that carry over under the same name */
	private const SAME_SETTINGS = ['topics_per_page', 'posts_per_page', 'quickreply_enabled', 'edit_timelimit', 'disclaimer'];

	private int $now;

	/** @var array<string, bool> */
	private array $present = [];

	public function __construct(private string $encoding = self::ENCODING_AS_IS, ?int $now = null, private string $sourceDirname = self::SOURCE_DIRNAME) {
		if (function_exists('icms_loadLanguageFile')) {
			icms_loadLanguageFile(IMFORUM_DIRNAME, 'admin');
		}
		$this->now = $now ?? time();
		if (!in_array($this->encoding, [self::ENCODING_AS_IS, self::ENCODING_UTF8_BYTES], true)) {
			$this->encoding = self::ENCODING_AS_IS;
		}
	}

	/**
	 * The dry run: reads the source and the target and writes nothing
	 *
	 * @return array{ok: bool, errors: list<string>, warnings: list<string>, rows: list<array{label: string, source: int|string, target: int|string, note: string}>, log: list<string>}
	 */
	public function check(): array {
		$report = self::newReport();
		$this->checkStructure($report);
		if (!$report['errors']) {
			$this->checkContent($report);
		}
		$report['ok'] = !$report['errors'];
		return $report;
	}

	/**
	 * Empty the target and copy everything
	 *
	 * Refuses to start when check() finds errors.
	 *
	 * @return array{ok: bool, errors: list<string>, warnings: list<string>, rows: list<array{label: string, source: int|string, target: int|string, note: string}>, log: list<string>}
	 */
	public function run(): array {
		$report = self::newReport();
		$this->checkStructure($report);
		if ($report['errors']) {
			return $report;
		}
		$oldMid = $this->moduleId($this->sourceDirname);
		$newMid = $this->moduleId(IMFORUM_DIRNAME);

		$this->clearTarget($newMid);
		$report['log'][] = _AM_IMFORUM_IMPORT_LOG_EMPTIED;

		$steps = [
			[_AM_IMFORUM_IMPORT_CATEGORIES, fn (): array => $this->copyCategories()],
			[_AM_IMFORUM_IMPORT_FORUMS, fn (): array => $this->copyForums()],
			[_AM_IMFORUM_IMPORT_MODERATORS, fn (): array => $this->copyModerators()],
			[_AM_IMFORUM_IMPORT_TOPICS, fn (): array => $this->copyTopics()],
			[_AM_IMFORUM_IMPORT_POSTS, fn (): array => $this->copyPosts()],
			[_AM_IMFORUM_IMPORT_EDITS, fn (): array => $this->copyEdits()],
			[_AM_IMFORUM_IMPORT_ATTACHMENTS, fn (): array => $this->copyAttachments()],
			[_AM_IMFORUM_IMPORT_REPORTS, fn (): array => $this->copyReports()],
			[_AM_IMFORUM_IMPORT_SUSPENSIONS, fn (): array => $this->copySuspensions()],
			[_AM_IMFORUM_IMPORT_READMARKS, fn (): array => $this->copyReadmarks()],
			[_AM_IMFORUM_IMPORT_DIGESTS, fn (): array => $this->copyDigests()],
			[_AM_IMFORUM_IMPORT_ARCHIVE, fn (): array => $this->copyArchive()],
			[_AM_IMFORUM_IMPORT_PERMISSIONS, fn (): array => $this->copyPermissions($oldMid, $newMid)],
			[_AM_IMFORUM_IMPORT_NOTIFICATIONS, fn (): array => $this->copyNotifications($oldMid, $newMid)],
			[_AM_IMFORUM_IMPORT_SETTINGS, fn (): array => $this->copySettings($oldMid, $newMid)],
			[_AM_IMFORUM_IMPORT_TEMPLATE, fn (): array => $this->copyTemplate()],
			[_AM_IMFORUM_IMPORT_FILES, fn (): array => $this->copyFiles()],
		];
		$current = '';
		try {
			foreach ($steps as [$label, $step]) {
				$current = $label;
				$result = $step();
				$report['rows'][] = ['label' => $label, 'source' => $result['source'], 'target' => $result['target'], 'note' => $result['note']];
				array_push($report['warnings'], ...$result['warnings']);
				$report['log'][] = sprintf(_AM_IMFORUM_IMPORT_LOG_STEP, $label, $result['target']);
			}
		} catch (RuntimeException $e) {
			$report['errors'][] = sprintf(_AM_IMFORUM_IMPORT_ERR_STEP, $current, $e->getMessage());
			return $report;
		}

		// the counters of the old data are not taken over: they are recounted from the copied rows
		$ok = icms_getModuleHandler('topic', IMFORUM_DIRNAME)->synchroniseAll()
			&& icms_getModuleHandler('forum', IMFORUM_DIRNAME)->synchroniseAll();
		$this->keepPendingReferences();
		if (!$ok) {
			$report['errors'][] = _AM_IMFORUM_RECOUNT_ERROR;
		}
		$report['log'][] = _AM_IMFORUM_IMPORT_LOG_RECOUNTED;
		$report['ok'] = !$report['errors'];
		return $report;
	}

	/**
	 * Compare source and target after a run
	 *
	 * @return array{ok: bool, errors: list<string>, warnings: list<string>, rows: list<array{label: string, source: int|string, target: int|string, note: string}>, log: list<string>}
	 */
	public function verify(): array {
		$report = self::newReport();
		$this->checkStructure($report);
		if ($report['errors']) {
			return $report;
		}
		$oldMid = $this->moduleId($this->sourceDirname);
		$newMid = $this->moduleId(IMFORUM_DIRNAME);
		$t = static fn (string $item): string => mod_imforum_Db::table($item);

		$compare = function (string $label, int $source, int $target, bool $atLeast = false, string $note = '') use (&$report): void {
			$same = $atLeast ? $target >= $source : $target === $source;
			$report['rows'][] = ['label' => $label, 'source' => $source, 'target' => $target, 'note' => $same ? $note : _AM_IMFORUM_IMPORT_DIFFERS];
			if (!$same) {
				$report['errors'][] = sprintf(_AM_IMFORUM_IMPORT_ERR_COUNT, $label, $source, $target);
			}
		};

		$count = static fn (string $sql, array $params = []): int => mod_imforum_Db::value($sql, $params);
		$compare(_AM_IMFORUM_IMPORT_CATEGORIES, $count('SELECT COUNT(*) FROM ' . $this->src('categories')), $count('SELECT COUNT(*) FROM ' . $t('category')));
		$compare(_AM_IMFORUM_IMPORT_FORUMS, $count('SELECT COUNT(*) FROM ' . $this->src('forums')), $count('SELECT COUNT(*) FROM ' . $t('forum')));
		$compare(_AM_IMFORUM_IMPORT_MODERATORS, count($this->moderatorPairs()), $count('SELECT COUNT(*) FROM ' . $t('moderator')));
		$compare(_AM_IMFORUM_IMPORT_TOPICS, $count('SELECT COUNT(*) FROM ' . $this->src('topics')), $count('SELECT COUNT(*) FROM ' . $t('topic')));
		$compare(_AM_IMFORUM_IMPORT_POSTS, $count('SELECT COUNT(*) FROM ' . $this->src('posts')), $count('SELECT COUNT(*) FROM ' . $t('post')));
		$compare(_AM_IMFORUM_IMPORT_ATTACHMENTS, $this->countAttachments(), $count('SELECT COUNT(*) FROM ' . $t('attachment')));
		if ($this->has('report')) {
			$compare(_AM_IMFORUM_IMPORT_REPORTS, $count('SELECT COUNT(*) FROM ' . $this->src('report')), $count('SELECT COUNT(*) FROM ' . $t('report')));
		}
		if ($this->has('moderates')) {
			$compare(_AM_IMFORUM_IMPORT_SUSPENSIONS, $count('SELECT COUNT(*) FROM ' . $this->src('moderates') . ' WHERE mod_end >= ?', [$this->now]), $count('SELECT COUNT(*) FROM ' . $t('suspension')), true, _AM_IMFORUM_IMPORT_NOTE_EXPIRED);
		}
		$compare(_AM_IMFORUM_IMPORT_READMARKS, $this->countReadmarks(), $count('SELECT COUNT(*) FROM ' . $t('readmark')), false, _AM_IMFORUM_IMPORT_NOTE_MERGED);
		if ($this->has('digest')) {
			$compare(_AM_IMFORUM_IMPORT_DIGESTS, $count('SELECT COUNT(*) FROM ' . $this->src('digest')), $count('SELECT COUNT(*) FROM ' . $t('digest')));
		}
		if ($this->has('archive')) {
			$compare(_AM_IMFORUM_IMPORT_ARCHIVE, $count('SELECT COUNT(*) FROM ' . $this->src('archive')), $count('SELECT COUNT(*) FROM ' . $t('archive')));
		}
		$compare(_AM_IMFORUM_IMPORT_PERMISSIONS, $this->countPermissions($oldMid), $count('SELECT COUNT(*) FROM ' . $this->core('group_permission') . ' WHERE gperm_modid = ? AND gperm_name IN (' . mod_imforum_Db::marks($this->permissionNames()) . ')', array_merge([$newMid], $this->permissionNames())));

		// posts per forum and per topic
		$perForum = $this->groupMismatches('forum_id', $this->src('posts'), $t('post'));
		$report['rows'][] = ['label' => _AM_IMFORUM_IMPORT_POSTS_PER_FORUM, 'source' => '', 'target' => '', 'note' => $perForum ? _AM_IMFORUM_IMPORT_DIFFERS : _AM_IMFORUM_IMPORT_SAME];
		if ($perForum) {
			$report['errors'][] = sprintf(_AM_IMFORUM_IMPORT_ERR_GROUPS, _AM_IMFORUM_IMPORT_POSTS_PER_FORUM, implode(', ', array_slice($perForum, 0, 10)));
		}
		$perTopic = $this->groupMismatches('topic_id', $this->src('posts'), $t('post'));
		$report['rows'][] = ['label' => _AM_IMFORUM_IMPORT_POSTS_PER_TOPIC, 'source' => '', 'target' => '', 'note' => $perTopic ? _AM_IMFORUM_IMPORT_DIFFERS : _AM_IMFORUM_IMPORT_SAME];
		if ($perTopic) {
			$report['errors'][] = sprintf(_AM_IMFORUM_IMPORT_ERR_GROUPS, _AM_IMFORUM_IMPORT_POSTS_PER_TOPIC, implode(', ', array_slice($perTopic, 0, 10)));
		}

		// the counters of the old data against the recounted ones: deviations are reported, not wrong
		$forumDeviations = $count('SELECT COUNT(*) FROM ' . $this->src('forums') . ' o JOIN ' . $t('forum') . ' n ON n.forum_id = o.forum_id'
			. ' WHERE o.forum_posts <> n.forum_posts OR o.forum_topics <> n.forum_topics');
		$topicDeviations = $count('SELECT COUNT(*) FROM ' . $this->src('topics') . ' o JOIN ' . $t('topic') . ' n ON n.topic_id = o.topic_id WHERE o.topic_replies <> n.topic_replies');
		$report['rows'][] = ['label' => _AM_IMFORUM_IMPORT_COUNTER_DEVIATIONS, 'source' => $forumDeviations . ' / ' . $topicDeviations, 'target' => '', 'note' => _AM_IMFORUM_IMPORT_NOTE_COUNTERS];
		if ($forumDeviations || $topicDeviations) {
			$report['warnings'][] = sprintf(_AM_IMFORUM_IMPORT_WARN_COUNTERS, $forumDeviations, $topicDeviations);
		}

		// a sample of posts must be the same text
		$ids = array_map('intval', array_column(mod_imforum_Db::rows('SELECT post_id FROM ' . $t('post') . ' ORDER BY RAND() LIMIT ' . self::SAMPLE), 0));
		$different = [];
		if ($ids) {
			$in = mod_imforum_Db::marks($ids);
			$source = [];
			foreach (mod_imforum_Db::rows('SELECT post_id, MD5(COALESCE(' . $this->text('post_text') . ", '')) FROM " . $this->src('posts_text') . " WHERE post_id IN ($in)", $ids) as $row) {
				$source[(int) $row[0]] = (string) $row[1];
			}
			foreach (mod_imforum_Db::rows('SELECT post_id, MD5(post_text) FROM ' . $t('post') . " WHERE post_id IN ($in)", $ids) as $row) {
				if (($source[(int) $row[0]] ?? md5('')) !== (string) $row[1]) {
					$different[] = (int) $row[0];
				}
			}
		}
		$report['rows'][] = ['label' => sprintf(_AM_IMFORUM_IMPORT_CHECKSUMS, count($ids)), 'source' => '', 'target' => '', 'note' => $different ? _AM_IMFORUM_IMPORT_DIFFERS : _AM_IMFORUM_IMPORT_SAME];
		if ($different) {
			$report['errors'][] = sprintf(_AM_IMFORUM_IMPORT_ERR_TEXT, implode(', ', array_slice($different, 0, 10)));
		}

		// every attachment needs its file
		$missing = $this->missingFiles(IMFORUM_UPLOAD_PATH, 'attachment');
		$report['rows'][] = ['label' => _AM_IMFORUM_IMPORT_FILES_PRESENT, 'source' => '', 'target' => '', 'note' => $missing ? sprintf(_AM_IMFORUM_IMPORT_NOTE_MISSING, count($missing)) : _AM_IMFORUM_IMPORT_SAME];
		if ($missing) {
			$report['warnings'][] = sprintf(_AM_IMFORUM_IMPORT_WARN_FILES, count($missing), implode(', ', array_slice($missing, 0, 10)));
		}

		$report['ok'] = !$report['errors'];
		return $report;
	}

	/**
	 * Undo a run: empty imforum's tables and remove what was copied next to them
	 *
	 * The tables of iForum are never touched.
	 *
	 * @return array{ok: bool, errors: list<string>, warnings: list<string>, rows: list<array{label: string, source: int|string, target: int|string, note: string}>, log: list<string>}
	 */
	public function rollback(): array {
		$report = self::newReport();
		$newMid = $this->moduleId(IMFORUM_DIRNAME);
		if ($newMid === 0) {
			$report['errors'][] = _AM_IMFORUM_IMPORT_ERR_NO_TARGET;
			return $report;
		}
		$removed = 0;
		foreach (mod_imforum_Db::rows('SELECT DISTINCT name_saved FROM ' . mod_imforum_Db::table('attachment')) as $row) {
			$name = (string) $row[0];
			$file = IMFORUM_UPLOAD_PATH . $name;
			if (self::isSafeName($name) && is_file($file) && @unlink($file)) {
				$removed++;
			}
		}
		$this->clearTarget($newMid);
		$report['rows'][] = ['label' => _AM_IMFORUM_IMPORT_FILES, 'source' => '', 'target' => $removed, 'note' => _AM_IMFORUM_IMPORT_NOTE_REMOVED];
		$report['log'][] = _AM_IMFORUM_IMPORT_LOG_EMPTIED;
		$report['ok'] = true;
		return $report;
	}

	/**
	 * Does the source module exist, are its tables complete and does the target accept the copy?
	 *
	 * @param array{ok: bool, errors: list<string>, warnings: list<string>, rows: list<array{label: string, source: int|string, target: int|string, note: string}>, log: list<string>} $report
	 */
	private function checkStructure(array &$report): void {
		$this->present = [];
		if ($this->moduleId($this->sourceDirname) === 0) {
			$report['errors'][] = sprintf(_AM_IMFORUM_IMPORT_ERR_NO_SOURCE, $this->sourceDirname);
		}
		if ($this->moduleId(IMFORUM_DIRNAME) === 0) {
			$report['errors'][] = _AM_IMFORUM_IMPORT_ERR_NO_TARGET;
		}
		foreach (self::REQUIRED as $table => $columns) {
			if (!$this->tableExists($this->src($table))) {
				$report['errors'][] = sprintf(_AM_IMFORUM_IMPORT_ERR_NO_TABLE, $this->src($table));
				continue;
			}
			$this->present[$table] = true;
			$this->checkColumns($report, $table, $columns);
		}
		foreach (self::OPTIONAL as $table => $columns) {
			if (!$this->tableExists($this->src($table))) {
				$report['warnings'][] = sprintf(_AM_IMFORUM_IMPORT_WARN_NO_TABLE, $this->src($table));
				continue;
			}
			$this->present[$table] = true;
			$this->checkColumns($report, $table, $columns);
		}
		foreach (self::TARGETS as $item) {
			$collation = (string) (mod_imforum_Db::row('SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [mod_imforum_Db::table($item)])[0] ?? '');
			if ($collation !== '' && !str_starts_with($collation, 'utf8mb4')) {
				$report['errors'][] = sprintf(_AM_IMFORUM_IMPORT_ERR_CHARSET, mod_imforum_Db::table($item), $collation);
			}
		}
	}

	/**
	 * @param array{ok: bool, errors: list<string>, warnings: list<string>, rows: list<array{label: string, source: int|string, target: int|string, note: string}>, log: list<string>} $report
	 * @param list<string> $columns
	 */
	private function checkColumns(array &$report, string $table, array $columns): void {
		$have = array_map('strval', array_column(mod_imforum_Db::rows('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$this->src($table)]), 0));
		$missing = array_diff($columns, $have);
		if ($missing) {
			$report['errors'][] = sprintf(_AM_IMFORUM_IMPORT_ERR_COLUMNS, $this->src($table), implode(', ', $missing));
		}
	}

	/**
	 * Row counts, leftovers in the source and files that are gone
	 *
	 * @param array{ok: bool, errors: list<string>, warnings: list<string>, rows: list<array{label: string, source: int|string, target: int|string, note: string}>, log: list<string>} $report
	 */
	private function checkContent(array &$report): void {
		$labels = [
			'categories' => _AM_IMFORUM_IMPORT_CATEGORIES, 'forums' => _AM_IMFORUM_IMPORT_FORUMS, 'topics' => _AM_IMFORUM_IMPORT_TOPICS,
			'posts' => _AM_IMFORUM_IMPORT_POSTS, 'attachments' => _AM_IMFORUM_IMPORT_ATTACHMENTS, 'report' => _AM_IMFORUM_IMPORT_REPORTS,
			'moderates' => _AM_IMFORUM_IMPORT_SUSPENSIONS, 'reads_forum' => _AM_IMFORUM_IMPORT_READMARKS . ' (forum)', 'reads_topic' => _AM_IMFORUM_IMPORT_READMARKS . ' (topic)',
			'digest' => _AM_IMFORUM_IMPORT_DIGESTS, 'archive' => _AM_IMFORUM_IMPORT_ARCHIVE,
		];
		foreach ($labels as $table => $label) {
			if ($this->has($table)) {
				$report['rows'][] = ['label' => $label, 'source' => mod_imforum_Db::value('SELECT COUNT(*) FROM ' . $this->src($table)), 'target' => '', 'note' => ''];
			}
		}

		$full = [];
		foreach (self::TARGETS as $item) {
			$rows = mod_imforum_Db::value('SELECT COUNT(*) FROM ' . mod_imforum_Db::table($item));
			if ($rows > 0) {
				$full[] = mod_imforum_Db::table($item) . ' (' . $rows . ')';
			}
		}
		if ($full) {
			$report['warnings'][] = sprintf(_AM_IMFORUM_IMPORT_WARN_EMPTIED, implode(', ', $full));
		}

		$orphans = [
			_AM_IMFORUM_IMPORT_ORPHAN_POSTS => 'SELECT COUNT(*) FROM ' . $this->src('posts') . ' p LEFT JOIN ' . $this->src('topics') . ' t ON t.topic_id = p.topic_id WHERE t.topic_id IS NULL',
			_AM_IMFORUM_IMPORT_ORPHAN_TOPICS => 'SELECT COUNT(*) FROM ' . $this->src('topics') . ' t LEFT JOIN ' . $this->src('forums') . ' f ON f.forum_id = t.forum_id WHERE f.forum_id IS NULL',
			_AM_IMFORUM_IMPORT_ORPHAN_FORUMS => 'SELECT COUNT(*) FROM ' . $this->src('forums') . ' f LEFT JOIN ' . $this->src('categories') . ' c ON c.cat_id = f.cat_id WHERE c.cat_id IS NULL',
			_AM_IMFORUM_IMPORT_NO_TEXT => 'SELECT COUNT(*) FROM ' . $this->src('posts') . ' p LEFT JOIN ' . $this->src('posts_text') . ' t ON t.post_id = p.post_id WHERE t.post_id IS NULL',
		];
		foreach ($orphans as $label => $sql) {
			$n = mod_imforum_Db::value($sql);
			if ($n > 0) {
				$report['warnings'][] = sprintf(_AM_IMFORUM_IMPORT_WARN_ORPHANS, $n, $label);
			}
		}

		// text that no longer fits a TEXT column once it is converted
		$long = mod_imforum_Db::value('SELECT COUNT(*) FROM ' . $this->src('posts_text') . ' WHERE LENGTH(' . $this->text('post_text') . ') > 65535');
		if ($long > 0) {
			$report['errors'][] = sprintf(_AM_IMFORUM_IMPORT_ERR_LONG, $long);
		}

		$charset = (string) (mod_imforum_Db::row('SELECT CHARACTER_SET_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$this->src('posts_text'), 'post_text'])[0] ?? '');
		$report['rows'][] = ['label' => _AM_IMFORUM_IMPORT_CHARSET, 'source' => $charset, 'target' => 'utf8mb4', 'note' => $this->encoding === self::ENCODING_UTF8_BYTES ? _AM_IMFORUM_IMPORT_NOTE_BYTES : ''];
		if ($this->encoding === self::ENCODING_AS_IS && $charset !== '' && !str_starts_with($charset, 'utf8')) {
			$suspect = mod_imforum_Db::value('SELECT COUNT(*) FROM ' . $this->src('posts_text') . ' WHERE LOCATE(UNHEX(?), CAST(post_text AS BINARY)) > 0', ['C3']);
			if ($suspect > 0) {
				$report['warnings'][] = sprintf(_AM_IMFORUM_IMPORT_WARN_ENCODING, $charset, $suspect);
			}
		}

		$missing = $this->missingSourceFiles();
		if ($missing) {
			$report['warnings'][] = sprintf(_AM_IMFORUM_IMPORT_WARN_FILES, count($missing), implode(', ', array_slice($missing, 0, 10)));
		}
		if (!is_file(XOOPS_CACHE_PATH . '/' . $this->sourceDirname . '_perm_template.php')) {
			$report['warnings'][] = _AM_IMFORUM_IMPORT_WARN_NO_TEMPLATE;
		}
	}

	/**
	 * @return array{ok: bool, errors: list<string>, warnings: list<string>, rows: list<array{label: string, source: int|string, target: int|string, note: string}>, log: list<string>}
	 */
	private static function newReport(): array {
		return ['ok' => false, 'errors' => [], 'warnings' => [], 'rows' => [], 'log' => []];
	}

	/**
	 * @param list<string> $warnings
	 * @return array{source: int, target: int, note: string, warnings: list<string>}
	 */
	private static function result(int $source, int $target, string $note = '', array $warnings = []): array {
		return ['source' => $source, 'target' => $target, 'note' => $note, 'warnings' => $warnings];
	}

	/**
	 * Run a statement that must work; a failure stops the import with the database's own message
	 *
	 * @param list<int|string|null> $params
	 * @throws RuntimeException
	 */
	private function write(string $sql, array $params = []): int {
		$rows = mod_imforum_Db::execute($sql, $params);
		if ($rows === null) {
			throw new RuntimeException(implode(' ', array_map('strval', mod_imforum_Db::connection()->errorInfo())) . ' | ' . substr($sql, 0, 120));
		}
		return $rows;
	}

	private function clearTarget(int $newMid): void {
		foreach (self::TARGETS as $item) {
			mod_imforum_Db::execute('TRUNCATE TABLE ' . mod_imforum_Db::table($item));
		}
		mod_imforum_Db::execute('DELETE FROM ' . $this->core('group_permission') . ' WHERE gperm_modid = ? AND gperm_name IN (' . mod_imforum_Db::marks($this->permissionNames()) . ')', array_merge([$newMid], $this->permissionNames()));
		mod_imforum_Db::execute('DELETE FROM ' . $this->core('xoopsnotifications') . ' WHERE not_modid = ?', [$newMid]);
	}

	/** @return array{source: int, target: int, note: string, warnings: list<string>} */
	private function copyCategories(): array {
		$n = $this->write('INSERT INTO ' . mod_imforum_Db::table('category') . ' (cat_id, cat_title, cat_description, cat_image, cat_url, weight, meta_keywords, meta_description, short_url)'
			. ' SELECT cat_id, ' . $this->text('cat_title') . ', ' . $this->text('cat_description') . ', cat_image, cat_url, cat_order, \'\', \'\', \'\' FROM ' . $this->src('categories'));
		return self::result(mod_imforum_Db::value('SELECT COUNT(*) FROM ' . $this->src('categories')), $n);
	}

	/** @return array{source: int, target: int, note: string, warnings: list<string>} */
	private function copyForums(): array {
		$n = $this->write('INSERT INTO ' . mod_imforum_Db::table('forum')
			. ' (forum_id, cat_id, parent_forum, forum_name, forum_desc, forum_active, weight, allow_html, allow_sig, allow_subject_prefix, hot_threshold, attach_maxkb, attach_ext, forum_topics, forum_posts, forum_last_post_id, meta_keywords, meta_description, short_url)'
			. ' SELECT forum_id, cat_id, parent_forum, ' . $this->text('forum_name') . ', ' . $this->text('forum_desc')
			. ', IF(forum_type = 0, 1, 0), forum_order, IF(allow_html > 0, 1, 0), allow_sig, allow_subject_prefix, hot_threshold, attach_maxkb, attach_ext, 0, 0, 0, \'\', \'\', \'\' FROM ' . $this->src('forums'));
		return self::result(mod_imforum_Db::value('SELECT COUNT(*) FROM ' . $this->src('forums')), $n);
	}

	/**
	 * The moderators sit in one serialized list per forum
	 *
	 * @return list<array{0: int, 1: int}> forum id and member id
	 */
	private function moderatorPairs(): array {
		$pairs = [];
		foreach (mod_imforum_Db::rows('SELECT forum_id, forum_moderator FROM ' . $this->src('forums') . " WHERE forum_moderator <> ''") as $row) {
			$list = @unserialize((string) $row[1], ['allowed_classes' => false]);
			foreach (is_array($list) ? array_unique(array_map('intval', array_filter($list, 'is_numeric'))) : [] as $uid) {
				if ($uid > 0) {
					$pairs[] = [(int) $row[0], $uid];
				}
			}
		}
		return $pairs;
	}

	/** @return array{source: int, target: int, note: string, warnings: list<string>} */
	private function copyModerators(): array {
		$pairs = $this->moderatorPairs();
		foreach (array_chunk($pairs, self::BATCH) as $chunk) {
			$this->insertRows('moderator', ['forum_id', 'uid'], $chunk);
		}
		return self::result(count($pairs), count($pairs));
	}

	/** @return array{source: int, target: int, note: string, warnings: list<string>} */
	private function copyTopics(): array {
		$n = $this->write('INSERT INTO ' . mod_imforum_Db::table('topic')
			. ' (topic_id, forum_id, topic_title, topic_subject, topic_poster, poster_name, topic_time, topic_views, topic_status, topic_sticky, topic_digest, digest_time, approved, topic_replies, topic_first_post_id, topic_last_post_id, meta_keywords, meta_description, short_url)'
			. ' SELECT topic_id, forum_id, ' . $this->text('topic_title') . ', topic_subject, topic_poster, ' . $this->text('poster_name')
			. ', topic_time, topic_views, topic_status, topic_sticky, topic_digest, digest_time, IF(approved > 0, 1, 0), topic_replies, 0, topic_last_post_id, \'\', \'\', \'\' FROM ' . $this->src('topics'));
		return self::result(mod_imforum_Db::value('SELECT COUNT(*) FROM ' . $this->src('topics')), (int) $n, _AM_IMFORUM_IMPORT_NOTE_DROPPED);
	}

	/** @return array{source: int, target: int, note: string, warnings: list<string>} */
	private function copyPosts(): array {
		$p = $this->src('posts');
		$n = $this->write('INSERT INTO ' . mod_imforum_Db::table('post')
			. ' (post_id, topic_id, forum_id, pid, uid, poster_name, poster_ip, post_time, subject, post_text, post_edit, icon, attachsig, approved, post_karma, require_reply, dohtml, dosmiley, doxcode, doimage, dobr)'
			. ' SELECT p.post_id, p.topic_id, p.forum_id, p.pid, p.uid, ' . $this->text('p.poster_name')
			. ", IF(p.poster_ip = 0, '', INET_NTOA(IF(p.poster_ip < 0, p.poster_ip + 4294967296, p.poster_ip))), p.post_time, " . $this->text('p.subject')
			. ', COALESCE(' . $this->text('t.post_text') . ", ''), '', p.icon, p.attachsig, IF(p.approved > 0, 1, 0), p.post_karma, p.require_reply, p.dohtml, p.dosmiley, p.doxcode, p.doimage, p.dobr"
			. " FROM $p p LEFT JOIN " . $this->src('posts_text') . ' t ON t.post_id = p.post_id');
		return self::result(mod_imforum_Db::value("SELECT COUNT(*) FROM $p"), (int) $n, _AM_IMFORUM_IMPORT_NOTE_MERGED_TEXT);
	}

	/**
	 * iForum keeps the edits as a base64 encoded serialized list of name and time; imforum as a JSON list
	 *
	 * @return array{source: int, target: int, note: string, warnings: list<string>}
	 */
	private function copyEdits(): array {
		$found = $converted = 0;
		$last = 0;
		do {
			$rows = mod_imforum_Db::rows('SELECT post_id, post_edit FROM ' . $this->src('posts_text') . " WHERE post_edit IS NOT NULL AND post_edit <> '' AND post_id > ? ORDER BY post_id LIMIT " . self::BATCH, [$last]);
			foreach ($rows as $row) {
				$last = (int) $row[0];
				$found++;
				$raw = base64_decode((string) $row[1], true);
				$list = $raw === false ? false : @unserialize($raw, ['allowed_classes' => false]);
				if (!is_array($list)) {
					continue;
				}
				$edits = [];
				foreach ($list as $edit) {
					if (is_array($edit) && isset($edit['edit_time'])) {
						$name = (string) ($edit['edit_user'] ?? '');
						$edits[] = ['uid' => 0, 'name' => self::asUtf8($name), 'time' => (int) $edit['edit_time']];
					}
				}
				if ($edits && mod_imforum_Db::ok('UPDATE ' . mod_imforum_Db::table('post') . ' SET post_edit = ? WHERE post_id = ?', [json_encode($edits, JSON_UNESCAPED_UNICODE), $last])) {
					$converted++;
				}
			}
		} while (count($rows) === self::BATCH);

		return self::result($found, $converted, '', $found > $converted ? [sprintf(_AM_IMFORUM_IMPORT_WARN_EDITS, $found - $converted)] : []);
	}

	/**
	 * The attachments of all posts: the list inside the post and the table iForum had reserved
	 *
	 * Only names that cannot leave the upload folder are accepted.
	 *
	 * @return Generator<int, array{post_id: int, name_saved: string, name_display: string, mimetype: string, downloads: int, time: int}>
	 */
	private function attachments(): Generator {
		$last = 0;
		do {
			$rows = mod_imforum_Db::rows('SELECT post_id, post_time, attachment FROM ' . $this->src('posts') . " WHERE attachment IS NOT NULL AND attachment <> '' AND post_id > ? ORDER BY post_id LIMIT " . self::BATCH, [$last]);
			foreach ($rows as $row) {
				$last = (int) $row[0];
				$raw = base64_decode((string) $row[2], true);
				$list = $raw === false ? false : @unserialize($raw, ['allowed_classes' => false]);
				foreach (is_array($list) ? $list : [] as $item) {
					if (is_array($item) && self::isSafeName((string) ($item['name_saved'] ?? ''))) {
						yield [
							'post_id' => $last,
							'name_saved' => (string) $item['name_saved'],
							'name_display' => (string) ($item['name_display'] ?? $item['name_saved']),
							'mimetype' => (string) ($item['mimetype'] ?? ''),
							'downloads' => (int) ($item['num_download'] ?? 0),
							'time' => (int) $row[1],
						];
					}
				}
			}
		} while (count($rows) === self::BATCH);

		if ($this->has('attachments')) {
			$seen = [];
			foreach (mod_imforum_Db::rows('SELECT post_id, name_saved, name_disp, mimetype, download, attach_time FROM ' . $this->src('attachments') . ' ORDER BY attach_id') as $row) {
				$name = (string) $row[1];
				if (self::isSafeName($name) && !isset($seen[$row[0] . '/' . $name])) {
					$seen[$row[0] . '/' . $name] = true;
					yield ['post_id' => (int) $row[0], 'name_saved' => $name, 'name_display' => (string) $row[2], 'mimetype' => (string) $row[3], 'downloads' => (int) $row[4], 'time' => (int) $row[5]];
				}
			}
		}
	}

	private function countAttachments(): int {
		$seen = [];
		foreach ($this->attachments() as $attachment) {
			$seen[$attachment['post_id'] . '/' . $attachment['name_saved']] = true;
		}
		return count($seen);
	}

	/** @return array{source: int, target: int, note: string, warnings: list<string>} */
	private function copyAttachments(): array {
		$seen = [];
		$batch = [];
		$flush = function () use (&$batch): void {
			$this->insertRows('attachment', ['post_id', 'name_saved', 'name_display', 'mimetype', 'filesize', 'downloads', 'online', 'attach_time'], $batch);
			$batch = [];
		};
		foreach ($this->attachments() as $attachment) {
			$key = $attachment['post_id'] . '/' . $attachment['name_saved'];
			if (isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$batch[] = [
				$attachment['post_id'], $attachment['name_saved'], self::asUtf8($attachment['name_display']),
				$attachment['mimetype'], 0, $attachment['downloads'], 1, $attachment['time'],
			];
			if (count($batch) >= self::BATCH) {
				$flush();
			}
		}
		if ($batch) {
			$flush();
		}
		return self::result(count($seen), count($seen), _AM_IMFORUM_IMPORT_NOTE_FILES_LATER);
	}

	/** @return array{source: int, target: int, note: string, warnings: list<string>} */
	private function copyReports(): array {
		if (!$this->has('report')) {
			return self::result(0, 0, _AM_IMFORUM_IMPORT_NOTE_ABSENT);
		}
		$n = $this->write('INSERT INTO ' . mod_imforum_Db::table('report')
			. ' (report_id, post_id, reporter_uid, reporter_ip, report_time, report_text, report_result, report_memo)'
			. " SELECT report_id, post_id, reporter_uid, IF(reporter_ip = 0, '', INET_NTOA(IF(reporter_ip < 0, reporter_ip + 4294967296, reporter_ip))), report_time, "
			. $this->text('report_text') . ', IF(report_result > 0, 1, 0), ' . $this->text('report_memo') . ' FROM ' . $this->src('report'));
		return self::result(mod_imforum_Db::value('SELECT COUNT(*) FROM ' . $this->src('report')), $n);
	}

	/** @return array{source: int, target: int, note: string, warnings: list<string>} */
	private function copySuspensions(): array {
		if (!$this->has('moderates')) {
			return self::result(0, 0, _AM_IMFORUM_IMPORT_NOTE_ABSENT);
		}
		$n = $this->write('INSERT INTO ' . mod_imforum_Db::table('suspension') . ' (suspension_id, uid, ip, forum_id, start_time, end_time, description)'
			. ' SELECT mod_id, uid, ip, forum_id, mod_start, mod_end, ' . $this->text('mod_desc') . ' FROM ' . $this->src('moderates') . ' WHERE mod_end >= ?', [$this->now]);
		return self::result(mod_imforum_Db::value('SELECT COUNT(*) FROM ' . $this->src('moderates')), (int) $n, _AM_IMFORUM_IMPORT_NOTE_EXPIRED);
	}

	/** @return array{source: int, target: int, note: string, warnings: list<string>} */
	private function copyReadmarks(): array {
		$total = 0;
		foreach ([1 => 'reads_forum', 2 => 'reads_topic'] as $type => $table) {
			if ($this->has($table)) {
				$total += $this->write('INSERT INTO ' . mod_imforum_Db::table('readmark') . ' (read_type, uid, item_id, post_id, read_time)'
					. ' SELECT ?, uid, read_item, MAX(post_id), MAX(read_time) FROM ' . $this->src($table) . ' WHERE uid > 0 GROUP BY uid, read_item', [$type]);
			}
		}
		return self::result($this->countReadmarks(), $total, _AM_IMFORUM_IMPORT_NOTE_MERGED);
	}

	/** Marks of members, one per member and item */
	private function countReadmarks(): int {
		$total = 0;
		foreach (['reads_forum', 'reads_topic'] as $table) {
			if ($this->has($table)) {
				$total += mod_imforum_Db::value('SELECT COUNT(*) FROM (SELECT 1 FROM ' . $this->src($table) . ' WHERE uid > 0 GROUP BY uid, read_item) marks');
			}
		}
		return $total;
	}

	/** @return array{source: int, target: int, note: string, warnings: list<string>} */
	private function copyDigests(): array {
		if (!$this->has('digest')) {
			return self::result(0, 0, _AM_IMFORUM_IMPORT_NOTE_ABSENT);
		}
		$n = $this->write('INSERT INTO ' . mod_imforum_Db::table('digest') . ' (digest_id, digest_time, digest_content)'
			. ' SELECT digest_id, digest_time, ' . $this->text('digest_content') . ' FROM ' . $this->src('digest'));
		return self::result(mod_imforum_Db::value('SELECT COUNT(*) FROM ' . $this->src('digest')), $n);
	}

	/** @return array{source: int, target: int, note: string, warnings: list<string>} */
	private function copyArchive(): array {
		if (!$this->has('archive')) {
			return self::result(0, 0, _AM_IMFORUM_IMPORT_NOTE_ABSENT);
		}
		$n = $this->write('INSERT INTO ' . mod_imforum_Db::table('archive') . ' (topic_id, post_id, post_text, archived_time)'
			. ' SELECT topic_id, post_id, ' . $this->text('post_text') . ', ? FROM ' . $this->src('archive'), [$this->now]);
		return self::result(mod_imforum_Db::value('SELECT COUNT(*) FROM ' . $this->src('archive')), $n);
	}

	/**
	 * The permissions iForum and imforum share, now under the id of imforum
	 *
	 * The poll permissions are left out. Rights on forums or categories that are gone are not copied.
	 *
	 * @return list<string>
	 */
	private function permissionNames(): array {
		return array_merge(mod_imforum_ForumHandler::PERMISSIONS, ['category_access']);
	}

	private function countPermissions(int $oldMid): int {
		$g = $this->core('group_permission');
		return mod_imforum_Db::value("SELECT COUNT(*) FROM (SELECT DISTINCT gperm_groupid, gperm_itemid, gperm_name FROM $g WHERE gperm_modid = ? AND ("
			. '(gperm_name = ? AND gperm_itemid IN (SELECT cat_id FROM ' . $this->src('categories') . '))'
			. ' OR (gperm_name IN (' . mod_imforum_Db::marks(mod_imforum_ForumHandler::PERMISSIONS) . ') AND gperm_itemid IN (SELECT forum_id FROM ' . $this->src('forums') . '))'
			. ')) rights', array_merge([$oldMid, 'category_access'], mod_imforum_ForumHandler::PERMISSIONS));
	}

	/** @return array{source: int, target: int, note: string, warnings: list<string>} */
	private function copyPermissions(int $oldMid, int $newMid): array {
		$g = $this->core('group_permission');
		$columns = " (gperm_groupid, gperm_itemid, gperm_modid, gperm_name) SELECT DISTINCT g.gperm_groupid, g.gperm_itemid, $newMid, g.gperm_name FROM $g g";
		$n = $this->write("INSERT INTO $g" . $columns . ' JOIN ' . mod_imforum_Db::table('category') . ' c ON c.cat_id = g.gperm_itemid WHERE g.gperm_modid = ? AND g.gperm_name = ?', [$oldMid, 'category_access']);
		$n += $this->write("INSERT INTO $g" . $columns . ' JOIN ' . mod_imforum_Db::table('forum') . ' f ON f.forum_id = g.gperm_itemid WHERE g.gperm_modid = ? AND g.gperm_name IN (' . mod_imforum_Db::marks(mod_imforum_ForumHandler::PERMISSIONS) . ')', array_merge([$oldMid], mod_imforum_ForumHandler::PERMISSIONS));
		return self::result($this->countPermissions($oldMid), $n, _AM_IMFORUM_IMPORT_NOTE_POLL_RIGHTS);
	}

	/** @return array{source: int, target: int, note: string, warnings: list<string>} */
	private function copyNotifications(int $oldMid, int $newMid): array {
		$n = $total = 0;
		$table = $this->core('xoopsnotifications');
		foreach (self::NOTIFICATIONS as [$category, $event, $kind]) {
			$total += mod_imforum_Db::value("SELECT COUNT(*) FROM $table WHERE not_modid = ? AND not_category = ? AND not_event = ?", [$oldMid, $category, $event]);
			$join = match ($kind) {
				'topic' => ' JOIN ' . mod_imforum_Db::table('topic') . ' i ON i.topic_id = n.not_itemid',
				'forum' => ' JOIN ' . mod_imforum_Db::table('forum') . ' i ON i.forum_id = n.not_itemid',
				default => '',
			};
			$n += $this->write("INSERT INTO $table (not_modid, not_itemid, not_category, not_event, not_uid, not_mode)"
				. " SELECT $newMid, n.not_itemid, n.not_category, n.not_event, n.not_uid, n.not_mode FROM $table n$join"
				. ' WHERE n.not_modid = ? AND n.not_category = ? AND n.not_event = ?', [$oldMid, $category, $event]);
		}
		$all = mod_imforum_Db::value("SELECT COUNT(*) FROM $table WHERE not_modid = ?", [$oldMid]);
		return self::result($all, $n, $all > $total ? _AM_IMFORUM_IMPORT_NOTE_EVENTS : '');
	}

	/**
	 * The preferences with an equal in imforum
	 *
	 * @return array{source: int, target: int, note: string, warnings: list<string>}
	 */
	private function copySettings(int $oldMid, int $newMid): array {
		$config = $this->core('config');
		$old = [];
		foreach (mod_imforum_Db::rows("SELECT conf_name, conf_value FROM $config WHERE conf_modid = ?", [$oldMid]) as $row) {
			$old[(string) $row[0]] = (string) $row[1];
		}
		$new = [];
		foreach (self::SAME_SETTINGS as $name) {
			if (isset($old[$name])) {
				$new[$name] = $old[$name];
			}
		}
		if (isset($old['rss_maxitems'])) {
			$new['rss_items'] = $old['rss_maxitems'];
		}
		if (isset($old['wol_enabled'])) {
			$new['show_online'] = $old['wol_enabled'];
		}
		if (isset($old['view_mode'])) {
			$new['default_view'] = (int) $old['view_mode'] === 2 ? 'tree' : 'flat';
		}
		if (isset($old['disc_show'])) {
			$new['disclaimer_show'] = (int) $old['disc_show'] > 0 ? 1 : 0;
		}
		if (isset($old['subject_prefix'])) {
			// iForum counts the first entry ("NONE") as prefix 0, imforum starts at the first real one
			$entries = explode(',', $old['subject_prefix']);
			array_shift($entries);
			$new['subject_prefix'] = implode(',', $entries);
		}
		$done = 0;
		foreach ($new as $name => $value) {
			// an update that changes nothing reports no row, so ask whether the preference exists
			if (mod_imforum_Db::value("SELECT COUNT(*) FROM $config WHERE conf_modid = ? AND conf_name = ?", [$newMid, $name]) > 0
				&& mod_imforum_Db::ok("UPDATE $config SET conf_value = ? WHERE conf_modid = ? AND conf_name = ?", [(string) $value, $newMid, $name])) {
				$done++;
			}
		}
		return self::result(count($new), $done);
	}

	/**
	 * The permission template iForum kept in a cache file: group id => permission name => allowed
	 *
	 * @return array{source: int, target: int, note: string, warnings: list<string>}
	 */
	private function copyTemplate(): array {
		$file = XOOPS_CACHE_PATH . '/' . $this->sourceDirname . '_perm_template.php';
		$template = is_file($file) ? (static fn (string $path): mixed => @include $path)($file) : null;
		if (!is_array($template)) {
			return self::result(0, 0, _AM_IMFORUM_IMPORT_NOTE_ABSENT);
		}
		$rows = [];
		foreach ($template as $groupId => $permissions) {
			foreach (is_array($permissions) ? $permissions : [] as $name => $allowed) {
				if ($allowed && in_array($name, mod_imforum_ForumHandler::PERMISSIONS, true) && (int) $groupId > 0) {
					$rows[] = [(string) $name, (int) $groupId];
				}
			}
		}
		$this->insertRows('permtemplate', ['perm_name', 'groupid'], $rows);
		return self::result(count($rows), count($rows));
	}

	/**
	 * Copy the files of the attachments next to the other uploads, the sizes come from the copies
	 *
	 * @return array{source: int, target: int, note: string, warnings: list<string>}
	 */
	private function copyFiles(): array {
		$from = $this->sourceUploadPath();
		$copied = $total = 0;
		$missing = [];
		if (!imforum_create_upload_folder()) {
			return self::result(0, 0, '', [_AM_IMFORUM_IMPORT_ERR_UPLOAD_FOLDER]);
		}
		$attachments = mod_imforum_Db::table('attachment');
		foreach (mod_imforum_Db::rows("SELECT DISTINCT name_saved FROM $attachments") as $row) {
			$name = (string) $row[0];
			$total++;
			$source = $from . $name;
			$target = IMFORUM_UPLOAD_PATH . $name;
			if (!is_file($target) && is_file($source) && @copy($source, $target)) {
				$copied++;
			}
			if (is_file($target)) {
				mod_imforum_Db::ok("UPDATE $attachments SET filesize = ? WHERE name_saved = ?", [(int) filesize($target), $name]);
			} else {
				$missing[] = $name;
			}
		}
		return self::result($total, $total - count($missing), '', $missing ? [sprintf(_AM_IMFORUM_IMPORT_WARN_FILES, count($missing), implode(', ', array_slice($missing, 0, 10)))] : []);
	}

	/**
	 * Topics and forums that have only posts waiting for approval keep a reference to them
	 *
	 * The recount counts approved posts only, which would leave such a topic without a first and last post.
	 */
	private function keepPendingReferences(): void {
		$topic = mod_imforum_Db::table('topic');
		mod_imforum_Db::execute("UPDATE $topic t JOIN (SELECT topic_id, MIN(post_id) first, MAX(post_id) last FROM " . mod_imforum_Db::table('post') . ' GROUP BY topic_id) p ON p.topic_id = t.topic_id'
			. ' SET t.topic_first_post_id = p.first, t.topic_last_post_id = p.last WHERE t.topic_first_post_id = 0');
	}

	/**
	 * Names of attachments without a file in the old upload folder
	 *
	 * @return list<string>
	 */
	private function missingSourceFiles(): array {
		$from = $this->sourceUploadPath();
		$missing = [];
		foreach ($this->attachments() as $attachment) {
			if (!is_file($from . $attachment['name_saved'])) {
				$missing[$attachment['name_saved']] = true;
			}
		}
		return array_map('strval', array_keys($missing));
	}

	/**
	 * Names in a table of imforum that have no file in a folder
	 *
	 * @return list<string>
	 */
	private function missingFiles(string $folder, string $item): array {
		$missing = [];
		foreach (mod_imforum_Db::rows('SELECT DISTINCT name_saved FROM ' . mod_imforum_Db::table($item)) as $row) {
			if (!self::isSafeName((string) $row[0]) || !is_file($folder . $row[0])) {
				$missing[] = (string) $row[0];
			}
		}
		return $missing;
	}

	/**
	 * The upload folder of iForum, with a slash at the end
	 */
	private function sourceUploadPath(): string {
		$config = mod_imforum_Db::row('SELECT conf_value FROM ' . $this->core('config') . ' WHERE conf_modid = ? AND conf_name = ?', [$this->moduleId($this->sourceDirname), 'dir_attachments']);
		$folder = trim((string) ($config[0] ?? ''), '/\\');
		if ($folder === '' || str_contains($folder, '..')) {
			$folder = 'uploads/' . $this->sourceDirname;
		}
		return ICMS_ROOT_PATH . '/' . $folder . '/';
	}

	/**
	 * Posts per group in the source that differ from the target
	 *
	 * @return list<string> the group ids with the two counts
	 */
	private function groupMismatches(string $column, string $source, string $target): array {
		$mismatches = [];
		$rows = mod_imforum_Db::rows("SELECT s.$column, s.n, COALESCE(t.n, 0) FROM (SELECT $column, COUNT(*) n FROM $source GROUP BY $column) s"
			. " LEFT JOIN (SELECT $column, COUNT(*) n FROM $target GROUP BY $column) t ON t.$column = s.$column WHERE s.n <> COALESCE(t.n, 0) LIMIT 50");
		foreach ($rows as $row) {
			$mismatches[] = $row[0] . ': ' . $row[1] . '/' . $row[2];
		}
		return $mismatches;
	}

	/**
	 * Insert rows in one statement per call
	 *
	 * @param list<string> $columns
	 * @param list<list<int|string|null>> $rows
	 */
	private function insertRows(string $item, array $columns, array $rows): void {
		if (!$rows) {
			return;
		}
		$one = '(' . mod_imforum_Db::marks($columns) . ')';
		$params = [];
		foreach ($rows as $row) {
			array_push($params, ...$row);
		}
		$this->write('INSERT INTO ' . mod_imforum_Db::table($item) . ' (' . implode(', ', $columns) . ') VALUES ' . implode(', ', array_fill(0, count($rows), $one)), $params);
	}

	/**
	 * A text column as it is copied
	 *
	 * Old XOOPS sites often have UTF-8 text in columns that say latin1; copying those
	 * as they are turns every accent into two characters. Reading the bytes as UTF-8
	 * puts that right.
	 */
	private function text(string $column): string {
		return $this->encoding === self::ENCODING_UTF8_BYTES ? "CONVERT(CAST($column AS BINARY) USING utf8mb4)" : $column;
	}

	/**
	 * A name taken out of a serialized list
	 *
	 * It travelled as base64, so it is exactly the bytes iForum wrote: UTF-8 on most sites,
	 * latin1 on some. imforum stores UTF-8 only.
	 */
	private static function asUtf8(string $text): string {
		return mb_check_encoding($text, 'UTF-8') ? $text : mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
	}

	/**
	 * Names that stay inside the upload folder
	 */
	private static function isSafeName(string $name): bool {
		return preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,254}$/', $name) === 1 && !str_contains($name, '..');
	}

	private function src(string $table): string {
		return XOOPS_DB_PREFIX . '_bb_' . $table;
	}

	private function core(string $table): string {
		return XOOPS_DB_PREFIX . '_' . $table;
	}

	private function has(string $table): bool {
		return !empty($this->present[$table]);
	}

	private function tableExists(string $table): bool {
		return mod_imforum_Db::value('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]) > 0;
	}

	private function moduleId(string $dirname): int {
		return mod_imforum_Db::value('SELECT mid FROM ' . $this->core('modules') . ' WHERE dirname = ?', [$dirname]);
	}
}
