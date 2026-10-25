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
	 * Drop rows that were not updated since a moment
	 */
	public function purgeOlderThan(int $time): bool {
		return (bool) $this->db->queryF(sprintf('DELETE FROM %s WHERE updated < %d', $this->table, $time));
	}
}
