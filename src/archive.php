<?php
/**
 * Archived topics, read only
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

include_once dirname(__DIR__, 2) . '/mainfile.php';
include_once __DIR__ . '/include/common.php';

$permissions = mod_imforum_PermissionService::forCurrentUser();
$archiveHandler = icms_getModuleHandler('archive', IMFORUM_DIRNAME);
$topicHandler = icms_getModuleHandler('topic', IMFORUM_DIRNAME);
$postHandler = icms_getModuleHandler('post', IMFORUM_DIRNAME);

$topicId = mod_imforum_View::intParam('topic');
$topic = null;
if ($topicId > 0) {
	$topic = $topicHandler->get($topicId);
	$forum = $topic->isNew() ? null : $permissions->getForum((int) $topic->getVar('forum_id', 'e'));
	if ($forum === null || !$permissions->canReadForum($forum)) {
		redirect_header(mod_imforum_View::url('archive'), 3, _NOPERM);
		exit;
	}
}

$xoopsOption['template_main'] = 'imforum_archive.html';
include_once ICMS_ROOT_PATH . '/header.php';
global $icmsTpl, $xoTheme;
$xoTheme->addStylesheet(IMFORUM_URL . 'assets/css/imforum.css');

$listUrl = mod_imforum_View::url('archive');
if ($topic !== null) {
	$archived = $archiveHandler->getByTopic($topicId);
	// the posts still exist: the author's wish for karma or a reply holds for the archived text too
	$live = $postHandler->getByIds(array_map(static fn (mod_imforum_Archive $entry): int => (int) $entry->getVar('post_id', 'e'), $archived));
	$karma = new mod_imforum_KarmaService($permissions->getUid());
	$moderator = $permissions->isModerator((int) $topic->getVar('forum_id', 'e'));
	$posts = [];
	foreach ($archived as $entry) {
		$postId = (int) $entry->getVar('post_id', 'e');
		$post = $live[$postId] ?? null;
		$lock = $post === null ? mod_imforum_KarmaService::LOCK_NONE : $karma->apply($post, $moderator);
		$posts[] = [
			'id' => $postId,
			'subject' => $post === null ? '' : $post->getVar('subject'),
			'author' => $post === null ? '' : mod_imforum_View::poster((int) $post->getVar('uid', 'e'), (string) $post->getVar('poster_name', 'n')),
			'time' => $post === null ? '' : mod_imforum_View::time((int) $post->getVar('post_time', 'e')),
			'archived' => mod_imforum_View::time((int) $entry->getVar('archived_time', 'e')),
			'lock' => $lock,
			'text' => $lock !== mod_imforum_KarmaService::LOCK_NONE ? '' : (string) icms_core_DataFilter::filterTextareaDisplay((string) $entry->getVar('post_text', 'n'), 1, 1, 1, 1),
		];
	}
	$icmsTpl->assign('imforum_breadcrumb', mod_imforum_View::breadcrumb([[_MD_IMFORUM_ARCHIVE, $listUrl], [$topic->getVar('topic_title', 'n')]]));
	$icmsTpl->assign('imforum_archive_topic', [
		'title' => $topic->getVar('topic_title'),
		'url' => mod_imforum_View::link('topic', ['topic' => $topicId]),
	]);
	$icmsTpl->assign('imforum_archive_posts', $posts);
} else {
	$perPage = mod_imforum_Settings::getCount('topics_per_page');
	$start = mod_imforum_View::intParam('start');
	$page = $archiveHandler->getTopicsPage($permissions->getReadableForumIds(), $start, $perPage);
	$topics = $topicHandler->getByIds(array_column($page['rows'], 'topic_id'));
	$rows = [];
	foreach ($page['rows'] as $row) {
		if (!isset($topics[$row['topic_id']])) {
			continue;
		}
		$rows[] = [
			'url' => mod_imforum_View::link('archive', ['topic' => $row['topic_id']]),
			'title' => $topics[$row['topic_id']]->getVar('topic_title'),
			'posts' => $row['posts'],
			'archived' => mod_imforum_View::time($row['archived_time']),
		];
	}
	$icmsTpl->assign('imforum_breadcrumb', mod_imforum_View::breadcrumb([[_MD_IMFORUM_ARCHIVE]]));
	$icmsTpl->assign('imforum_archive_topics', $rows);
	$icmsTpl->assign('imforum_pager', mod_imforum_View::pager('archive', [], $page['total'], $perPage, $start));
}

include_once ICMS_ROOT_PATH . '/footer.php';
