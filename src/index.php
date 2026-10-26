<?php
/**
 * Forum index: the categories with the forums the visitor may see
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

include_once dirname(__DIR__, 2) . '/mainfile.php';
include_once __DIR__ . '/include/common.php';

$permissions = mod_imforum_PermissionService::forCurrentUser();

$xoopsOption['template_main'] = 'imforum_index.html';
include_once ICMS_ROOT_PATH . '/header.php';
/** header.php creates the theme and its template engine as globals */
global $icmsTpl, $xoTheme;
$xoTheme->addStylesheet(IMFORUM_URL . 'assets/css/imforum.css');

$tracker = new mod_imforum_ReadTracker($permissions->getUid());
$forums = $permissions->getVisibleForums();
$unread = $tracker->countUnreadByForum(array_keys($forums));

$lastPostIds = [];
$children = [];
foreach ($forums as $forumId => $forum) {
	$lastPostIds[] = (int) $forum->getVar('forum_last_post_id', 'e');
	$children[(int) $forum->getVar('parent_forum', 'e')][] = $forum;
}
$summaries = icms_getModuleHandler('post', IMFORUM_DIRNAME)->getSummaries($lastPostIds);

$describe = static function (mod_imforum_Forum $forum) use (&$describe, $children, $unread, $summaries): array {
	$forumId = (int) $forum->id();
	$last = $summaries[(int) $forum->getVar('forum_last_post_id', 'e')] ?? null;
	$subforums = [];
	foreach ($children[$forumId] ?? [] as $child) {
		$subforums[] = $describe($child);
	}
	return [
		'id' => $forumId,
		'url' => mod_imforum_View::link('forum', ['forum' => $forumId]),
		'name' => $forum->getVar('forum_name'),
		'description' => $forum->getVar('forum_desc'),
		'topics' => (int) $forum->getVar('forum_topics', 'e'),
		'posts' => (int) $forum->getVar('forum_posts', 'e'),
		'unread' => $unread[$forumId] ?? 0,
		'last' => $last === null ? null : [
			'url' => mod_imforum_View::link('topic', ['topic' => $last['topic_id'], 'post' => $last['post_id']]) . '#post-' . $last['post_id'],
			'subject' => mod_imforum_View::escape($last['subject']),
			'poster' => mod_imforum_View::poster($last['uid'], $last['poster_name']),
			'time' => mod_imforum_View::time($last['post_time']),
		],
		'subforums' => $subforums,
	];
};

$categoryCriteria = new icms_db_criteria_Compo();
$categoryCriteria->setSort('weight');
$categoryCriteria->setOrder('ASC');
$categories = [];
$totals = ['topics' => 0, 'posts' => 0];
foreach (icms_getModuleHandler('category', IMFORUM_DIRNAME)->getObjects($categoryCriteria) as $category) {
	$catId = (int) $category->id();
	$rows = [];
	foreach ($children[0] ?? [] as $forum) {
		if ((int) $forum->getVar('cat_id', 'e') === $catId) {
			$rows[] = $describe($forum);
		}
	}
	// A forum in a category without parent in the list (its parent is hidden) is not shown here
	if ($rows) {
		$categories[] = [
			'id' => $catId,
			'title' => $category->getVar('cat_title'),
			'description' => $category->getVar('cat_description'),
			'forums' => $rows,
		];
	}
}
foreach ($forums as $forum) {
	$totals['topics'] += (int) $forum->getVar('forum_topics', 'e');
	$totals['posts'] += (int) $forum->getVar('forum_posts', 'e');
}

$online = icms_getModuleHandler('online', IMFORUM_DIRNAME);
$showOnline = (int) mod_imforum_Settings::get('show_online') === 1;
if ($showOnline) {
	$online->track($permissions->getUid(), icms::$user ? (string) icms::$user->getVar('uname', 'n') : '', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 0, mod_imforum_Settings::getCount('online_minutes'));
}

$icmsTpl->assign('imforum_breadcrumb', mod_imforum_View::breadcrumb([]));
$icmsTpl->assign('imforum_categories', $categories);
$icmsTpl->assign('imforum_totals', $totals);
$icmsTpl->assign('imforum_viewall_url', mod_imforum_View::link('viewall'));
$icmsTpl->assign('imforum_unread_url', mod_imforum_View::link('viewall', ['type' => 'unread']));
$icmsTpl->assign('imforum_is_member', $permissions->getUid() > 0);
$icmsTpl->assign('imforum_online', $showOnline ? mod_imforum_View::online($online->getSnapshot(0, 0, mod_imforum_Settings::getCount('online_minutes'))) : null);

include_once ICMS_ROOT_PATH . '/footer.php';
