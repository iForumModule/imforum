<?php
/**
 * Administration menu
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

// The position of each entry is the $menuIndex the admin scripts pass to
// imforum_admin_page(); keep both in step.
$adminmenu = [];
foreach ([
	'index' => _MI_IMFORUM_MENU_INDEX,
	'category' => _MI_IMFORUM_MENU_CATEGORIES,
	'forum' => _MI_IMFORUM_MENU_FORUMS,
	'permtemplate' => _MI_IMFORUM_MENU_PERMTEMPLATE,
	'report' => _MI_IMFORUM_MENU_REPORTS,
	'suspension' => _MI_IMFORUM_MENU_SUSPENSIONS,
	'digest' => _MI_IMFORUM_MENU_DIGESTS,
	'archive' => _MI_IMFORUM_MENU_ARCHIVE,
	'prune' => _MI_IMFORUM_MENU_PRUNE,
	'import' => _MI_IMFORUM_MENU_IMPORT,
] as $script => $title) {
	$adminmenu[] = [
		'title' => $title,
		'link' => 'admin/' . $script . '.php',
		'icon' => 'images/imforum_iconbig.png',
		'small' => 'images/imforum_iconsmall.png',
	];
}

// icms_module_Object::displayAdminMenu() counts the header menu without an
// isset check, so it must always be an array.
$headermenu = [];
$headermenu[] = [
	'title' => _MI_IMFORUM_NAME,
	'link' => ICMS_URL . '/modules/' . basename(dirname(__DIR__)) . '/',
];
