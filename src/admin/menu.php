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
