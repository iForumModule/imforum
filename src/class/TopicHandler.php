<?php
/**
 * Handler for topics
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_TopicHandler extends icms_ipf_Handler {

	public function __construct(icms_db_legacy_Database $db) {
		parent::__construct($db, 'topic', 'topic_id', 'topic_title', '', IMFORUM_DIRNAME);
	}

	/**
	 * Recount the replies and the first and last post of a topic
	 *
	 * Only approved posts count; the first post is not a reply.
	 */
	public function synchronise(int $topicId): bool {
		$posts = $this->db->prefix(IMFORUM_DIRNAME . '_post');
		$result = $this->db->queryF("SELECT COUNT(*), MIN(post_id), MAX(post_id) FROM $posts WHERE topic_id = $topicId AND approved = 1");
		$row = $result ? $this->db->fetchRow($result) : false;
		[$count, $first, $last] = $row ? array_values($row) : [0, 0, 0];

		// queryF: the counters follow a write that has already been allowed
		return (bool) $this->db->queryF(sprintf(
			'UPDATE %s SET topic_replies = %d, topic_first_post_id = %d, topic_last_post_id = %d WHERE topic_id = %d',
			$this->table, max((int) $count - 1, 0), (int) $first, (int) $last, $topicId
		));
	}

	/**
	 * Recount every topic in one query; used after an import or a prune
	 */
	public function synchroniseAll(): bool {
		$posts = $this->db->prefix(IMFORUM_DIRNAME . '_post');
		return (bool) $this->db->queryF("UPDATE {$this->table} t"
			. " LEFT JOIN (SELECT topic_id, COUNT(*) AS n, MIN(post_id) AS first, MAX(post_id) AS last FROM $posts WHERE approved = 1 GROUP BY topic_id) p ON p.topic_id = t.topic_id"
			. ' SET t.topic_replies = GREATEST(COALESCE(p.n, 0) - 1, 0), t.topic_first_post_id = COALESCE(p.first, 0), t.topic_last_post_id = COALESCE(p.last, 0)');
	}

	/**
	 * Topics whose last post is older than a moment
	 *
	 * @param list<int> $forumIds
	 * @return list<int> topic ids
	 */
	public function findInactive(array $forumIds, int $before, bool $keepSticky = true, bool $keepDigest = true): array {
		$forumIds = array_values(array_filter(array_map('intval', $forumIds)));
		if (!$forumIds) {
			return [];
		}
		$posts = $this->db->prefix(IMFORUM_DIRNAME . '_post');
		$sql = "SELECT t.topic_id FROM {$this->table} t"
			. " LEFT JOIN $posts p ON p.post_id = t.topic_last_post_id"
			. ' WHERE t.forum_id IN (' . implode(',', $forumIds) . ')'
			. ' AND COALESCE(p.post_time, t.topic_time) < ' . $before
			. ($keepSticky ? ' AND t.topic_sticky = 0' : '')
			. ($keepDigest ? ' AND t.topic_digest = 0' : '');

		$ids = [];
		$result = $this->db->queryF($sql);
		while ($result && ($row = $this->db->fetchRow($result))) {
			$ids[] = (int) $row[0];
		}
		return $ids;
	}

	/**
	 * Move a topic and its posts to another forum, and recount both forums
	 */
	public function moveToForum(int $topicId, int $forumId): bool {
		$topic = $this->get($topicId);
		$forumHandler = icms_getModuleHandler('forum', IMFORUM_DIRNAME);
		if ($topic->isNew() || $forumHandler->get($forumId)->isNew()) {
			return false;
		}
		$oldForumId = (int) $topic->getVar('forum_id', 'e');
		if ($oldForumId === $forumId) {
			return true;
		}

		$posts = $this->db->prefix(IMFORUM_DIRNAME . '_post');
		$ok = (bool) $this->db->queryF("UPDATE $posts SET forum_id = $forumId WHERE topic_id = $topicId");
		$topic->setVar('forum_id', $forumId);
		$ok = $this->insert($topic, true) && $ok;
		return $forumHandler->synchronise($oldForumId) && $ok;
	}

	protected function beforeInsert(mod_imforum_Topic $topic): bool {
		if ((int) $topic->getVar('topic_time', 'e') === 0) {
			$topic->setVar('topic_time', time());
		}
		return true;
	}

	protected function afterSave(mod_imforum_Topic $topic): bool {
		return icms_getModuleHandler('forum', IMFORUM_DIRNAME)->synchronise((int) $topic->getVar('forum_id', 'e'));
	}

	/**
	 * Remove the posts, read marks and archive copies of a deleted topic
	 */
	protected function afterDelete(mod_imforum_Topic $topic): bool {
		$topicId = (int) $topic->id();
		$ok = icms_getModuleHandler('post', IMFORUM_DIRNAME)->deleteByTopic($topicId);
		icms_getModuleHandler('readmark', IMFORUM_DIRNAME)->deleteByItem(mod_imforum_ReadmarkHandler::TYPE_TOPIC, $topicId);
		icms_getModuleHandler('archive', IMFORUM_DIRNAME)->deleteAll(new icms_db_criteria_Item('topic_id', $topicId));
		return icms_getModuleHandler('forum', IMFORUM_DIRNAME)->synchronise((int) $topic->getVar('forum_id', 'e')) && $ok;
	}
}
