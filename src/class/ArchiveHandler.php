<?php
/**
 * Handler for archived posts
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_ArchiveHandler extends icms_ipf_Handler {

	public function __construct(icms_db_legacy_Database $db) {
		parent::__construct($db, 'archive', 'archive_id', '', 'post_text', IMFORUM_DIRNAME);
	}

	protected function beforeInsert(mod_imforum_Archive $archive): bool {
		if ((int) $archive->getVar('archived_time', 'e') === 0) {
			$archive->setVar('archived_time', time());
		}
		return true;
	}
}
