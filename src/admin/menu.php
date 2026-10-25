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

$adminmenu = [];
$adminmenu[] = [
	'title' => _MI_IMFORUM_MENU_INDEX,
	'link' => 'admin/index.php',
	'icon' => 'images/imforum_iconbig.png',
	'small' => 'images/imforum_iconsmall.png',
];

// icms_module_Object::displayAdminMenu() counts the header menu without an
// isset check, so it must always be an array.
$headermenu = [];
$headermenu[] = [
	'title' => _MI_IMFORUM_NAME,
	'link' => ICMS_URL . '/modules/' . basename(dirname(__DIR__)) . '/',
];
