<?php
/**
 * Handler for forum moderators
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_ModeratorHandler extends icms_ipf_Handler {

	public function __construct(icms_db_legacy_Database $db) {
		parent::__construct($db, 'moderator', 'moderator_id', 'uid', '', IMFORUM_DIRNAME);
	}

	/**
	 * @return list<int> user ids of the moderators of a forum
	 */
	public function getModeratorIds(int $forumId): array {
		$ids = [];
		foreach ($this->getObjects(new icms_db_criteria_Item('forum_id', $forumId)) as $moderator) {
			$ids[] = (int) $moderator->getVar('uid', 'e');
		}
		return $ids;
	}

	public function isModerator(int $uid, int $forumId): bool {
		if ($uid < 1) {
			return false;
		}
		$criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('forum_id', $forumId));
		$criteria->add(new icms_db_criteria_Item('uid', $uid));
		return $this->getCount($criteria) > 0;
	}

	/**
	 * Make exactly these users the moderators of a forum
	 *
	 * @param list<int> $uids
	 */
	public function setModerators(int $forumId, array $uids): bool {
		$wanted = array_values(array_unique(array_filter(array_map('intval', $uids), static fn (int $uid): bool => $uid > 0)));
		$current = $this->getModeratorIds($forumId);

		$ok = true;
		foreach (array_diff($current, $wanted) as $uid) {
			$criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('forum_id', $forumId));
			$criteria->add(new icms_db_criteria_Item('uid', $uid));
			$ok = $this->deleteAll($criteria) !== false && $ok;
		}
		foreach (array_diff($wanted, $current) as $uid) {
			$moderator = $this->create();
			$moderator->setVar('forum_id', $forumId);
			$moderator->setVar('uid', $uid);
			$ok = $this->insert($moderator, true) && $ok;
		}
		return $ok;
	}
}
