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
		$posts = mod_imforum_Db::table('post');
		$row = mod_imforum_Db::row("SELECT COUNT(*), MIN(post_id), MAX(post_id) FROM $posts WHERE topic_id = ? AND approved = 1", [$topicId]);
		[$count, $first, $last] = $row ?: [0, 0, 0];

		return mod_imforum_Db::ok(
			"UPDATE {$this->table} SET topic_replies = ?, topic_first_post_id = ?, topic_last_post_id = ? WHERE topic_id = ?",
			[max((int) $count - 1, 0), (int) $first, (int) $last, $topicId]
		);
	}

	/**
	 * Recount every topic in one query; used after an import or a prune
	 */
	public function synchroniseAll(): bool {
		$posts = mod_imforum_Db::table('post');
		return mod_imforum_Db::ok("UPDATE {$this->table} t"
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
		$posts = mod_imforum_Db::table('post');
		$sql = "SELECT t.topic_id FROM {$this->table} t"
			. " LEFT JOIN $posts p ON p.post_id = t.topic_last_post_id"
			. ' WHERE t.forum_id IN (' . mod_imforum_Db::marks($forumIds) . ')'
			. ' AND COALESCE(p.post_time, t.topic_time) < ?'
			. ($keepSticky ? ' AND t.topic_sticky = 0' : '')
			. ($keepDigest ? ' AND t.topic_digest = 0' : '');

		return array_map(static fn (array $row): int => (int) $row[0], mod_imforum_Db::rows($sql, [...$forumIds, $before]));
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

		$ok = mod_imforum_Db::ok('UPDATE ' . mod_imforum_Db::table('post') . ' SET forum_id = ? WHERE topic_id = ?', [$forumId, $topicId]);
		$topic->setVar('forum_id', $forumId);
		$ok = $this->insert($topic, true) && $ok;
		return $forumHandler->synchronise($oldForumId) && $ok;
	}

	/**
	 * One page of the approved topics of a forum: sticky topics first, then by last post
	 *
	 * @return array<int, mod_imforum_Topic> by topic id
	 */
	public function getForumTopics(int $forumId, int $start, int $limit): array {
		$criteria = $this->approvedInForum($forumId);
		$criteria->setSort('topic_sticky DESC, topic_last_post_id');
		$criteria->setOrder('DESC');
		$criteria->setStart($start);
		$criteria->setLimit($limit);
		return $this->getObjects($criteria, true);
	}

	public function countForumTopics(int $forumId): int {
		return (int) $this->getCount($this->approvedInForum($forumId));
	}

	private function approvedInForum(int $forumId): icms_db_criteria_Compo {
		$criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('forum_id', $forumId));
		$criteria->add(new icms_db_criteria_Item('approved', 1));
		return $criteria;
	}

	/**
	 * Topics by id, in the order of the ids; unknown ids are left out
	 *
	 * @param list<int> $topicIds
	 * @return array<int, mod_imforum_Topic>
	 */
	public function getByIds(array $topicIds): array {
		$topicIds = array_values(array_filter(array_map('intval', $topicIds)));
		if (!$topicIds) {
			return [];
		}
		$found = $this->getObjects(new icms_db_criteria_Item('topic_id', '(' . implode(',', $topicIds) . ')', 'IN'), true);
		$ordered = [];
		foreach ($topicIds as $topicId) {
			if (isset($found[$topicId])) {
				$ordered[$topicId] = $found[$topicId];
			}
		}
		return $ordered;
	}

	/**
	 * Topics over several forums, newest activity first
	 *
	 * @param list<int> $forumIds the forums to look in; already limited to what the reader may open
	 * @param string $filter '' for all, 'unread' for what the member has not read, 'digest' for featured topics
	 * @return array{ids: list<int>, total: int}
	 */
	public function findRecent(array $forumIds, int $start, int $limit, string $filter = '', int $uid = 0): array {
		$forumIds = array_values(array_filter(array_map('intval', $forumIds)));
		if (!$forumIds) {
			return ['ids' => [], 'total' => 0];
		}
		$joins = '';
		$joinParams = [];
		$where = ' WHERE t.approved = 1 AND t.forum_id IN (' . mod_imforum_Db::marks($forumIds) . ')';
		if ($filter === 'digest') {
			$where .= ' AND t.topic_digest = 1';
		} elseif ($filter === 'unread') {
			if ($uid < 1) {
				return ['ids' => [], 'total' => 0];
			}
			$unread = icms_getModuleHandler('readmark', IMFORUM_DIRNAME)->getUnreadSql($uid);
			$joins = $unread['joins'];
			$joinParams = $unread['params'];
			$where .= $unread['where'];
		}

		// the placeholders of the joins come first in the statement
		$params = [...$joinParams, ...$forumIds];
		$total = mod_imforum_Db::value("SELECT COUNT(*) FROM {$this->table} t$joins$where", $params);
		$ids = array_map(
			static fn (array $row): int => (int) $row[0],
			mod_imforum_Db::rows("SELECT t.topic_id FROM {$this->table} t$joins$where ORDER BY t.topic_last_post_id DESC LIMIT ?, ?", [...$params, max(0, $start), max(1, $limit)])
		);
		return ['ids' => $ids, 'total' => $total];
	}

	/**
	 * The topics with the most replies among those that saw a new post since a moment
	 *
	 * @param list<int> $forumIds the forums the reader may open
	 * @return list<int> topic ids, busiest first
	 */
	public function findHot(array $forumIds, int $since, int $limit): array {
		$forumIds = array_values(array_filter(array_map('intval', $forumIds)));
		if (!$forumIds) {
			return [];
		}
		$posts = mod_imforum_Db::table('post');
		return array_map(static fn (array $row): int => (int) $row[0], mod_imforum_Db::rows(
			"SELECT t.topic_id FROM {$this->table} t JOIN $posts p ON p.post_id = t.topic_last_post_id"
			. ' WHERE t.approved = 1 AND t.topic_replies > 0 AND t.forum_id IN (' . mod_imforum_Db::marks($forumIds) . ') AND p.post_time >= ?'
			. ' ORDER BY t.topic_replies DESC, t.topic_last_post_id DESC LIMIT ?',
			[...$forumIds, max(0, $since), max(1, $limit)]
		));
	}

	/**
	 * Count a view
	 */
	public function incrementViews(int $topicId): bool {
		return mod_imforum_Db::ok("UPDATE {$this->table} SET topic_views = topic_views + 1 WHERE topic_id = ?", [$topicId]);
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
