<?php
/**
 * Administration index
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

include_once dirname(__DIR__, 3) . '/include/cp_header.php';
include_once dirname(__DIR__) . '/include/common.php';

icms_cp_header();
/** icms_cp_header() creates the admin template engine as a global */
global $icmsAdminTpl;
icms::$module->displayAdminMenu(0, _AM_IMFORUM_INDEX);

$icmsAdminTpl->assign('imforum_version', icms::$module->getVar('version'));
$icmsAdminTpl->assign('imforum_dbversion', icms::$module->getDBVersion());
$icmsAdminTpl->display('db:imforum_admin_index.html');

icms_cp_footer();
