<?php
/**
 * Administration index
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

include_once __DIR__ . '/admin_header.php';

icms_cp_header();
/** icms_cp_header() creates the admin template engine as a global */
global $icmsAdminTpl;
imforum_admin_menu(0, _AM_IMFORUM_INDEX);

$icmsAdminTpl->assign('imforum_version', icms::$module->getVar('version'));
$icmsAdminTpl->assign('imforum_dbversion', icms::$module->getDBVersion());
$icmsAdminTpl->assign('imforum_stats', [
	_AM_IMFORUM_CATEGORIES => icms_getModuleHandler('category', IMFORUM_DIRNAME)->getCount(),
	_AM_IMFORUM_FORUMS => icms_getModuleHandler('forum', IMFORUM_DIRNAME)->getCount(),
	_AM_IMFORUM_TOPICS => icms_getModuleHandler('topic', IMFORUM_DIRNAME)->getCount(),
	_AM_IMFORUM_POSTS => icms_getModuleHandler('post', IMFORUM_DIRNAME)->getCount(),
]);
$icmsAdminTpl->assign('imforum_open_reports', icms_getModuleHandler('report', IMFORUM_DIRNAME)->getOpenCount());
$icmsAdminTpl->assign('imforum_report_url', imforum_admin_url('report.php'));
$icmsAdminTpl->display('db:imforum_admin_index.html');

icms_cp_footer();
