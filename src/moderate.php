<?php
/**
 * Moderation of a forum: topic actions, the queue of waiting posts and the reports
 *
 * Every action first shows a confirmation form (GET) and then does its work
 * on a POST with a security token. Lists: do=pending&forum=N and
 * do=reports&forum=N. Topic actions: do=lock|unlock|sticky|unsticky|digest|
 * undigest|move|merge|delete&topic=N. Post actions: do=split|approve|suspend
 * &post=N. do=handle&report=N closes a report.
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
$reportHandler = icms_getModuleHandler('report', IMFORUM_DIRNAME);

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$request = $isPost ? $_POST : $_GET;
$param = static fn (string $name): int => is_scalar($request[$name] ?? null) ? max(0, (int) $request[$name]) : 0;
$text = static fn (string $name): string => is_string($request[$name] ?? null) ? trim($request[$name]) : '';
$do = $text('do');

$topicActions = ['lock', 'unlock', 'sticky', 'unsticky', 'digest', 'undigest', 'move', 'merge', 'delete'];
$postActions = ['split', 'approve', 'suspend'];
$listActions = ['pending', 'reports'];

$topic = null;
$post = null;
$report = null;
if (in_array($do, $topicActions, true)) {
	$topic = $topicHandler->get($param('topic'));
	$forumId = $topic->isNew() ? 0 : (int) $topic->getVar('forum_id', 'e');
} elseif (in_array($do, $postActions, true)) {
	$post = $postHandler->get($param('post'));
	$topic = $post->isNew() ? null : $topicHandler->get((int) $post->getVar('topic_id', 'e'));
	$forumId = $post->isNew() ? 0 : (int) $post->getVar('forum_id', 'e');
} elseif ($do === 'handle') {
	$report = $reportHandler->get($param('report'));
	$reported = $report->isNew() ? null : $postHandler->get((int) $report->getVar('post_id', 'e'));
	$forumId = ($reported !== null && !$reported->isNew()) ? (int) $reported->getVar('forum_id', 'e') : 0;
} elseif (in_array($do, $listActions, true)) {
	$forumId = $param('forum');
} else {
	redirect_header(mod_imforum_View::url('index'), 3, _NOPERM);
	exit;
}

$forum = $permissions->getForum($forumId);
$orphanReport = $do === 'handle' && $report !== null && !$report->isNew() && $forum === null;
if (!$orphanReport && ($forum === null || !$permissions->canModerate($forum) || ($topic !== null && $topic->isNew()) || ($post !== null && $post->isNew()) || ($report !== null && $report->isNew()))) {
	redirect_header(mod_imforum_View::url($forum !== null ? 'forum' : 'index', $forum !== null ? ['forum' => $forumId] : []), 3, _NOPERM);
	exit;
}

$service = new mod_imforum_ModerationService($permissions);
$errors = [];
$topicId = $topic !== null ? (int) $topic->id() : 0;
$back = $topic !== null ? mod_imforum_View::url('topic', ['topic' => $topicId]) : mod_imforum_View::url('forum', ['forum' => $forumId]);
if ($do === 'handle' || $do === 'reports') {
	$back = mod_imforum_View::url('moderate', ['do' => 'reports', 'forum' => $forumId]);
}

// doing the work
if ($isPost && !in_array($do, $listActions, true)) {
	if (!icms::$security->check()) {
		$errors = array_merge($errors, (array) icms::$security->getErrors());
	} else {
		$message = _MD_IMFORUM_MOD_DONE;
		$target = $back;
		switch ($do) {
			case 'lock':
			case 'unlock':
				$outcome = $service->setLocked($topic, $do === 'lock');
				break;
			case 'sticky':
			case 'unsticky':
				$outcome = $service->setSticky($topic, $do === 'sticky');
				break;
			case 'digest':
			case 'undigest':
				$outcome = $service->setDigest($topic, $do === 'digest');
				break;
			case 'move':
				$outcome = $service->move($topic, $param('target_forum'));
				break;
			case 'merge':
				$outcome = $service->merge($topic, $topicHandler->get($param('target_topic')));
				if ($outcome['ok'] && $outcome['topic'] !== null) {
					$target = mod_imforum_View::url('topic', ['topic' => (int) $outcome['topic']->id()]);
				}
				break;
			case 'delete':
				$outcome = $service->deleteTopic($topic);
				$target = mod_imforum_View::url('forum', ['forum' => $forumId]);
				break;
			case 'split':
				$outcome = $service->split($post, $text('title'));
				if ($outcome['ok'] && $outcome['topic'] !== null) {
					$target = mod_imforum_View::url('topic', ['topic' => (int) $outcome['topic']->id()]);
				}
				break;
			case 'approve':
				$outcome = $service->approve($post);
				$target = mod_imforum_View::url('moderate', ['do' => 'pending', 'forum' => $forumId]);
				break;
			case 'suspend':
				$outcome = $service->suspend($forum, (int) $post->getVar('uid', 'e'), $param('days'), $text('description'));
				$message = _MD_IMFORUM_MOD_SUSPENDED;
				break;
			default:
				$outcome = $service->handleReport($report, $text('memo'));
				break;
		}
		if ($outcome['ok']) {
			redirect_header($target, 2, $message);
			exit;
		}
		$errors = $outcome['errors'];
	}
}

$xoopsOption['template_main'] = 'imforum_moderate.html';
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
if ($topic !== null) {
	$trail[] = [$topic->getVar('topic_title', 'n'), mod_imforum_View::url('topic', ['topic' => $topicId])];
}
$trail[] = [constant('_MD_IMFORUM_MOD_' . strtoupper($do))];

$icmsTpl->assign('imforum_breadcrumb', mod_imforum_View::breadcrumb($trail));
$icmsTpl->assign('imforum_errors', $errors);
$icmsTpl->assign('imforum_back', mod_imforum_View::escape($back));
$icmsTpl->assign('imforum_mod_do', $do);
$icmsTpl->assign('imforum_mod_title', constant('_MD_IMFORUM_MOD_' . strtoupper($do)));

if (in_array($do, $listActions, true)) {
	$rows = [];
	if ($do === 'pending') {
		$criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('forum_id', $forumId));
		$criteria->add(new icms_db_criteria_Item('approved', 0));
		$criteria->setSort('post_id');
		$criteria->setOrder('ASC');
		$criteria->setLimit(100);
		foreach ($postHandler->getObjects($criteria, true) as $waiting) {
			$waitingTopic = $topicHandler->get((int) $waiting->getVar('topic_id', 'e'));
			$rows[] = [
				'subject' => $waiting->getVar('subject'),
				'topic' => $waitingTopic->getVar('topic_title'),
				'author' => mod_imforum_View::poster((int) $waiting->getVar('uid', 'e'), (string) $waiting->getVar('poster_name', 'n')),
				'time' => mod_imforum_View::time((int) $waiting->getVar('post_time', 'e')),
				'excerpt' => mod_imforum_View::escape(icms_core_DataFilter::icms_substr(strip_tags((string) $waiting->getVar('post_text', 'n')), 0, 200)),
				'approve_url' => mod_imforum_View::link('moderate', ['do' => 'approve', 'post' => (int) $waiting->id()]),
				'delete_url' => mod_imforum_View::link('post', ['do' => 'delete', 'post' => (int) $waiting->id()]),
				'suspend_url' => (int) $waiting->getVar('uid', 'e') > 0 ? mod_imforum_View::link('moderate', ['do' => 'suspend', 'post' => (int) $waiting->id()]) : '',
			];
		}
	} else {
		$criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('report_result', mod_imforum_Report::RESULT_OPEN));
		$criteria->setSort('report_id');
		$criteria->setOrder('ASC');
		foreach ($reportHandler->getObjects($criteria, true) as $open) {
			$reportedPost = $postHandler->get((int) $open->getVar('post_id', 'e'));
			// only the reports of this forum; reports of posts that are gone belong to nobody
			if ($reportedPost->isNew() || (int) $reportedPost->getVar('forum_id', 'e') !== $forumId) {
				continue;
			}
			$rows[] = [
				'subject' => $reportedPost->getVar('subject'),
				'post_url' => mod_imforum_View::link('topic', ['topic' => (int) $reportedPost->getVar('topic_id', 'e'), 'post' => (int) $reportedPost->id()]) . '#post-' . (int) $reportedPost->id(),
				'reporter' => mod_imforum_View::poster((int) $open->getVar('reporter_uid', 'e'), ''),
				'time' => mod_imforum_View::time((int) $open->getVar('report_time', 'e')),
				'text' => $open->getVar('report_text'),
				'handle_url' => mod_imforum_View::link('moderate', ['do' => 'handle', 'report' => (int) $open->id()]),
			];
		}
	}
	$icmsTpl->assign('imforum_mod_rows', $rows);
} else {
	$form = [
		'action' => mod_imforum_View::link('moderate'),
		'token' => icms::$security->getTokenHTML(),
		'do' => $do,
		'topic' => $topicId,
		'post' => $post !== null ? (int) $post->id() : 0,
		'report' => $report !== null ? (int) $report->id() : 0,
		'subject' => $topic !== null ? $topic->getVar('topic_title') : ($post !== null ? $post->getVar('subject') : ''),
		'forums' => [],
		'author' => '',
		'text' => $report !== null ? $report->getVar('report_text') : '',
		'values' => [
			'title' => mod_imforum_View::escape($text('title') !== '' ? $text('title') : ($post !== null ? (string) $post->getVar('subject', 'n') : '')),
			'target_topic' => $param('target_topic') ?: '',
			'days' => $param('days') ?: 7,
			'description' => mod_imforum_View::escape($text('description')),
			'memo' => mod_imforum_View::escape($text('memo')),
		],
	];
	if ($do === 'move') {
		foreach ($permissions->getAllForums() as $id => $candidate) {
			if ((int) $id !== $forumId && $permissions->canModerate($candidate)) {
				$form['forums'][] = ['id' => (int) $id, 'name' => $candidate->getVar('forum_name')];
			}
		}
	}
	if ($do === 'suspend' && $post !== null) {
		$form['author'] = mod_imforum_View::poster((int) $post->getVar('uid', 'e'), '');
	}
	$icmsTpl->assign('imforum_mod_form', $form);
}

include_once ICMS_ROOT_PATH . '/footer.php';
