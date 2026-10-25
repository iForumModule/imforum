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

	// IPF objects: one table <prefix>_imforum_<item> each, classes in class/.
	// Never remove or rename an item without a migration; the core would leave
	// the old table behind and create an empty new one.
	'object_items' => [
		'category', 'forum', 'moderator', 'topic', 'post', 'attachment', 'report',
		'suspension', 'readmark', 'online', 'digest', 'archive', 'permtemplate',
	],

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
		['file' => 'imforum_admin.html', 'description' => 'Administration page'],
	],

	'blocks' => [],
	// No 'config' key until the module has preferences: icms_module_Object sets
	// hasconfig for any array, even an empty one, and the admin menu then
	// shows a Preferences link that ends on the system preferences.
];
