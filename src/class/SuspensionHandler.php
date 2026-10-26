<?php
/**
 * Handler for suspensions
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_SuspensionHandler extends icms_ipf_Handler {

	public function __construct(icms_db_legacy_Database $db) {
		parent::__construct($db, 'suspension', 'suspension_id', 'description', '', IMFORUM_DIRNAME);
	}

	/**
	 * Whether a user or IP address is suspended in a forum right now
	 *
	 * A suspension with forum_id 0 applies to every forum. Guests are only
	 * matched on IP address; an invalid address is ignored.
	 */
	public function isSuspended(int $uid, string $ip, int $forumId, ?int $now = null): bool {
		$now ??= time();

		$who = new icms_db_criteria_Compo();
		if ($uid > 0) {
			$who->add(new icms_db_criteria_Item('uid', $uid), 'OR');
		}
		// criteria values are not escaped by the core, so only a valid address goes in
		if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
			$who->add(new icms_db_criteria_Item('ip', $ip), 'OR');
		}
		if (!$who->criteriaElements) {
			return false;
		}

		$where = new icms_db_criteria_Compo(new icms_db_criteria_Item('forum_id', '(0, ' . $forumId . ')', 'IN'));
		$where->add(new icms_db_criteria_Item('start_time', $now, '<='));
		$where->add(new icms_db_criteria_Item('end_time', $now, '>'));
		$where->add($who);

		return $this->getCount($where) > 0;
	}

	/**
	 * Remove suspensions that ended before a moment
	 */
	public function purgeEndedBefore(int $time): int {
		$count = $this->getCount(new icms_db_criteria_Item('end_time', $time, '<'));
		$this->deleteAll(new icms_db_criteria_Item('end_time', $time, '<'));
		return (int) $count;
	}

	protected function beforeInsert(mod_imforum_Suspension $suspension): bool {
		if ((int) $suspension->getVar('start_time', 'e') === 0) {
			$suspension->setVar('start_time', time());
		}
		return true;
	}
}
