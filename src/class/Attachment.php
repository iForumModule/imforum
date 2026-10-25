<?php
/**
 * A file attached to a post
 *
 * Replaces the base64-serialised list in bb_posts.attachment.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_Attachment extends icms_ipf_Object {

	public function __construct(mod_imforum_AttachmentHandler $handler) {
		parent::__construct($handler);

		$this->quickInitVar('attach_id', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('post_id', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('name_saved', XOBJ_DTYPE_TXTBOX, true);
		$this->quickInitVar('name_display', XOBJ_DTYPE_TXTBOX, true);
		$this->quickInitVar('mimetype', XOBJ_DTYPE_TXTBOX);
		$this->quickInitVar('filesize', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('downloads', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('online', XOBJ_DTYPE_INT, false, '', '', 1);
		$this->quickInitVar('attach_time', XOBJ_DTYPE_LTIME, false, '', '', 0);

		$this->setControl('online', ['name' => 'yesno']);
	}

	/**
	 * Absolute path of the stored file
	 *
	 * basename() keeps a tampered name_saved inside the upload folder.
	 */
	public function getFilePath(): string {
		return IMFORUM_UPLOAD_PATH . basename((string) $this->getVar('name_saved', 'e'));
	}
}
