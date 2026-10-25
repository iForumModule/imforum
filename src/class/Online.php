<?php
/**
 * Who is in which forum or topic right now
 *
 * Volatile: rows older than the online timeout are purged and never migrated.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_Online extends icms_ipf_Object {

	public function __construct(mod_imforum_OnlineHandler $handler) {
		parent::__construct($handler);

		$this->quickInitVar('online_id', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('forum_id', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('topic_id', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('uid', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('uname', XOBJ_DTYPE_TXTBOX);
		$this->quickInitVar('ip', XOBJ_DTYPE_TXTBOX);
		$this->quickInitVar('updated', XOBJ_DTYPE_LTIME, false, '', '', 0);
	}
}
