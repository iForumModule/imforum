<?php
/**
 * Handler for attachments
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_AttachmentHandler extends icms_ipf_Handler {

	public function __construct(icms_db_legacy_Database $db) {
		parent::__construct($db, 'attachment', 'attach_id', 'name_display', '', IMFORUM_DIRNAME);
	}

	protected function beforeInsert(mod_imforum_Attachment $attachment): bool {
		if ((int) $attachment->getVar('attach_time', 'e') === 0) {
			$attachment->setVar('attach_time', time());
		}
		return true;
	}

	/**
	 * Remove the file together with its record
	 *
	 * A file that is already gone is not an error.
	 */
	protected function afterDelete(mod_imforum_Attachment $attachment): bool {
		$path = $attachment->getFilePath();
		return !is_file($path) || unlink($path);
	}
}
