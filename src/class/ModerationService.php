<?php
/**
 * Moderation: lock, pin, feature, move, merge, split, approve, report and suspend
 *
 * All rules live here and not in moderate.php, so they can be tested without
 * a request. Every action checks that the visitor moderates the forums
 * involved. The counters of topics and forums are recounted after each
 * change instead of adjusted, so a merge or split cannot leave them wrong.
 * Messages are language constants, ready for output.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_ModerationService {

	public const MAX_DAYS = 3650;

	private mod_imforum_PermissionService $permissions;

	private ?mod_imforum_Notifier $notifier;

	public function __construct(mod_imforum_PermissionService $permissions, ?mod_imforum_Notifier $notifier = null) {
		$this->permissions = $permissions;
		$this->notifier = $notifier;
	}

	/**
	 * @return array{ok: bool, errors: list<string>}
	 */
	private static function result(bool $ok, string $error = ''): array {
		return ['ok' => $ok, 'errors' => $ok || $error === '' ? [] : [$error]];
	}

	/**
	 * A write that has already been allowed
	 */
	private function run(string $sql): bool {
		return (bool) icms::$xoopsDB->queryF($sql);
	}

	private function mayModerate(int $forumId): bool {
		$forum = $this->permissions->getForum($forumId);
		return $forum !== null && $this->permissions->canModerate($forum);
	}

	private static function topics(): mod_imforum_TopicHandler {
		return icms_getModuleHandler('topic', IMFORUM_DIRNAME);
	}

	private static function posts(): mod_imforum_PostHandler {
		return icms_getModuleHandler('post', IMFORUM_DIRNAME);
	}

	private static function forums(): mod_imforum_ForumHandler {
		return icms_getModuleHandler('forum', IMFORUM_DIRNAME);
	}

	/**
	 * Close a topic to replies from members, or open it again
	 *
	 * @return array{ok: bool, errors: list<string>}
	 */
	public function setLocked(mod_imforum_Topic $topic, bool $locked): array {
		return $this->setFlag($topic, 'topic_status', $locked);
	}

	/**
	 * Keep a topic at the top of its forum, or let it sink again
	 *
	 * @return array{ok: bool, errors: list<string>}
	 */
	public function setSticky(mod_imforum_Topic $topic, bool $sticky): array {
		return $this->setFlag($topic, 'topic_sticky', $sticky);
	}

	/**
	 * Feature a topic in the digest list, or take it out
	 *
	 * @return array{ok: bool, errors: list<string>}
	 */
	public function setDigest(mod_imforum_Topic $topic, bool $digest): array {
		return $this->setFlag($topic, 'topic_digest', $digest);
	}

	/**
	 * @return array{ok: bool, errors: list<string>}
	 */
	private function setFlag(mod_imforum_Topic $topic, string $field, bool $on): array {
		if (!$this->mayModerate((int) $topic->getVar('forum_id', 'e'))) {
			return self::result(false, _NOPERM);
		}
		$topic->setVar($field, $on ? 1 : 0);
		if ($field === 'topic_digest') {
			$topic->setVar('digest_time', $on ? time() : 0);
		}
		return self::result(self::topics()->insert($topic, true), _MD_IMFORUM_ERR_SAVE_FAILED);
	}

	/**
	 * Move a topic to another forum; the moderator must moderate both
	 *
	 * @return array{ok: bool, errors: list<string>}
	 */
	public function move(mod_imforum_Topic $topic, int $targetForumId): array {
		$fromId = (int) $topic->getVar('forum_id', 'e');
		if (!$this->mayModerate($fromId) || !$this->mayModerate($targetForumId)) {
			return self::result(false, _NOPERM);
		}
		if ($fromId === $targetForumId) {
			return self::result(false, _MD_IMFORUM_MOD_ERR_SAME_FORUM);
		}
		return self::result(self::topics()->moveToForum((int) $topic->id(), $targetForumId), _MD_IMFORUM_ERR_SAVE_FAILED);
	}

	/**
	 * Add all posts of a topic to another topic and remove the empty one
	 *
	 * The target keeps its title. The views are added up.
	 *
	 * @return array{ok: bool, errors: list<string>, topic: ?mod_imforum_Topic}
	 */
	public function merge(mod_imforum_Topic $source, mod_imforum_Topic $target): array {
		$fail = static fn (string $error): array => ['ok' => false, 'errors' => [$error], 'topic' => null];
		if ($source->isNew() || $target->isNew()) {
			return $fail(_MD_IMFORUM_MOD_ERR_NO_TOPIC);
		}
		$sourceId = (int) $source->id();
		$targetId = (int) $target->id();
		$sourceForum = (int) $source->getVar('forum_id', 'e');
		$targetForum = (int) $target->getVar('forum_id', 'e');
		if (!$this->mayModerate($sourceForum) || !$this->mayModerate($targetForum)) {
			return $fail(_NOPERM);
		}
		if ($sourceId === $targetId) {
			return $fail(_MD_IMFORUM_MOD_ERR_SAME_TOPIC);
		}

		$db = icms::$xoopsDB;
		$posts = $db->prefix(IMFORUM_DIRNAME . '_post');
		$topicTable = $db->prefix(IMFORUM_DIRNAME . '_topic');
		$ok = $this->run("UPDATE $posts SET topic_id = $targetId, forum_id = $targetForum WHERE topic_id = $sourceId");
		$views = (int) $source->getVar('topic_views', 'e');
		$ok = $this->run("UPDATE $topicTable SET topic_views = topic_views + $views WHERE topic_id = $targetId") && $ok;
		$ok = $this->run("DELETE FROM $topicTable WHERE topic_id = $sourceId") && $ok;
		icms_getModuleHandler('readmark', IMFORUM_DIRNAME)->deleteByItem(mod_imforum_ReadmarkHandler::TYPE_TOPIC, $sourceId);

		$ok = self::topics()->synchronise($targetId) && $ok;
		$ok = self::forums()->synchronise($sourceForum) && $ok;
		if ($targetForum !== $sourceForum) {
			$ok = self::forums()->synchronise($targetForum) && $ok;
		}
		return ['ok' => $ok, 'errors' => $ok ? [] : [_MD_IMFORUM_ERR_SAVE_FAILED], 'topic' => self::topics()->get($targetId)];
	}

	/**
	 * Move a post and every later post of the topic to a new topic
	 *
	 * Replies that pointed at a post that stays behind now answer the new
	 * first post, so the tree view stays whole.
	 *
	 * @return array{ok: bool, errors: list<string>, topic: ?mod_imforum_Topic}
	 */
	public function split(mod_imforum_Post $post, string $title): array {
		$fail = static fn (string $error): array => ['ok' => false, 'errors' => [$error], 'topic' => null];
		$oldTopic = self::topics()->get((int) $post->getVar('topic_id', 'e'));
		if ($post->isNew() || $oldTopic->isNew()) {
			return $fail(_MD_IMFORUM_MOD_ERR_NO_POST);
		}
		$forumId = (int) $oldTopic->getVar('forum_id', 'e');
		if (!$this->mayModerate($forumId)) {
			return $fail(_NOPERM);
		}
		$title = trim($title);
		if ($title === '' || mb_strlen($title) > mod_imforum_PostService::MAX_SUBJECT) {
			return $fail(sprintf(_MD_IMFORUM_ERR_SUBJECT_LONG, mod_imforum_PostService::MAX_SUBJECT));
		}
		$postId = (int) $post->id();
		$oldId = (int) $oldTopic->id();

		$db = icms::$xoopsDB;
		$posts = $db->prefix(IMFORUM_DIRNAME . '_post');
		$result = $db->queryF("SELECT MIN(post_id) FROM $posts WHERE topic_id = $oldId");
		$row = $result ? $db->fetchRow($result) : false;
		if ($row && (int) $row[0] === $postId) {
			return $fail(_MD_IMFORUM_MOD_ERR_SPLIT_FIRST);
		}

		$topic = self::topics()->create();
		$topic->setVar('forum_id', $forumId);
		$topic->setVar('topic_title', $title);
		$topic->setVar('topic_poster', (int) $post->getVar('uid', 'e'));
		$topic->setVar('poster_name', (string) $post->getVar('poster_name', 'n'));
		$topic->setVar('topic_time', (int) $post->getVar('post_time', 'e'));
		$topic->setVar('approved', (int) $post->getVar('approved', 'e'));
		if (!self::topics()->insert($topic, true)) {
			return $fail(_MD_IMFORUM_ERR_SAVE_FAILED);
		}
		$newId = (int) $topic->id();

		$ok = $this->run("UPDATE $posts SET topic_id = $newId WHERE topic_id = $oldId AND post_id >= $postId");
		$ok = $this->run("UPDATE $posts SET pid = 0 WHERE post_id = $postId") && $ok;
		$ok = $this->run("UPDATE $posts SET pid = $postId WHERE topic_id = $newId AND post_id > $postId AND pid < $postId") && $ok;
		$ok = self::topics()->synchronise($oldId) && $ok;
		$ok = self::topics()->synchronise($newId) && $ok;
		$ok = self::forums()->synchronise($forumId) && $ok;
		return ['ok' => $ok, 'errors' => $ok ? [] : [_MD_IMFORUM_ERR_SAVE_FAILED], 'topic' => self::topics()->get($newId)];
	}

	/**
	 * Delete a topic with all its posts
	 *
	 * @return array{ok: bool, errors: list<string>}
	 */
	public function deleteTopic(mod_imforum_Topic $topic): array {
		if (!$this->mayModerate((int) $topic->getVar('forum_id', 'e'))) {
			return self::result(false, _NOPERM);
		}
		return self::result(self::topics()->delete($topic, true), _MD_IMFORUM_ERR_SAVE_FAILED);
	}

	/**
	 * Let a waiting post appear, and the topic with it when the post starts it
	 *
	 * Subscribers are told only now, as they were not told when the post was written.
	 *
	 * @return array{ok: bool, errors: list<string>}
	 */
	public function approve(mod_imforum_Post $post): array {
		$topic = self::topics()->get((int) $post->getVar('topic_id', 'e'));
		$forum = $this->permissions->getForum((int) $post->getVar('forum_id', 'e'));
		if ($post->isNew() || $topic->isNew() || $forum === null || !$this->permissions->canModerate($forum)) {
			return self::result(false, _NOPERM);
		}
		if ((int) $post->getVar('approved', 'e') === 1) {
			return self::result(true);
		}

		$post->setVar('approved', 1);
		if (!self::posts()->insert($post, true)) {
			return self::result(false, _MD_IMFORUM_ERR_SAVE_FAILED);
		}
		$newTopic = false;
		if ((int) $topic->getVar('approved', 'e') === 0) {
			$firstId = $this->firstPostId((int) $topic->id());
			if ($firstId === (int) $post->id()) {
				$topic->setVar('approved', 1);
				self::topics()->insert($topic, true);
				$newTopic = true;
			}
		}
		$topic = self::topics()->get((int) $topic->id());
		// a reply in a topic that still waits stays invisible, so nobody is told
		if ((int) $topic->getVar('approved', 'e') === 1) {
			($this->notifier ?? new mod_imforum_Notifier())->newPost($post, $topic, $forum, $newTopic);
		}
		return self::result(true);
	}

	private function firstPostId(int $topicId): int {
		$db = icms::$xoopsDB;
		$result = $db->queryF('SELECT MIN(post_id) FROM ' . $db->prefix(IMFORUM_DIRNAME . '_post') . " WHERE topic_id = $topicId");
		$row = $result ? $db->fetchRow($result) : false;
		return $row ? (int) $row[0] : 0;
	}

	/**
	 * A member tells the moderators about a post
	 *
	 * @return array{ok: bool, errors: list<string>}
	 */
	public function report(mod_imforum_Post $post, string $text): array {
		$forum = $this->permissions->getForum((int) $post->getVar('forum_id', 'e'));
		if ($post->isNew() || $forum === null || !$this->permissions->canReport($forum)) {
			return self::result(false, _NOPERM);
		}
		$text = trim($text);
		if ($text === '') {
			return self::result(false, _MD_IMFORUM_REPORT_ERR_TEXT);
		}
		$uid = $this->permissions->getUid();
		$handler = icms_getModuleHandler('report', IMFORUM_DIRNAME);
		$open = new icms_db_criteria_Compo(new icms_db_criteria_Item('post_id', (int) $post->id()));
		$open->add(new icms_db_criteria_Item('reporter_uid', $uid));
		$open->add(new icms_db_criteria_Item('report_result', mod_imforum_Report::RESULT_OPEN));
		if ($handler->getCount($open) > 0) {
			return self::result(false, _MD_IMFORUM_REPORT_ERR_TWICE);
		}

		$report = $handler->create();
		$report->setVar('post_id', (int) $post->id());
		$report->setVar('reporter_uid', $uid);
		$report->setVar('reporter_ip', mod_imforum_View::clientIp());
		$report->setVar('report_text', $text);
		return self::result($handler->insert($report, true), _MD_IMFORUM_ERR_SAVE_FAILED);
	}

	/**
	 * Close a report with a note for the other moderators
	 *
	 * @return array{ok: bool, errors: list<string>}
	 */
	public function handleReport(mod_imforum_Report $report, string $memo): array {
		$post = self::posts()->get((int) $report->getVar('post_id', 'e'));
		$forumId = $post->isNew() ? 0 : (int) $post->getVar('forum_id', 'e');
		// a report whose post is gone can be closed by any moderator or webmaster
		$allowed = $post->isNew() ? $this->permissions->isWebmaster() || $this->moderatesAny() : $this->mayModerate($forumId);
		if (!$allowed) {
			return self::result(false, _NOPERM);
		}
		$report->setVar('report_result', mod_imforum_Report::RESULT_HANDLED);
		$report->setVar('report_memo', trim($memo));
		return self::result(icms_getModuleHandler('report', IMFORUM_DIRNAME)->insert($report, true), _MD_IMFORUM_ERR_SAVE_FAILED);
	}

	private function moderatesAny(): bool {
		foreach ($this->permissions->getAllForums() as $forumId => $forum) {
			if ($this->permissions->isModerator((int) $forumId)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Stop a member from writing in one forum for some days
	 *
	 * Reading stays possible. Moderators and webmasters cannot be suspended.
	 *
	 * @return array{ok: bool, errors: list<string>}
	 */
	public function suspend(mod_imforum_Forum $forum, int $uid, int $days, string $description): array {
		if (!$this->permissions->canModerate($forum)) {
			return self::result(false, _NOPERM);
		}
		if ($uid < 1 || $uid === $this->permissions->getUid()) {
			return self::result(false, _MD_IMFORUM_MOD_ERR_SUSPEND_WHO);
		}
		if ($days < 1 || $days > self::MAX_DAYS) {
			return self::result(false, sprintf(_MD_IMFORUM_MOD_ERR_SUSPEND_DAYS, self::MAX_DAYS));
		}
		$target = new mod_imforum_PermissionService(array_map('intval', (array) icms::handler('icms_member')->getGroupsByUser($uid)), $uid);
		if ($target->isModerator((int) $forum->id()) || in_array((int) ICMS_GROUP_ADMIN, $target->getGroups(), true)) {
			return self::result(false, _MD_IMFORUM_MOD_ERR_SUSPEND_MOD);
		}

		$handler = icms_getModuleHandler('suspension', IMFORUM_DIRNAME);
		$suspension = $handler->create();
		$suspension->setVar('uid', $uid);
		$suspension->setVar('forum_id', (int) $forum->id());
		$suspension->setVar('start_time', time());
		$suspension->setVar('end_time', time() + $days * 86400);
		$suspension->setVar('description', trim($description));
		return self::result($handler->insert($suspension, true), _MD_IMFORUM_ERR_SAVE_FAILED);
	}
}
