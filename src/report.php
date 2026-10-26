<?php
/**
 * Report a post to the moderators of its forum
 *
 * report.php?post=N shows the form; the report itself is a POST with a
 * security token. Only members can report.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

include_once dirname(__DIR__, 2) . '/mainfile.php';
include_once __DIR__ . '/include/common.php';

$permissions = mod_imforum_PermissionService::forCurrentUser();
$postHandler = icms_getModuleHandler('post', IMFORUM_DIRNAME);
$topicHandler = icms_getModuleHandler('topic', IMFORUM_DIRNAME);

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$request = $isPost ? $_POST : $_GET;
$postId = is_scalar($request['post'] ?? null) ? max(0, (int) $request['post']) : 0;
$post = $postHandler->get($postId);
$topic = $post->isNew() ? null : $topicHandler->get((int) $post->getVar('topic_id', 'e'));
$forum = $post->isNew() ? null : $permissions->getForum((int) $post->getVar('forum_id', 'e'));

if ($permissions->getUid() === 0) {
	redirect_header(ICMS_URL . '/user.php?xoops_redirect=' . rawurlencode($_SERVER['REQUEST_URI'] ?? ''), 3, _NOPERM);
	exit;
}
if ($forum === null || $topic === null || $topic->isNew() || (int) $post->getVar('approved', 'e') !== 1 || !$permissions->canReport($forum)) {
	redirect_header(mod_imforum_View::url('index'), 3, _NOPERM);
	exit;
}
$back = mod_imforum_View::url('topic', ['topic' => (int) $topic->id(), 'post' => $postId]) . '#post-' . $postId;

$errors = [];
$reason = is_string($request['report_text'] ?? null) ? trim($request['report_text']) : '';
if ($isPost) {
	if (!icms::$security->check()) {
		$errors = array_merge($errors, (array) icms::$security->getErrors());
	} else {
		$outcome = (new mod_imforum_ModerationService($permissions))->report($post, $reason);
		if ($outcome['ok']) {
			redirect_header($back, 3, _MD_IMFORUM_REPORT_SENT);
			exit;
		}
		$errors = $outcome['errors'];
	}
}

$xoopsOption['template_main'] = 'imforum_moderate.html';
include_once ICMS_ROOT_PATH . '/header.php';
global $icmsTpl, $xoTheme;
$xoTheme->addStylesheet(IMFORUM_URL . 'assets/css/imforum.css');

$icmsTpl->assign('imforum_breadcrumb', mod_imforum_View::breadcrumb([
	[$forum->getVar('forum_name', 'n'), mod_imforum_View::url('forum', ['forum' => (int) $forum->id()])],
	[$topic->getVar('topic_title', 'n'), mod_imforum_View::url('topic', ['topic' => (int) $topic->id()])],
	[_MD_IMFORUM_REPORT],
]));
$icmsTpl->assign('imforum_errors', $errors);
$icmsTpl->assign('imforum_back', mod_imforum_View::escape($back));
$icmsTpl->assign('imforum_mod_do', 'report');
$icmsTpl->assign('imforum_mod_title', _MD_IMFORUM_REPORT);
$icmsTpl->assign('imforum_mod_form', [
	'action' => mod_imforum_View::link('report'),
	'token' => icms::$security->getTokenHTML(),
	'do' => 'report',
	'topic' => 0,
	'post' => $postId,
	'report' => 0,
	'subject' => $post->getVar('subject'),
	'forums' => [],
	'author' => '',
	'text' => '',
	'values' => ['report_text' => mod_imforum_View::escape($reason)],
]);

include_once ICMS_ROOT_PATH . '/footer.php';
