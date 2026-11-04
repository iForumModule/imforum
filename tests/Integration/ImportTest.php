<?php

declare(strict_types=1);

namespace Imforum\Tests\Integration;

use mod_imforum_Db as Db;
use mod_imforum_Importer as Importer;

/**
 * Copying the data of a running iForum installation
 */
final class ImportTest extends IntegrationTestCase
{
	/** @var array{mid: int, uploads: string, now: int} */
	private array $old;

	private int $newMid;

	protected function setUp(): void
	{
		parent::setUp();
		OldIforum::create();
		$this->old = OldIforum::fill(ICMS_ROOT_PATH . '/uploads/iforum');
		$this->newMid = Db::value('SELECT mid FROM ' . XOOPS_DB_PREFIX . "_modules WHERE dirname = 'imforum'");
		foreach (['topics_per_page', 'rss_items', 'default_view', 'disclaimer_show', 'subject_prefix'] as $name) {
			Db::execute('INSERT INTO ' . XOOPS_DB_PREFIX . '_config (conf_modid, conf_name, conf_value) VALUES (?, ?, ?)', [$this->newMid, $name, 'default']);
		}
	}

	protected function tearDown(): void
	{
		foreach (glob($this->old['uploads'] . '/*') ?: [] as $file) {
			unlink($file);
		}
		@rmdir($this->old['uploads']);
		foreach (glob(IMFORUM_UPLOAD_PATH . '*') ?: [] as $file) {
			unlink($file);
		}
		@unlink(XOOPS_CACHE_PATH . '/iforum_perm_template.php');
	}

	private function importer(string $encoding = Importer::ENCODING_AS_IS): Importer
	{
		return new Importer($encoding, $this->old['now']);
	}

	/**
	 * @return array<string, int> checksum per table of the old module and the core tables it reads
	 */
	private function sourceChecksums(): array
	{
		$sums = [];
		foreach (Database::tables('bb_') as $table) {
			$sums[$table] = (int) Db::row("CHECKSUM TABLE `$table`")[1];
		}
		foreach (['config', 'group_permission', 'xoopsnotifications'] as $core) {
			$table = XOOPS_DB_PREFIX . '_' . $core;
			$sums[$core] = Db::value("SELECT COUNT(*) FROM `$table` WHERE " . ($core === 'config' ? 'conf_modid' : ($core === 'group_permission' ? 'gperm_modid' : 'not_modid')) . ' = ?', [$this->old['mid']]);
		}
		return $sums;
	}

	/**
	 * @return list<array<int, int|string|null>>
	 */
	private static function query(string $sql, array $params = []): array
	{
		return Db::rows($sql, $params);
	}

	public function testTheCheckReportsAndWritesNothing(): void
	{
		$report = $this->importer()->check();

		self::assertSame([], $report['errors']);
		self::assertTrue($report['ok']);
		$warnings = implode(' | ', $report['warnings']);
		self::assertStringContainsString('1 posts without a topic', $warnings);
		self::assertStringContainsString('1 posts without text', $warnings);
		self::assertStringContainsString('gone.zip', $warnings, 'the missing attachment file is named');
		self::assertSame(0, self::rows('post'));
		self::assertSame(0, self::rows('forum'));
	}

	public function testTheCheckStopsWhenIForumIsNotInstalled(): void
	{
		Db::execute('DELETE FROM ' . XOOPS_DB_PREFIX . "_modules WHERE dirname = 'iforum'");

		$report = $this->importer()->check();

		self::assertFalse($report['ok']);
		self::assertStringContainsString('iforum', $report['errors'][0]);
		self::assertFalse($this->importer()->run()['ok']);
		self::assertSame(0, self::rows('category'));
	}

	public function testTheCheckStopsOnAMissingTableOrColumn(): void
	{
		OldIforum::drop('posts_text');
		Db::execute('ALTER TABLE ' . OldIforum::name('topics') . ' DROP COLUMN topic_sticky');

		$report = $this->importer()->check();

		self::assertFalse($report['ok']);
		self::assertCount(2, $report['errors']);
		self::assertStringContainsString('bb_posts_text', implode(' ', $report['errors']));
		self::assertStringContainsString('topic_sticky', implode(' ', $report['errors']));
	}

	public function testAMissingOptionalTableIsOnlyAWarning(): void
	{
		OldIforum::drop('archive');
		OldIforum::drop('digest');

		$report = $this->importer()->run();

		self::assertTrue($report['ok'], implode(' ', $report['errors']));
		self::assertSame(0, self::rows('archive'));
		self::assertSame(0, self::rows('digest'));
	}

	public function testEverythingIsCopiedWithItsIds(): void
	{
		$report = $this->importer()->run();

		self::assertSame([], $report['errors']);
		self::assertSame([3, 7], array_map('intval', array_column(self::query('SELECT cat_id FROM ' . Db::table('category') . ' ORDER BY cat_id'), 0)));
		self::assertSame(['General', 'Chat here', 2], array_map(static fn ($v) => is_numeric($v) ? (int) $v : $v, self::query('SELECT cat_title, cat_description, weight FROM ' . Db::table('category') . ' WHERE cat_id = 3')[0]));

		// forums: type 1 means inactive, "checked by default" for HTML becomes plain yes
		$forums = [];
		foreach (self::query('SELECT forum_id, forum_name, parent_forum, forum_active, allow_html, weight, hot_threshold, attach_ext FROM ' . Db::table('forum') . ' ORDER BY forum_id') as $row) {
			$forums[(int) $row[0]] = array_map('strval', $row);
		}
		self::assertSame(['10', 'Lounge', '0', '1', '1', '4', '8', 'zip|jpg'], $forums[10]);
		self::assertSame(['11', 'Archive room', '10', '0', '0', '1', '9', ''], $forums[11]);

		// the moderator list is unserialized, without doubles and without member 0
		self::assertSame([[10, 5], [10, 6], [12, 7]], array_map(static fn (array $r): array => [(int) $r[0], (int) $r[1]], self::query('SELECT forum_id, uid FROM ' . Db::table('moderator') . ' ORDER BY forum_id, uid')));

		$topic = self::query('SELECT topic_title, topic_poster, topic_views, topic_status, topic_sticky, topic_digest, topic_subject, approved, poster_name FROM ' . Db::table('topic') . ' WHERE topic_id = 100')[0];
		self::assertSame(['First topic', 5, 40, 1, 1, 1, 1, 1, 'Ann'], array_map(static fn ($v) => is_numeric($v) && !preg_match('/[a-z]/i', (string) $v) ? (int) $v : $v, $topic));
		self::assertSame(3, self::rows('topic'));
		self::assertSame(6, self::rows('post'));
	}

	public function testPostsKeepTheirTextFlagsAndReadableIpAddresses(): void
	{
		$this->importer()->run();
		$posts = [];
		foreach (self::query('SELECT post_id, pid, topic_id, uid, poster_name, poster_ip, subject, post_text, dohtml, approved, post_karma FROM ' . Db::table('post') . ' ORDER BY post_id') as $row) {
			$posts[(int) $row[0]] = $row;
		}

		self::assertSame('192.168.1.1', $posts[1000][5], 'a negative integer is an address above 127.255.255.255');
		self::assertSame('', $posts[1001][5], 'zero means unknown');
		self::assertSame('1.2.3.4', $posts[1002][5]);
		self::assertSame('Hello [b]world[/b] <i>caf&eacute;</i>', $posts[1000][7]);
		self::assertSame("Line one\nLine two", $posts[1001][7]);
		self::assertSame(1, (int) $posts[1000][8]);
		self::assertSame('Guest', $posts[1001][4]);
		self::assertSame(1000, (int) $posts[1001][1]);
		self::assertSame(0, (int) $posts[1004][9], 'a waiting post stays waiting');
		self::assertSame(50, (int) $posts[1003][10]);
		self::assertSame('', $posts[1005][7], 'a post without a text row gets an empty text');
	}

	public function testEditHistoriesBecomeJson(): void
	{
		$this->importer()->run();

		$edits = json_decode((string) Db::row('SELECT post_edit FROM ' . Db::table('post') . ' WHERE post_id = 1001')[0], true);

		self::assertSame([['uid' => 0, 'name' => 'Ann', 'time' => $this->old['now'] - 3600], ['uid' => 0, 'name' => 'Bert', 'time' => $this->old['now'] - 60]], $edits);
		self::assertSame('', (string) Db::row('SELECT post_edit FROM ' . Db::table('post') . ' WHERE post_id = 1000')[0]);
	}

	public function testAttachmentsAreCopiedWithTheirFilesAndOnlySafeNames(): void
	{
		$report = $this->importer()->run();

		$rows = [];
		foreach (self::query('SELECT post_id, name_saved, name_display, mimetype, filesize, downloads, online FROM ' . Db::table('attachment') . ' ORDER BY name_saved') as $row) {
			$rows[(string) $row[1]] = $row;
		}
		self::assertCount(3, $rows, 'two from the posts and the reserved table; the path that leaves the folder is dropped');
		self::assertArrayNotHasKey('../../mainfile.php', $rows);
		self::assertSame(1002, (int) $rows['a1b2c3.jpg'][0]);
		self::assertSame('holiday.jpg', $rows['a1b2c3.jpg'][2]);
		self::assertSame(4, (int) $rows['a1b2c3.jpg'][5]);
		self::assertSame(3, (int) $rows['a1b2c3.jpg'][4], 'the size comes from the copy on disk');
		self::assertSame(12, (int) $rows['reserved.txt'][4]);
		self::assertSame(0, (int) $rows['gone.zip'][4]);
		self::assertFileExists(IMFORUM_UPLOAD_PATH . 'a1b2c3.jpg');
		self::assertFileExists($this->old['uploads'] . '/a1b2c3.jpg', 'the original stays');
		self::assertFileDoesNotExist(IMFORUM_UPLOAD_PATH . 'gone.zip');
		self::assertStringContainsString('gone.zip', implode(' ', $report['warnings']));
	}

	public function testReportsSuspensionsReadMarksDigestsAndArchive(): void
	{
		$this->importer()->run();

		self::assertSame([['1001', '5', '1.2.3.4', 'Rude', '1', 'Handled']], array_map(static fn (array $r): array => array_map('strval', $r), self::query('SELECT post_id, reporter_uid, reporter_ip, report_text, report_result, report_memo FROM ' . Db::table('report'))));
		self::assertSame([[9, 'Spam']], array_map(static fn (array $r): array => [(int) $r[0], (string) $r[1]], self::query('SELECT uid, description FROM ' . Db::table('suspension'))), 'a suspension that ended is left out');

		// guests have no marks and a member has one mark per item
		self::assertSame([[1, 5, 10, 1003], [2, 5, 100, 1002], [2, 6, 101, 1003]], array_map(static fn (array $r): array => array_map('intval', $r), self::query('SELECT read_type, uid, item_id, post_id FROM ' . Db::table('readmark') . ' ORDER BY read_type, uid, item_id')));
		self::assertSame('Yesterday', (string) Db::row('SELECT digest_content FROM ' . Db::table('digest'))[0]);
		self::assertSame([[55, 555, 'Archived text', $this->old['now']]], array_map(static fn (array $r): array => [(int) $r[0], (int) $r[1], (string) $r[2], (int) $r[3]], self::query('SELECT topic_id, post_id, post_text, archived_time FROM ' . Db::table('archive'))));
	}

	public function testPermissionsSubscriptionsPreferencesAndTheTemplate(): void
	{
		$this->importer()->run();
		$perm = XOOPS_DB_PREFIX . '_group_permission';

		// the poll right and the rights on things that are gone stay behind
		$rights = array_map(static fn (array $r): string => implode('/', array_map('strval', $r)), self::query("SELECT gperm_groupid, gperm_itemid, gperm_name FROM $perm WHERE gperm_modid = ? ORDER BY gperm_name, gperm_groupid, gperm_itemid", [$this->newMid]));
		self::assertSame(['2/3/category_access', '1/10/forum_access', '2/10/forum_access', '3/10/forum_access', '1/12/forum_noapprove', '2/10/forum_post', '2/10/forum_view'], $rights);
		self::assertSame(10, Db::value("SELECT COUNT(*) FROM $perm WHERE gperm_modid = ?", [$this->old['mid']]), 'the rights of iForum are not touched');

		$notes = array_map(static fn (array $r): string => implode('/', array_map('strval', $r)), self::query('SELECT not_category, not_itemid, not_event, not_uid FROM ' . XOOPS_DB_PREFIX . '_xoopsnotifications WHERE not_modid = ? ORDER BY not_category, not_itemid', [$this->newMid]));
		self::assertSame(['forum/10/new_thread/5', 'global/0/digest/6', 'thread/100/new_post/5'], $notes);

		$config = [];
		foreach (self::query('SELECT conf_name, conf_value FROM ' . XOOPS_DB_PREFIX . '_config WHERE conf_modid = ?', [$this->newMid]) as $row) {
			$config[(string) $row[0]] = (string) $row[1];
		}
		self::assertSame('25', $config['topics_per_page']);
		self::assertSame('tree', $config['default_view']);
		self::assertSame('Question,Tip', $config['subject_prefix'], 'the first entry (NONE) was prefix 0 in iForum');
		self::assertSame('1', $config['disclaimer_show']);
		self::assertSame('15', $config['rss_items'], 'rss_maxitems is called rss_items in imforum');

		$template = self::query('SELECT perm_name, groupid FROM ' . Db::table('permtemplate') . ' ORDER BY perm_name, groupid');
		self::assertSame([['forum_access', 1], ['forum_access', 2], ['forum_post', 1], ['forum_view', 1]], array_map(static fn (array $r): array => [(string) $r[0], (int) $r[1]], $template), 'group 0 and the poll right are dropped');
	}

	public function testCountersAreRecountedAndPendingTopicsKeepTheirPosts(): void
	{
		$this->importer()->run();

		$forum = self::query('SELECT forum_topics, forum_posts, forum_last_post_id FROM ' . Db::table('forum') . ' WHERE forum_id = 10')[0];
		self::assertSame([2, 5, 1005], array_map('intval', $forum), 'the 99 of the old data is not taken over; the post of the missing topic counts for its forum');
		$topic = self::query('SELECT topic_replies, topic_first_post_id, topic_last_post_id FROM ' . Db::table('topic') . ' WHERE topic_id = 100')[0];
		self::assertSame([2, 1000, 1002], array_map('intval', $topic));

		$waiting = self::query('SELECT topic_first_post_id, topic_last_post_id FROM ' . Db::table('topic') . ' WHERE topic_id = 102')[0];
		self::assertSame([1004, 1004], array_map('intval', $waiting), 'a topic that waits for approval still points at its post');
	}

	public function testVerifyConfirmsTheCopyAndNoticesDamage(): void
	{
		$importer = $this->importer();
		$importer->run();

		$report = $importer->verify();
		self::assertSame([], $report['errors']);
		self::assertTrue($report['ok']);
		self::assertNotEmpty($report['rows']);

		Db::execute('DELETE FROM ' . Db::table('post') . ' WHERE post_id = 1002');
		Db::execute('UPDATE ' . Db::table('post') . " SET post_text = 'changed'");
		$report = $importer->verify();
		self::assertFalse($report['ok']);
		$errors = implode(' | ', $report['errors']);
		self::assertStringContainsString('Posts: 6 in iForum, 5 in imforum', $errors);
		self::assertStringContainsString('The text of these posts differs: 1000', $errors);
	}

	public function testTheSourceIsNeverChanged(): void
	{
		$before = $this->sourceChecksums();
		$importer = $this->importer();
		$importer->check();
		$importer->run();
		$importer->verify();
		$importer->rollback();

		self::assertSame($before, $this->sourceChecksums());
		self::assertFileExists($this->old['uploads'] . '/a1b2c3.jpg');
	}

	public function testRunningTwiceGivesTheSameResult(): void
	{
		$importer = $this->importer();
		$importer->run();
		$first = [self::rows('post'), self::rows('attachment'), self::rows('readmark'), self::rows('moderator'), Db::value('SELECT COUNT(*) FROM ' . XOOPS_DB_PREFIX . '_group_permission WHERE gperm_modid = ?', [$this->newMid])];

		$report = $importer->run();

		self::assertTrue($report['ok']);
		self::assertSame($first, [self::rows('post'), self::rows('attachment'), self::rows('readmark'), self::rows('moderator'), Db::value('SELECT COUNT(*) FROM ' . XOOPS_DB_PREFIX . '_group_permission WHERE gperm_modid = ?', [$this->newMid])]);
		self::assertTrue($importer->verify()['ok']);
	}

	public function testRollbackEmptiesImforumAndLeavesIForumAlone(): void
	{
		$this->importer()->run();
		$posts = Db::value('SELECT COUNT(*) FROM ' . OldIforum::name('posts'));

		$report = $this->importer()->rollback();

		self::assertTrue($report['ok']);
		foreach (['category', 'forum', 'moderator', 'topic', 'post', 'attachment', 'report', 'suspension', 'readmark', 'digest', 'archive', 'permtemplate'] as $item) {
			self::assertSame(0, self::rows($item), $item);
		}
		self::assertSame(0, Db::value('SELECT COUNT(*) FROM ' . XOOPS_DB_PREFIX . '_group_permission WHERE gperm_modid = ?', [$this->newMid]));
		self::assertSame(0, Db::value('SELECT COUNT(*) FROM ' . XOOPS_DB_PREFIX . '_xoopsnotifications WHERE not_modid = ?', [$this->newMid]));
		self::assertFileDoesNotExist(IMFORUM_UPLOAD_PATH . 'a1b2c3.jpg');
		self::assertFileExists($this->old['uploads'] . '/a1b2c3.jpg');
		self::assertSame($posts, Db::value('SELECT COUNT(*) FROM ' . OldIforum::name('posts')));
		self::assertSame(10, Db::value('SELECT COUNT(*) FROM ' . XOOPS_DB_PREFIX . '_group_permission WHERE gperm_modid = ?', [$this->old['mid']]));
	}

	public function testRollbackOnlyRemovesFilesInTheUploadFolder(): void
	{
		$this->importer()->run();
		$outside = ICMS_ROOT_PATH . '/uploads/precious.txt';
		file_put_contents($outside, 'keep');
		Db::execute('UPDATE ' . Db::table('attachment') . " SET name_saved = '../precious.txt' WHERE name_saved = 'reserved.txt'");

		$this->importer()->rollback();

		self::assertFileExists($outside);
		unlink($outside);
	}

	public function testUtf8StoredInLatin1ColumnsCanBeReadAsBytes(): void
	{
		foreach (['posts_text', 'posts', 'topics', 'forums', 'categories'] as $table) {
			Db::execute('ALTER TABLE ' . OldIforum::name($table) . ' CONVERT TO CHARACTER SET latin1');
		}
		// the bytes of "café" in UTF-8, written into a latin1 column
		Db::execute('UPDATE ' . OldIforum::name('posts_text') . " SET post_text = CONCAT('caf', CAST(UNHEX('C3A9') AS CHAR CHARACTER SET latin1)) WHERE post_id = 1003");
		Db::execute('UPDATE ' . OldIforum::name('topics') . " SET topic_title = CONCAT('Caf', CAST(UNHEX('C3A9') AS CHAR CHARACTER SET latin1)) WHERE topic_id = 101");

		$check = $this->importer()->check();
		self::assertStringContainsString('latin1', implode(' ', $check['warnings']), 'the check suspects UTF-8 bytes');

		$this->importer(Importer::ENCODING_AS_IS)->run();
		self::assertSame('cafÃ©', (string) Db::row('SELECT post_text FROM ' . Db::table('post') . ' WHERE post_id = 1003')[0]);

		$importer = $this->importer(Importer::ENCODING_UTF8_BYTES);
		self::assertTrue($importer->run()['ok']);
		self::assertSame('café', (string) Db::row('SELECT post_text FROM ' . Db::table('post') . ' WHERE post_id = 1003')[0]);
		self::assertSame('Café', (string) Db::row('SELECT topic_title FROM ' . Db::table('topic') . ' WHERE topic_id = 101')[0]);
		self::assertTrue($importer->verify()['ok'], implode(' ', $importer->verify()['errors']));
	}

	public function testALargeInstallationImportsInOneGo(): void
	{
		$posts = 43778;
		$perTopic = 9;
		$topics = (int) ceil($posts / $perTopic);
		Db::execute('CREATE TABLE ' . XOOPS_DB_PREFIX . '_bb_digits (d int NOT NULL)');
		Db::execute('INSERT INTO ' . XOOPS_DB_PREFIX . '_bb_digits VALUES (0),(1),(2),(3),(4),(5),(6),(7),(8),(9)');
		$numbers = '(SELECT a.d + b.d * 10 + c.d * 100 + e.d * 1000 + f.d * 10000 + 1 AS n FROM ' . XOOPS_DB_PREFIX . '_bb_digits a, ' . XOOPS_DB_PREFIX . '_bb_digits b, '
			. XOOPS_DB_PREFIX . '_bb_digits c, ' . XOOPS_DB_PREFIX . '_bb_digits e, ' . XOOPS_DB_PREFIX . '_bb_digits f) x';
		foreach (['posts', 'posts_text', 'topics', 'attachments', 'reads_topic', 'reads_forum'] as $table) {
			Db::execute('TRUNCATE TABLE ' . OldIforum::name($table));
		}
		Db::execute('INSERT INTO ' . OldIforum::name('topics') . " (topic_id, topic_title, topic_poster, topic_time, forum_id, topic_replies, topic_last_post_id) SELECT n, CONCAT('Topic ', n), 5, 1700000000 + n, 10 + n % 3, 8, 0 FROM $numbers WHERE n <= ?", [$topics]);
		Db::execute('INSERT INTO ' . OldIforum::name('posts') . " (post_id, pid, topic_id, forum_id, post_time, uid, subject, poster_ip) SELECT n, 0, FLOOR((n - 1) / $perTopic) + 1, 10 + FLOOR((n - 1) / $perTopic) % 3 + 1 - 1, 1700000000 + n, 5, CONCAT('Post ', n), 0 FROM $numbers WHERE n <= ?", [$posts]);
		Db::execute('INSERT INTO ' . OldIforum::name('posts_text') . " (post_id, post_text) SELECT n, CONCAT('Text of post ', n, REPEAT(' lorem ipsum', 20)) FROM $numbers WHERE n <= ?", [$posts]);
		Db::execute('UPDATE ' . OldIforum::name('posts') . ' SET forum_id = 10 + (topic_id % 3)');
		Db::execute('UPDATE ' . OldIforum::name('topics') . ' SET forum_id = 10 + (topic_id % 3)');
		Db::execute('INSERT INTO ' . OldIforum::name('reads_topic') . " (uid, read_time, read_item, post_id) SELECT 5, 1700000000, n, n FROM $numbers WHERE n <= ?", [$topics]);
		Db::execute('DROP TABLE ' . XOOPS_DB_PREFIX . '_bb_digits');

		$importer = $this->importer();
		$started = microtime(true);
		$report = $importer->run();
		$seconds = microtime(true) - $started;

		self::assertSame([], $report['errors']);
		self::assertSame($posts, self::rows('post'));
		self::assertSame($topics, self::rows('topic'));
		$verify = $importer->verify();
		self::assertSame([], $verify['errors']);
		self::assertLessThan(120, $seconds, 'the import of the largest installation takes seconds, not minutes');
	}
}
