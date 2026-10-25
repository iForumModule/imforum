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
		return (bool) $this->db->queryF(sprintf(
			'DELETE FROM %s WHERE read_type = %d AND item_id = %d',
			$this->table, $type, $itemId
		));
	}

	/**
	 * Drop read marks older than a moment; used by the cleanup task (phase 6)
	 */
	public function purgeOlderThan(int $time): bool {
		return (bool) $this->db->queryF(sprintf('DELETE FROM %s WHERE read_time < %d', $this->table, $time));
	}

	/**
	 * Drop a member's marks on the topics of a forum, once the forum itself is marked
	 */
	public function deleteTopicMarksInForum(int $uid, int $forumId): bool {
		$topics = $this->db->prefix(IMFORUM_DIRNAME . '_topic');
		return (bool) $this->db->queryF(sprintf(
			'DELETE FROM %s WHERE read_type = %d AND uid = %d AND item_id IN (SELECT topic_id FROM %s WHERE forum_id = %d)',
			$this->table, self::TYPE_TOPIC, $uid, $topics, $forumId
		));
	}

	/**
	 * SQL pieces that select the topics a member has not read to the end
	 *
	 * A topic is unread when its last post is newer than both the member's
	 * mark on the topic and the mark on its forum ("mark all as read"). The
	 * topic table must be aliased t.
	 *
	 * @return array{joins: string, where: string}
	 */
	public function getUnreadSql(int $uid): array {
		$read = $this->table;
		return [
			'joins' => " LEFT JOIN $read rt ON rt.read_type = " . self::TYPE_TOPIC . " AND rt.uid = $uid AND rt.item_id = t.topic_id"
				. " LEFT JOIN $read rf ON rf.read_type = " . self::TYPE_FORUM . " AND rf.uid = $uid AND rf.item_id = t.forum_id",
			'where' => ' AND t.topic_last_post_id > GREATEST(COALESCE(rt.post_id, 0), COALESCE(rf.post_id, 0))',
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
		$sql = 'SELECT t.forum_id, COUNT(*) FROM ' . $this->db->prefix(IMFORUM_DIRNAME . '_topic') . ' t' . $unread['joins']
			. ' WHERE t.approved = 1 AND t.forum_id IN (' . implode(',', $forumIds) . ')' . $unread['where'] . ' GROUP BY t.forum_id';
		$counts = [];
		$result = $this->db->queryF($sql);
		while ($result && ($row = $this->db->fetchRow($result))) {
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
