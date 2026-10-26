<?php
/**
 * Handler for read marks
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_ReadmarkHandler extends icms_ipf_Handler {

	public const TYPE_FORUM = 1;
	public const TYPE_TOPIC = 2;

	public function __construct(icms_db_legacy_Database $db) {
		parent::__construct($db, 'readmark', 'read_id', '', '', IMFORUM_DIRNAME);
	}

	/**
	 * Remember that a member has read up to a post
	 */
	public function markRead(int $type, int $uid, int $itemId, int $postId, ?int $time = null): bool {
		if ($uid < 1) {
			return false;
		}
		$existing = $this->getObjects($this->criteriaFor($type, $uid, $itemId));
		$read = $existing[0] ?? $this->create();
		$read->setVar('read_type', $type);
		$read->setVar('uid', $uid);
		$read->setVar('item_id', $itemId);
		$read->setVar('post_id', $postId);
		$read->setVar('read_time', $time ?? time());
		return $this->insert($read, true);
	}

	/**
	 * Last read post per item for one member
	 *
	 * @param list<int> $itemIds
	 * @return array<int, int> item id => post id; unread items are left out
	 */
	public function getLastRead(int $type, int $uid, array $itemIds): array {
		$itemIds = array_values(array_filter(array_map('intval', $itemIds)));
		if ($uid < 1 || !$itemIds) {
			return [];
		}
		$criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('read_type', $type));
		$criteria->add(new icms_db_criteria_Item('uid', $uid));
		$criteria->add(new icms_db_criteria_Item('item_id', '(' . implode(',', $itemIds) . ')', 'IN'));

		$ret = [];
		foreach ($this->getObjects($criteria) as $read) {
			$ret[(int) $read->getVar('item_id', 'e')] = (int) $read->getVar('post_id', 'e');
		}
		return $ret;
	}

	/**
	 * Forget all read marks for a forum or topic that is deleted
	 */
	public function deleteByItem(int $type, int $itemId): bool {
		return mod_imforum_Db::ok("DELETE FROM {$this->table} WHERE read_type = ? AND item_id = ?", [$type, $itemId]);
	}

	/**
	 * Drop read marks older than a moment; used by the cleanup task (phase 6)
	 *
	 * A topic without a mark counts as unread, so deleting marks by age alone
	 * would turn every old topic into an unread one. Posts older than the
	 * moment are therefore counted as read first: a member who has marks in a
	 * forum gets a forum mark up to the newest post from before the moment.
	 * Then the old marks go, together with the marks of topics that no longer
	 * exist and the topic marks the forum mark already covers.
	 *
	 * @return int the number of topic marks removed
	 */
	public function purgeOlderThan(int $time): int {
		$posts = mod_imforum_Db::table('post');
		$topics = mod_imforum_Db::table('topic');
		$topicType = self::TYPE_TOPIC;
		$forumType = self::TYPE_FORUM;

		$newestOld = [];
		foreach (mod_imforum_Db::rows("SELECT forum_id, MAX(post_id) FROM $posts WHERE approved = 1 AND post_time < ? GROUP BY forum_id", [$time]) as $row) {
			$newestOld[(int) $row[0]] = (int) $row[1];
		}

		$pairs = [];
		foreach (mod_imforum_Db::rows("SELECT DISTINCT m.uid, t.forum_id FROM {$this->table} m JOIN $topics t ON t.topic_id = m.item_id WHERE m.read_type = ? AND m.read_time < ?", [$topicType, $time]) as $row) {
			$pairs[] = [(int) $row[0], (int) $row[1]];
		}
		foreach ($pairs as [$uid, $forumId]) {
			$upTo = $newestOld[$forumId] ?? 0;
			$seen = $this->getLastRead($forumType, $uid, [$forumId])[$forumId] ?? 0;
			if ($upTo > $seen) {
				$this->markRead($forumType, $uid, $forumId, $upTo);
			}
		}

		$removed = 0;
		foreach ([
			["DELETE FROM {$this->table} WHERE read_type = ? AND read_time < ?", [$topicType, $time]],
			["DELETE m FROM {$this->table} m LEFT JOIN $topics t ON t.topic_id = m.item_id WHERE m.read_type = ? AND t.topic_id IS NULL", [$topicType]],
			["DELETE m FROM {$this->table} m JOIN $topics t ON t.topic_id = m.item_id JOIN {$this->table} f ON f.read_type = ? AND f.uid = m.uid AND f.item_id = t.forum_id WHERE m.read_type = ? AND m.post_id <= f.post_id", [$forumType, $topicType]],
		] as [$sql, $params]) {
			$removed += mod_imforum_Db::execute($sql, $params) ?? 0;
		}
		return $removed;
	}

	/**
	 * Drop a member's marks on the topics of a forum, once the forum itself is marked
	 */
	public function deleteTopicMarksInForum(int $uid, int $forumId): bool {
		return mod_imforum_Db::ok(
			"DELETE FROM {$this->table} WHERE read_type = ? AND uid = ? AND item_id IN (SELECT topic_id FROM " . mod_imforum_Db::table('topic') . ' WHERE forum_id = ?)',
			[self::TYPE_TOPIC, $uid, $forumId]
		);
	}

	/**
	 * SQL pieces that select the topics a member has not read to the end
	 *
	 * A topic is unread when its last post is newer than both the member's
	 * mark on the topic and the mark on its forum ("mark all as read"). The
	 * topic table must be aliased t.
	 *
	 * @return array{joins: string, where: string, params: list<int>} the values for the placeholders of the joins, in order
	 */
	public function getUnreadSql(int $uid): array {
		$read = $this->table;
		return [
			'joins' => " LEFT JOIN $read rt ON rt.read_type = ? AND rt.uid = ? AND rt.item_id = t.topic_id"
				. " LEFT JOIN $read rf ON rf.read_type = ? AND rf.uid = ? AND rf.item_id = t.forum_id",
			'where' => ' AND t.topic_last_post_id > GREATEST(COALESCE(rt.post_id, 0), COALESCE(rf.post_id, 0))',
			'params' => [self::TYPE_TOPIC, $uid, self::TYPE_FORUM, $uid],
		];
	}

	/**
	 * Unread topics per forum
	 *
	 * @param list<int> $forumIds
	 * @return array<int, int> forum id => unread topics; forums without any are left out
	 */
	public function countUnreadByForum(int $uid, array $forumIds): array {
		$forumIds = array_values(array_filter(array_map('intval', $forumIds)));
		if ($uid < 1 || !$forumIds) {
			return [];
		}
		$unread = $this->getUnreadSql($uid);
		$sql = 'SELECT t.forum_id, COUNT(*) FROM ' . mod_imforum_Db::table('topic') . ' t' . $unread['joins']
			. ' WHERE t.approved = 1 AND t.forum_id IN (' . mod_imforum_Db::marks($forumIds) . ')' . $unread['where'] . ' GROUP BY t.forum_id';
		$counts = [];
		foreach (mod_imforum_Db::rows($sql, [...$unread['params'], ...$forumIds]) as $row) {
			$counts[(int) $row[0]] = (int) $row[1];
		}
		return $counts;
	}

	private function criteriaFor(int $type, int $uid, int $itemId): icms_db_criteria_Compo {
		$criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('read_type', $type));
		$criteria->add(new icms_db_criteria_Item('uid', $uid));
		$criteria->add(new icms_db_criteria_Item('item_id', $itemId));
		return $criteria;
	}
}
