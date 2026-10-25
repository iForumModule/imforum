<?php
/**
 * A digest: the list of featured topics sent by mail
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_Digest extends icms_ipf_Object {

	public function __construct(mod_imforum_DigestHandler $handler) {
		parent::__construct($handler);

		$this->quickInitVar('digest_id', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('digest_time', XOBJ_DTYPE_LTIME, false, '', '', 0);
		$this->quickInitVar('digest_content', XOBJ_DTYPE_TXTAREA);
	}

	/**
	 * First words of the digest, for the admin list
	 */
	public function getExcerpt(): string {
		return htmlspecialchars(icms_core_DataFilter::icms_substr(strip_tags((string) $this->getVar('digest_content', 'n')), 0, 120), ENT_QUOTES);
	}

	/**
	 * Admin link to read the whole digest
	 */
	public function getViewLink(): string {
		return '<a href="' . IMFORUM_URL . 'admin/digest.php?op=view&amp;digest_id=' . (int) $this->id() . '">' . _CO_IMFORUM_VIEW . '</a>';
	}
}
