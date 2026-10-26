<?php
/**
 * A topic: one page of its posts, flat or as a thread
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

include_once dirname(__DIR__, 2) . '/mainfile.php';
include_once __DIR__ . '/include/common.php';

$permissions = mod_imforum_PermissionService::forCurrentUser();
$topicHandler = icms_getModuleHandler('topic', IMFORUM_DIRNAME);
$postHandler = icms_getModuleHandler('post', IMFORUM_DIRNAME);

$topicId = mod_imforum_View::intParam('topic');
$topic = $topicId > 0 ? $topicHandler->get($topicId) : null;
$forum = ($topic !== null && !$topic->isNew()) ? $permissions->getForum((int) $topic->getVar('forum_id', 'e')) : null;
if ($forum === null || !$permissions->canReadForum($forum)) {
	redirect_header(mod_imforum_View::url('index'), 3, _NOPERM);
	exit;
}
$forumId = (int) $forum->id();
$moderator = $permissions->isModerator($forumId);
// topics waiting for approval are for the moderators
if ((int) $topic->getVar('approved', 'e') !== 1 && !$moderator) {
	redirect_header(mod_imforum_View::url('forum', ['forum' => $forumId]), 3, _NOPERM);
	exit;
}

$mode = ($_GET['mode'] ?? mod_imforum_Settings::get('default_view')) === 'tree' ? 'tree' : 'flat';
$perPage = mod_imforum_Settings::getCount('posts_per_page');
$total = $postHandler->countApproved($topicId);

// the page of posts: by position in the thread for the tree, by id for the flat view
$order = $mode === 'tree' ? $postHandler->getThreadOrder($topicId) : null;
$start = mod_imforum_View::intParam('start');
$jumpTo = mod_imforum_View::intParam('post');
if ($jumpTo > 0) {
	if ($order !== null) {
		$position = array_search($jumpTo, array_column($order, 'post_id'), true);
		$start = $position === false ? 0 : (int) $position;
	} else {
		$start = $postHandler->getPosition($topicId, $jumpTo);
	}
	$start = (int) (floor($start / $perPage) * $perPage);
}
$start = min($start, max(0, (int) (ceil($total / $perPage) - 1) * $perPage));

$depths = [];
if ($order !== null) {
	$slice = array_slice($order, $start, $perPage);
	foreach ($slice as $entry) {
		$depths[$entry['post_id']] = $entry['depth'];
	}
	$posts = $postHandler->getByIds(array_column($slice, 'post_id'));
} else {
	$posts = $postHandler->getPage($topicId, $start, $perPage);
}

$karma = new mod_imforum_KarmaService($permissions->getUid());
$canReply = $permissions->canReply($forum, $topic);
$canReport = $permissions->canReport($forum);
$firstPostId = $moderator ? (int) $topic->getVar('topic_first_post_id', 'e') : 0;
$minutes = mod_imforum_Settings::getCount('online_minutes');
$posters = mod_imforum_PosterInfo::load(array_map(static fn (mod_imforum_Post $post): int => (int) $post->getVar('uid', 'e'), $posts), $minutes);

$rows = [];
foreach ($posts as $postId => $post) {
	$karma->apply($post, $moderator);
	$row = $post->toArray();
	$uid = $row['uid'];
	$poster = $posters[$uid] ?? null;
	$row['author'] = mod_imforum_View::poster($uid, (string) $post->getVar('poster_name', 'n'));
	$row['time'] = mod_imforum_View::time($row['post_time']);
	$row['depth'] = min($depths[$postId] ?? 0, 8);
	$row['url'] = mod_imforum_View::link('topic', ['topic' => $topicId, 'post' => $postId, 'mode' => $mode === 'tree' ? 'tree' : '']) . '#post-' . $postId;
	$row['poster'] = $poster;
	// the signature belongs to the post only when the author and the forum allow it
	$row['signature'] = ($poster !== null && $row['attachsig'] === 1 && (int) $forum->getVar('allow_sig', 'e') === 1) ? $poster['signature'] : '';
	$row['edits'] = array_map(static fn (array $edit): array => [
		'name' => mod_imforum_View::poster((int) $edit['uid'], (string) $edit['name']),
		'time' => mod_imforum_View::time((int) $edit['time']),
	], $row['edits']);
	$row['attachments'] = [];
	$row['can_edit'] = $permissions->canEdit($forum, $topic, $post);
	$row['can_delete'] = $permissions->canDelete($forum, $topic, $post);
	$row['reply_url'] = $canReply ? mod_imforum_View::link('post', ['topic' => $topicId, 'post' => $postId]) : '';
	$row['quote_url'] = ($canReply && !$row['locked']) ? mod_imforum_View::link('post', ['topic' => $topicId, 'post' => $postId, 'quote' => 1]) : '';
	$row['edit_url'] = $row['can_edit'] ? mod_imforum_View::link('post', ['do' => 'edit', 'post' => $postId]) : '';
	$row['delete_url'] = $row['can_delete'] ? mod_imforum_View::link('post', ['do' => 'delete', 'post' => $postId]) : '';
	$row['report_url'] = $canReport ? mod_imforum_View::link('report', ['post' => $postId]) : '';
	$row['split_url'] = ($moderator && $postId !== $firstPostId) ? mod_imforum_View::link('moderate', ['do' => 'split', 'post' => $postId]) : '';
	$row['suspend_url'] = ($moderator && $uid > 0 && $uid !== $permissions->getUid()) ? mod_imforum_View::link('moderate', ['do' => 'suspend', 'post' => $postId]) : '';
	$rows[] = $row;
}

// the files of the posts the reader may open
$attachments = icms_getModuleHandler('attachment', IMFORUM_DIRNAME)->getByPosts(array_keys($posts));
foreach ($rows as &$row) {
	if (!$row['locked']) {
		foreach ($attachments[$row['post_id']] ?? [] as $attachment) {
			$row['attachments'][] = [
				'url' => mod_imforum_View::link('attachment', ['id' => (int) $attachment->id()]),
				'name' => $attachment->getVar('name_display'),
				'size' => round((int) $attachment->getVar('filesize', 'e') / 1024) . ' KB',
				'downloads' => (int) $attachment->getVar('downloads', 'e'),
			];
		}
	}
}
unset($row);

// reading a page marks the topic as read up to the newest post seen; the last page reads it to the end
$tracker = new mod_imforum_ReadTracker($permissions->getUid());
if ($posts) {
	$onLastPage = $start + count($posts) >= $total;
	$tracker->markTopicRead($topicId, $onLastPage ? (int) $topic->getVar('topic_last_post_id', 'e') : max(array_keys($posts)));
}
// a view counts once per visit
if (isset($_SESSION) && !isset($_SESSION['imforum_viewed'][$topicId])) {
	$_SESSION['imforum_viewed'][$topicId] = true;
	$topicHandler->incrementViews($topicId);
}

$xoopsOption['template_main'] = 'imforum_topic.html';
include_once ICMS_ROOT_PATH . '/header.php';
global $icmsTpl, $xoTheme;
$xoTheme->addStylesheet(IMFORUM_URL . 'assets/css/imforum.css');

$trail = [];
for ($parent = $forumId, $guard = 0; $parent > 0 && $guard < 10; $guard++) {
	$step = $permissions->getForum($parent);
	if ($step === null || !$permissions->canSeeForum($step)) {
		break;
	}
	array_unshift($trail, [$step->getVar('forum_name', 'n'), mod_imforum_View::url('forum', ['forum' => $parent])]);
	$parent = (int) $step->getVar('parent_forum', 'e');
}
$trail[] = [$topic->getVar('topic_title', 'n')];

$online = icms_getModuleHandler('online', IMFORUM_DIRNAME);
$showOnline = (int) mod_imforum_Settings::get('show_online') === 1;
if ($showOnline) {
	$online->track($permissions->getUid(), icms::$user ? (string) icms::$user->getVar('uname', 'n') : '', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), $forumId, $topicId, $minutes);
}

$icmsTpl->assign('imforum_breadcrumb', mod_imforum_View::breadcrumb($trail));
$icmsTpl->assign('imforum_topic', [
	'id' => $topicId,
	'prefix' => mod_imforum_View::prefix((int) $topic->getVar('topic_subject', 'e')),
	'title' => $topic->getVar('topic_title'),
	'views' => (int) $topic->getVar('topic_views', 'e'),
	'replies' => (int) $topic->getVar('topic_replies', 'e'),
	'locked' => $topic->isLocked(),
	'sticky' => (int) $topic->getVar('topic_sticky', 'e') === 1,
	'digest' => (int) $topic->getVar('topic_digest', 'e') === 1,
	'forum_url' => mod_imforum_View::link('forum', ['forum' => $forumId]),
	'forum_name' => $forum->getVar('forum_name'),
]);
$icmsTpl->assign('imforum_posts', $rows);
$topicLink = static fn (string $do): string => mod_imforum_View::link('moderate', ['do' => $do, 'topic' => $topicId]);
$icmsTpl->assign('imforum_mod_tools', $moderator ? [
	['url' => $topicLink($topic->isLocked() ? 'unlock' : 'lock'), 'label' => $topic->isLocked() ? _MD_IMFORUM_MOD_UNLOCK : _MD_IMFORUM_MOD_LOCK],
	['url' => $topicLink((int) $topic->getVar('topic_sticky', 'e') === 1 ? 'unsticky' : 'sticky'), 'label' => (int) $topic->getVar('topic_sticky', 'e') === 1 ? _MD_IMFORUM_MOD_UNSTICKY : _MD_IMFORUM_MOD_STICKY],
	['url' => $topicLink((int) $topic->getVar('topic_digest', 'e') === 1 ? 'undigest' : 'digest'), 'label' => (int) $topic->getVar('topic_digest', 'e') === 1 ? _MD_IMFORUM_MOD_UNDIGEST : _MD_IMFORUM_MOD_DIGEST],
	['url' => $topicLink('move'), 'label' => _MD_IMFORUM_MOD_MOVE],
	['url' => $topicLink('merge'), 'label' => _MD_IMFORUM_MOD_MERGE],
	['url' => $topicLink('delete'), 'label' => _MD_IMFORUM_MOD_DELETE],
] : []);
$icmsTpl->assign('imforum_reply_url', $canReply ? mod_imforum_View::link('post', ['topic' => $topicId]) : '');
// the quick reply is a short form on the page; with a captcha the full form is needed
$quick = $canReply && (int) mod_imforum_Settings::get('quickreply_enabled') === 1
	&& !($permissions->getUid() === 0 && (int) mod_imforum_Settings::get('captcha_guests') === 1);
$icmsTpl->assign('imforum_quickreply', $quick ? [
	'action' => mod_imforum_View::link('post'),
	'token' => icms::$security->getTokenHTML(),
	'topic' => $topicId,
	'forum' => $forumId,
	'subject' => mod_imforum_View::escape('Re: ' . preg_replace('/^(Re:\s*)+/i', '', (string) $topic->getVar('topic_title', 'n'))),
	'guest' => $permissions->getUid() === 0,
	'signature' => $permissions->getUid() > 0 && (int) $forum->getVar('allow_sig', 'e') === 1,
] : null);
$icmsTpl->assign('imforum_mode', $mode);
$icmsTpl->assign('imforum_flat_url', mod_imforum_View::link('topic', ['topic' => $topicId, 'mode' => 'flat']));
$icmsTpl->assign('imforum_tree_url', mod_imforum_View::link('topic', ['topic' => $topicId, 'mode' => 'tree']));
$icmsTpl->assign('imforum_pager', mod_imforum_View::pager('topic', ['topic' => $topicId, 'mode' => $mode], $total, $perPage, $start));
$icmsTpl->assign('imforum_online', $showOnline ? mod_imforum_View::online($online->getSnapshot($forumId, $topicId, $minutes)) : null);

include_once ICMS_ROOT_PATH . '/footer.php';
