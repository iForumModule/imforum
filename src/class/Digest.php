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
}
