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
	'hasSearch' => 1,
	'search' => ['file' => 'include/search.inc.php', 'func' => 'imforum_search'],
	'hasComments' => 0,
	'hasNotification' => 1,
	'notification' => [
		'lookup_file' => 'include/notification.inc.php',
		'lookup_func' => 'imforum_notify_iteminfo',
		'category' => [
			[
				'name' => 'global',
				'title' => _MI_IMFORUM_NOTIFY_GLOBAL,
				'description' => _MI_IMFORUM_NOTIFY_GLOBAL_DSC,
				'subscribe_from' => ['index.php', 'viewall.php'],
			],
			[
				'name' => 'forum',
				'title' => _MI_IMFORUM_NOTIFY_FORUM,
				'description' => _MI_IMFORUM_NOTIFY_FORUM_DSC,
				'subscribe_from' => ['forum.php'],
				'item_name' => 'forum',
				'allow_bookmark' => 1,
			],
			[
				'name' => 'thread',
				'title' => _MI_IMFORUM_NOTIFY_THREAD,
				'description' => _MI_IMFORUM_NOTIFY_THREAD_DSC,
				'subscribe_from' => ['topic.php'],
				'item_name' => 'topic',
				'allow_bookmark' => 1,
			],
		],
		'event' => [
			[
				'name' => 'new_post', 'category' => 'thread',
				'title' => _MI_IMFORUM_NOTIFY_THREAD_NEWPOST, 'caption' => _MI_IMFORUM_NOTIFY_THREAD_NEWPOST_CAP,
				'description' => _MI_IMFORUM_NOTIFY_THREAD_NEWPOST_DSC, 'mail_template' => 'thread_newpost', 'mail_subject' => _MI_IMFORUM_NOTIFY_THREAD_NEWPOST_SBJ,
			],
			[
				'name' => 'new_thread', 'category' => 'forum',
				'title' => _MI_IMFORUM_NOTIFY_FORUM_NEWTHREAD, 'caption' => _MI_IMFORUM_NOTIFY_FORUM_NEWTHREAD_CAP,
				'description' => _MI_IMFORUM_NOTIFY_FORUM_NEWTHREAD_DSC, 'mail_template' => 'forum_newthread', 'mail_subject' => _MI_IMFORUM_NOTIFY_FORUM_NEWTHREAD_SBJ,
			],
			[
				'name' => 'new_post', 'category' => 'forum',
				'title' => _MI_IMFORUM_NOTIFY_FORUM_NEWPOST, 'caption' => _MI_IMFORUM_NOTIFY_FORUM_NEWPOST_CAP,
				'description' => _MI_IMFORUM_NOTIFY_FORUM_NEWPOST_DSC, 'mail_template' => 'forum_newpost', 'mail_subject' => _MI_IMFORUM_NOTIFY_FORUM_NEWPOST_SBJ,
			],
			[
				'name' => 'new_thread', 'category' => 'global',
				'title' => _MI_IMFORUM_NOTIFY_GLOBAL_NEWTHREAD, 'caption' => _MI_IMFORUM_NOTIFY_GLOBAL_NEWTHREAD_CAP,
				'description' => _MI_IMFORUM_NOTIFY_GLOBAL_NEWTHREAD_DSC, 'mail_template' => 'global_newthread', 'mail_subject' => _MI_IMFORUM_NOTIFY_GLOBAL_NEWTHREAD_SBJ,
			],
			[
				'name' => 'new_post', 'category' => 'global',
				'title' => _MI_IMFORUM_NOTIFY_GLOBAL_NEWPOST, 'caption' => _MI_IMFORUM_NOTIFY_GLOBAL_NEWPOST_CAP,
				'description' => _MI_IMFORUM_NOTIFY_GLOBAL_NEWPOST_DSC, 'mail_template' => 'global_newpost', 'mail_subject' => _MI_IMFORUM_NOTIFY_GLOBAL_NEWPOST_SBJ,
			],
		],
	],

	'templates' => [
		['file' => 'imforum_index.html', 'description' => 'Forum index'],
		['file' => 'imforum_admin_index.html', 'description' => 'Administration index'],
		['file' => 'imforum_admin.html', 'description' => 'Administration page'],
		['file' => 'imforum_forum.html', 'description' => 'Forum with its topics'],
		['file' => 'imforum_topic.html', 'description' => 'Topic with its posts'],
		['file' => 'imforum_post.html', 'description' => 'Form to write, edit or delete a post'],
		['file' => 'imforum_viewall.html', 'description' => 'Topics of all forums'],
		['file' => 'imforum_archive.html', 'description' => 'Archived topics'],
		['file' => 'imforum_breadcrumb.html', 'description' => 'Path above a page'],
		['file' => 'imforum_topiclist.html', 'description' => 'List of topics'],
		['file' => 'imforum_online.html', 'description' => 'Who is online'],
	],

	'blocks' => [
		[
			'file' => 'imforum_blocks.php',
			'name' => _MI_IMFORUM_BLOCK_RECENT,
			'description' => _MI_IMFORUM_BLOCK_RECENT_DSC,
			'show_func' => 'imforum_block_recent',
			'edit_func' => 'imforum_block_edit_recent',
			'options' => '10',
			'template' => 'imforum_block_topics.html',
		],
		[
			'file' => 'imforum_blocks.php',
			'name' => _MI_IMFORUM_BLOCK_HOT,
			'description' => _MI_IMFORUM_BLOCK_HOT_DSC,
			'show_func' => 'imforum_block_hot',
			'edit_func' => 'imforum_block_edit_hot',
			'options' => '10|30',
			'template' => 'imforum_block_topics.html',
		],
		[
			'file' => 'imforum_blocks.php',
			'name' => _MI_IMFORUM_BLOCK_TOPPOSTERS,
			'description' => _MI_IMFORUM_BLOCK_TOPPOSTERS_DSC,
			'show_func' => 'imforum_block_topposters',
			'edit_func' => 'imforum_block_edit_recent',
			'options' => '10',
			'template' => 'imforum_block_topposters.html',
		],
	],

	// Preferences. The first one creates the config array, which is what turns
	// on the Preferences link of the module: do not declare an empty 'config'.
	'config' => [
		[
			'name' => 'topics_per_page',
			'title' => '_MI_IMFORUM_CFG_TOPICS_PER_PAGE',
			'description' => '_MI_IMFORUM_CFG_TOPICS_PER_PAGE_DSC',
			'formtype' => 'textbox',
			'valuetype' => 'int',
			'default' => 20,
		],
		[
			'name' => 'posts_per_page',
			'title' => '_MI_IMFORUM_CFG_POSTS_PER_PAGE',
			'description' => '_MI_IMFORUM_CFG_POSTS_PER_PAGE_DSC',
			'formtype' => 'textbox',
			'valuetype' => 'int',
			'default' => 10,
		],
		[
			'name' => 'default_view',
			'title' => '_MI_IMFORUM_CFG_DEFAULT_VIEW',
			'description' => '_MI_IMFORUM_CFG_DEFAULT_VIEW_DSC',
			'formtype' => 'select',
			'valuetype' => 'text',
			'default' => 'flat',
			'options' => ['_MI_IMFORUM_VIEW_FLAT' => 'flat', '_MI_IMFORUM_VIEW_TREE' => 'tree'],
		],
		[
			'name' => 'show_online',
			'title' => '_MI_IMFORUM_CFG_SHOW_ONLINE',
			'description' => '_MI_IMFORUM_CFG_SHOW_ONLINE_DSC',
			'formtype' => 'yesno',
			'valuetype' => 'int',
			'default' => 1,
		],
		[
			'name' => 'online_minutes',
			'title' => '_MI_IMFORUM_CFG_ONLINE_MINUTES',
			'description' => '_MI_IMFORUM_CFG_ONLINE_MINUTES_DSC',
			'formtype' => 'textbox',
			'valuetype' => 'int',
			'default' => 15,
		],
		[
			'name' => 'rss_items',
			'title' => '_MI_IMFORUM_CFG_RSS_ITEMS',
			'description' => '_MI_IMFORUM_CFG_RSS_ITEMS_DSC',
			'formtype' => 'textbox',
			'valuetype' => 'int',
			'default' => 20,
		],
		[
			'name' => 'edit_timelimit',
			'title' => '_MI_IMFORUM_CFG_EDIT_TIMELIMIT',
			'description' => '_MI_IMFORUM_CFG_EDIT_TIMELIMIT_DSC',
			'formtype' => 'textbox',
			'valuetype' => 'int',
			'default' => 30,
		],
		[
			'name' => 'flood_seconds',
			'title' => '_MI_IMFORUM_CFG_FLOOD_SECONDS',
			'description' => '_MI_IMFORUM_CFG_FLOOD_SECONDS_DSC',
			'formtype' => 'textbox',
			'valuetype' => 'int',
			'default' => 30,
		],
		[
			'name' => 'quickreply_enabled',
			'title' => '_MI_IMFORUM_CFG_QUICKREPLY',
			'description' => '_MI_IMFORUM_CFG_QUICKREPLY_DSC',
			'formtype' => 'yesno',
			'valuetype' => 'int',
			'default' => 1,
		],
		[
			'name' => 'captcha_guests',
			'title' => '_MI_IMFORUM_CFG_CAPTCHA_GUESTS',
			'description' => '_MI_IMFORUM_CFG_CAPTCHA_GUESTS_DSC',
			'formtype' => 'yesno',
			'valuetype' => 'int',
			'default' => 1,
		],
		[
			'name' => 'attach_max_files',
			'title' => '_MI_IMFORUM_CFG_ATTACH_MAX_FILES',
			'description' => '_MI_IMFORUM_CFG_ATTACH_MAX_FILES_DSC',
			'formtype' => 'textbox',
			'valuetype' => 'int',
			'default' => 3,
		],
		[
			'name' => 'subject_prefix',
			'title' => '_MI_IMFORUM_CFG_SUBJECT_PREFIX',
			'description' => '_MI_IMFORUM_CFG_SUBJECT_PREFIX_DSC',
			'formtype' => 'textbox',
			'valuetype' => 'text',
			'default' => '',
		],
		[
			'name' => 'disclaimer_show',
			'title' => '_MI_IMFORUM_CFG_DISCLAIMER_SHOW',
			'description' => '_MI_IMFORUM_CFG_DISCLAIMER_SHOW_DSC',
			'formtype' => 'yesno',
			'valuetype' => 'int',
			'default' => 0,
		],
		[
			'name' => 'disclaimer',
			'title' => '_MI_IMFORUM_CFG_DISCLAIMER',
			'description' => '_MI_IMFORUM_CFG_DISCLAIMER_DSC',
			'formtype' => 'textarea',
			'valuetype' => 'text',
			'default' => '',
		],
	],
];
