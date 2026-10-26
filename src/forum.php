<?php
/**
 * A forum: its subforums and one page of topics
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

include_once dirname(__DIR__, 2) . '/mainfile.php';
include_once __DIR__ . '/include/common.php';

$permissions = mod_imforum_PermissionService::forCurrentUser();
$forumId = mod_imforum_View::intParam('forum');
$forum = $permissions->getForum($forumId);
if ($forum === null || !$permissions->canReadForum($forum)) {
	redirect_header(mod_imforum_View::url('index'), 3, _NOPERM);
	exit;
}

$tracker = new mod_imforum_ReadTracker($permissions->getUid());
$topicHandler = icms_getModuleHandler('topic', IMFORUM_DIRNAME);

// "Mark all as read" changes data, so it takes a POST with a token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'markread') {
	if (!icms::$security->check()) {
		redirect_header(mod_imforum_View::url('forum', ['forum' => $forumId]), 3, implode('<br />', icms::$security->getErrors()));
		exit;
	}
	$tracker->markForumRead($forumId, (int) $forum->getVar('forum_last_post_id', 'e'));
	redirect_header(mod_imforum_View::url('forum', ['forum' => $forumId]), 2, _MD_IMFORUM_MARKED_READ);
	exit;
}

$xoopsOption['template_main'] = 'imforum_forum.html';
include_once ICMS_ROOT_PATH . '/header.php';
global $icmsTpl, $xoTheme;
$xoTheme->addStylesheet(IMFORUM_URL . 'assets/css/imforum.css');

$perPage = mod_imforum_Settings::getCount('topics_per_page');
$start = mod_imforum_View::intParam('start');
$total = $topicHandler->countForumTopics($forumId);
$start = min($start, max(0, (int) (ceil($total / $perPage) - 1) * $perPage));
$rows = mod_imforum_TopicList::rows($topicHandler->getForumTopics($forumId, $start, $perPage), [$forumId => $forum], $tracker, false);

// subforums the visitor may see
$visible = $permissions->getVisibleForums();
$unread = $tracker->countUnreadByForum(array_keys($visible));
$subforums = [];
foreach ($visible as $child) {
	if ((int) $child->getVar('parent_forum', 'e') === $forumId) {
		$subforums[] = [
			'url' => mod_imforum_View::link('forum', ['forum' => (int) $child->id()]),
			'name' => $child->getVar('forum_name'),
			'description' => $child->getVar('forum_desc'),
			'topics' => (int) $child->getVar('forum_topics', 'e'),
			'posts' => (int) $child->getVar('forum_posts', 'e'),
			'unread' => $unread[(int) $child->id()] ?? 0,
		];
	}
}

// the path above this forum
$trail = [[$forum->getVar('forum_name', 'n')]];
for ($parent = (int) $forum->getVar('parent_forum', 'e'), $guard = 0; $parent > 0 && $guard < 10; $guard++) {
	$parentForum = $permissions->getForum($parent);
	if ($parentForum === null || !$permissions->canSeeForum($parentForum)) {
		break;
	}
	array_unshift($trail, [$parentForum->getVar('forum_name', 'n'), mod_imforum_View::url('forum', ['forum' => $parent])]);
	$parent = (int) $parentForum->getVar('parent_forum', 'e');
}

$moderators = [];
foreach (icms_getModuleHandler('moderator', IMFORUM_DIRNAME)->getModeratorIds($forumId) as $uid) {
	$moderators[] = mod_imforum_View::poster($uid, '');
}

$online = icms_getModuleHandler('online', IMFORUM_DIRNAME);
$showOnline = (int) mod_imforum_Settings::get('show_online') === 1;
$minutes = mod_imforum_Settings::getCount('online_minutes');
if ($showOnline) {
	$online->track($permissions->getUid(), icms::$user ? (string) icms::$user->getVar('uname', 'n') : '', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), $forumId, 0, $minutes);
}

$icmsTpl->assign('imforum_new_topic_url', $permissions->canStartTopic($forum) ? mod_imforum_View::link('post', ['forum' => $forumId]) : '');
$icmsTpl->assign('imforum_breadcrumb', mod_imforum_View::breadcrumb($trail));
$icmsTpl->assign('imforum_forum', [
	'id' => $forumId,
	'name' => $forum->getVar('forum_name'),
	'description' => $forum->getVar('forum_desc'),
	'inactive' => (int) $forum->getVar('forum_active', 'e') === 0,
]);
$icmsTpl->assign('imforum_subforums', $subforums);
$icmsTpl->assign('imforum_topics', $rows);
$icmsTpl->assign('imforum_moderators', $moderators);
$icmsTpl->assign('imforum_pager', mod_imforum_View::pager('forum', ['forum' => $forumId], $total, $perPage, $start));
$icmsTpl->assign('imforum_can_mark_read', $tracker->isTracking());
$icmsTpl->assign('imforum_token', icms::$security->getTokenHTML());
$icmsTpl->assign('imforum_form_action', mod_imforum_View::link('forum', ['forum' => $forumId]));
$icmsTpl->assign('imforum_online', $showOnline ? mod_imforum_View::online($online->getSnapshot($forumId, 0, $minutes)) : null);

include_once ICMS_ROOT_PATH . '/footer.php';
