<?php
/**
 * A suspension: a user or IP address that may read but not post
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_Suspension extends icms_ipf_Object {

	public function __construct(mod_imforum_SuspensionHandler $handler) {
		parent::__construct($handler);

		$this->quickInitVar('suspension_id', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('uid', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('ip', XOBJ_DTYPE_TXTBOX);
		// 0 = every forum
		$this->quickInitVar('forum_id', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('start_time', XOBJ_DTYPE_LTIME, false, '', '', 0);
		$this->quickInitVar('end_time', XOBJ_DTYPE_LTIME, false, '', '', 0);
		$this->quickInitVar('description', XOBJ_DTYPE_TXTAREA);

		$this->setControl('uid', ['name' => 'user']);
		$this->setControl('forum_id', ['itemHandler' => 'forum', 'method' => 'getScopeSelectList', 'module' => IMFORUM_DIRNAME]);
	}

	public function getUserLink(): string {
		$uid = (int) $this->getVar('uid', 'e');
		return $uid > 0 ? icms_member_user_Handler::getUserLink($uid) : '';
	}

	/**
	 * Forum name, already escaped, or "every forum" for forum_id 0
	 */
	public function getForumName(): string {
		$forums = icms_getModuleHandler('forum', IMFORUM_DIRNAME)->getScopeSelectList();
		return $forums[(int) $this->getVar('forum_id', 'e')] ?? '';
	}
}
