<?php
/**
 * One group in the default permissions for new forums
 *
 * iForum kept this template in a cache file (IforumPermissionHandler::getTemplate).
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_Permtemplate extends icms_ipf_Object {

	public function __construct(mod_imforum_PermtemplateHandler $handler) {
		parent::__construct($handler);

		$this->quickInitVar('template_id', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('perm_name', XOBJ_DTYPE_TXTBOX, true);
		$this->quickInitVar('groupid', XOBJ_DTYPE_INT, true);

		$this->setControl('groupid', ['name' => 'group']);
	}
}
