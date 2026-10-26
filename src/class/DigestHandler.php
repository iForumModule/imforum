<?php
/**
 * Handler for digests
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_DigestHandler extends icms_ipf_Handler {

	public function __construct(icms_db_legacy_Database $db) {
		parent::__construct($db, 'digest', 'digest_id', 'digest_time', 'digest_content', IMFORUM_DIRNAME);
	}

	/**
	 * Time of the most recent digest, 0 if none was sent yet
	 */
	public function getLastDigestTime(): int {
		return mod_imforum_Db::value("SELECT MAX(digest_time) FROM {$this->table}");
	}

	protected function beforeInsert(mod_imforum_Digest $digest): bool {
		if ((int) $digest->getVar('digest_time', 'e') === 0) {
			$digest->setVar('digest_time', time());
		}
		return true;
	}
}
