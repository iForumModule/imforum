<?php
/**
 * Handler for the who-is-online list
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_OnlineHandler extends icms_ipf_Handler {

	public function __construct(icms_db_legacy_Database $db) {
		parent::__construct($db, 'online', 'online_id', 'uname', '', IMFORUM_DIRNAME);
	}

	/**
	 * Note that a visitor is looking at a forum or topic
	 *
	 * Members are found again by user id, guests by address; every visitor has
	 * one row. Rows that were not updated for $minutes are removed.
	 */
	public function track(int $uid, string $uname, string $ip, int $forumId, int $topicId, int $minutes = 15): bool {
		$ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
		$criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('uid', max(0, $uid)));
		if ($uid < 1) {
			if ($ip === '') {
				return false;
			}
			$criteria->add(new icms_db_criteria_Item('ip', $ip));
		}
		$existing = $this->getObjects($criteria);
		$row = $existing[0] ?? $this->create();
		$row->setVar('uid', max(0, $uid));
		$row->setVar('uname', $uid > 0 ? $uname : '');
		$row->setVar('ip', $ip);
		$row->setVar('forum_id', $forumId);
		$row->setVar('topic_id', $topicId);
		$row->setVar('updated', time());
		$ok = $this->insert($row, true);
		$this->purgeOlderThan(time() - max(1, $minutes) * 60);
		return $ok;
	}

	/**
	 * Who is online, on the whole forum or on a forum or topic
	 *
	 * @return array{members: list<array{uid: int, uname: string}>, guests: int}
	 */
	public function getSnapshot(int $forumId = 0, int $topicId = 0, int $minutes = 15): array {
		$criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('updated', time() - max(1, $minutes) * 60, '>='));
		if ($topicId > 0) {
			$criteria->add(new icms_db_criteria_Item('topic_id', $topicId));
		} elseif ($forumId > 0) {
			$criteria->add(new icms_db_criteria_Item('forum_id', $forumId));
		}
		$criteria->setSort('uname');
		$criteria->setOrder('ASC');

		$members = [];
		$guests = 0;
		foreach ($this->getObjects($criteria) as $row) {
			$uid = (int) $row->getVar('uid', 'e');
			if ($uid > 0) {
				$members[] = ['uid' => $uid, 'uname' => (string) $row->getVar('uname')];
			} else {
				$guests++;
			}
		}
		return ['members' => $members, 'guests' => $guests];
	}

	/**
	 * Which of these members are online
	 *
	 * @param list<int> $uids
	 * @return array<int, true>
	 */
	public function getOnlineMembers(array $uids, int $minutes = 15): array {
		$uids = array_values(array_filter(array_map('intval', $uids)));
		if (!$uids) {
			return [];
		}
		$online = [];
		$criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('uid', '(' . implode(',', $uids) . ')', 'IN'));
		$criteria->add(new icms_db_criteria_Item('updated', time() - max(1, $minutes) * 60, '>='));
		foreach ($this->getObjects($criteria) as $row) {
			$online[(int) $row->getVar('uid', 'e')] = true;
		}
		return $online;
	}

	/**
	 * Drop rows that were not updated since a moment
	 */
	public function purgeOlderThan(int $time): bool {
		return mod_imforum_Db::ok("DELETE FROM {$this->table} WHERE updated < ?", [$time]);
	}
}
