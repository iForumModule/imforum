<?php
/**
 * imforum module definition
 *
 * imforum is the IPF-based successor of iForum. As soon as `object_items` is an
 * array, the core flags the module as IPF (ipf = 1), registers class/ under the
 * namespace mod_imforum and builds the tables from the object definitions.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once __DIR__ . '/include/common.php';

$modversion = [
	'name' => _MI_IMFORUM_NAME,
	'version' => '1.0.0',
	'status' => 'Alpha',
	'status_version' => '1.0.0 Alpha',
	'date' => '25 October 2026',
	'description' => _MI_IMFORUM_DESC,
	'author' => 'fiammybe',
	'author_realname' => 'David Janssens',
	'credits' => 'Based on the ideas of iForum, CBB and newbb',
	'license' => 'MIT, see LICENSE',
	'official' => false,
	'dirname' => basename(__DIR__),
	'modname' => IMFORUM_DIRNAME,

	'iconsmall' => 'images/imforum_iconsmall.png',
	'iconbig' => 'images/imforum_iconbig.png',
	'image' => 'images/imforum_icon.png',

	'support_site_url' => 'https://github.com/iForumModule/imforum',
	'support_site_name' => 'imforum on GitHub',
	'submit_bug' => 'https://github.com/iForumModule/imforum/issues',
	'submit_feature' => 'https://github.com/iForumModule/imforum/issues',

	// IPF objects, filled in phase 1. An empty array already enables IPF mode.
	'object_items' => [],

	'onInstall' => 'include/onupdate.php',
	'onUpdate' => 'include/onupdate.php',

	'hasAdmin' => 1,
	'adminindex' => 'admin/index.php',
	'adminmenu' => 'admin/menu.php',

	'hasMain' => 1,
	'hasSearch' => 0,
	'hasComments' => 0,
	'hasNotification' => 0,

	'templates' => [
		['file' => 'imforum_index.html', 'description' => 'Forum index'],
		['file' => 'imforum_admin_index.html', 'description' => 'Administration index'],
	],

	'blocks' => [],
	'config' => [],
];
