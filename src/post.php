<?php
/**
 * Start a topic, reply, edit or delete a post
 *
 * One script, four modes: forum=N starts a topic, topic=N replies (post=M
 * quotes or answers a post), do=edit&post=N edits, do=delete&post=N asks for
 * confirmation. Everything that writes is a POST with a security token.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

include_once dirname(__DIR__, 2) . '/mainfile.php';
include_once __DIR__ . '/include/common.php';

$permissions = mod_imforum_PermissionService::forCurrentUser();
$forumHandler = icms_getModuleHandler('forum', IMFORUM_DIRNAME);
$topicHandler = icms_getModuleHandler('topic', IMFORUM_DIRNAME);
$postHandler = icms_getModuleHandler('post', IMFORUM_DIRNAME);

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$request = $isPost ? $_POST : $_GET;
$param = static fn (string $name): int => is_scalar($request[$name] ?? null) ? max(0, (int) $request[$name]) : 0;
$do = is_string($request['do'] ?? null) ? $request['do'] : '';

// what the request is about: a post to change, a topic to answer or a forum to start in
$post = null;
$topic = null;
$mode = 'new';
if ($param('post') > 0 && in_array($do, ['edit', 'delete'], true)) {
	$post = $postHandler->get($param('post'));
	$topic = $post->isNew() ? null : $topicHandler->get((int) $post->getVar('topic_id', 'e'));
	$mode = $do;
} elseif ($param('topic') > 0) {
	$topic = $topicHandler->get($param('topic'));
	$mode = 'reply';
}
if ($topic !== null && $topic->isNew()) {
	$topic = null;
}
$forumId = $topic !== null ? (int) $topic->getVar('forum_id', 'e') : $param('forum');
$forum = $permissions->getForum($forumId);
$back = mod_imforum_View::url($topic !== null ? 'topic' : 'index', $topic !== null ? ['topic' => (int) $topic->id()] : []);

if ($forum === null || ($mode !== 'new' && $topic === null) || ($mode === 'delete' || $mode === 'edit') && $post === null) {
	redirect_header(mod_imforum_View::url('index'), 3, _NOPERM);
	exit;
}

$allowed = match ($mode) {
	'new' => $permissions->canStartTopic($forum),
	'reply' => $permissions->canReply($forum, $topic),
	'edit' => $permissions->canEdit($forum, $topic, $post),
	default => $permissions->canDelete($forum, $topic, $post),
};
if (!$allowed) {
	if ($permissions->getUid() === 0) {
		redirect_header(ICMS_URL . '/user.php?xoops_redirect=' . rawurlencode($_SERVER['REQUEST_URI'] ?? ''), 3, _NOPERM);
	} else {
		redirect_header($topic !== null ? $back : mod_imforum_View::url('forum', ['forum' => $forumId]), 3, _NOPERM);
	}
	exit;
}

$service = new mod_imforum_PostService($permissions);
$errors = [];
$captcha = $permissions->getUid() === 0 && (int) mod_imforum_Settings::get('captcha_guests') === 1;
$preview = null;
$values = [];

// deleting: the confirmation is a form, the delete itself a POST with a token
if ($mode === 'delete' && $isPost) {
	if (!icms::$security->check()) {
		redirect_header($back, 3, implode('<br />', icms::$security->getErrors()));
		exit;
	}
	$outcome = $service->delete($forum, $topic, $post);
	if (!$outcome['ok']) {
		redirect_header($back, 3, implode('<br />', $outcome['errors']));
		exit;
	}
	redirect_header($outcome['topic_gone'] ? mod_imforum_View::url('forum', ['forum' => $forumId]) : $back, 2, _MD_IMFORUM_POST_DELETED);
	exit;
}

// saving or previewing
if ($isPost) {
	$values = $_POST;
	if (!icms::$security->check()) {
		$errors = array_merge($errors, (array) icms::$security->getErrors());
	} elseif ($captcha && !icms_form_elements_captcha_Object::instance()->verify(true)) {
		$errors[] = icms_form_elements_captcha_Object::instance()->getMessage();
	} elseif (isset($_POST['preview_button'])) {
		$preview = true;
	} else {
		if ($mode === 'new') {
			$outcome = $service->createTopic($forum, $_POST);
		} elseif ($mode === 'reply') {
			$outcome = $service->reply($forum, $topic, $_POST);
		} else {
			$outcome = $service->edit($forum, $topic, $post, $_POST);
		}
		if (!$outcome['errors']) {
			$saved = $outcome['post'];
			$remove = isset($_POST['remove_attachment']) && is_array($_POST['remove_attachment']) ? array_map('intval', $_POST['remove_attachment']) : [];
			$attachErrors = $service->handleAttachments($forum, $saved, $remove);
			$target = mod_imforum_View::url('topic', ['topic' => (int) $outcome['topic']->id(), 'post' => (int) $saved->id()]) . '#post-' . (int) $saved->id();
			if ($outcome['pending']) {
				$target = mod_imforum_View::url('forum', ['forum' => $forumId]);
			}
			$message = $outcome['pending'] ? _MD_IMFORUM_POST_PENDING : _MD_IMFORUM_POST_SAVED;
			redirect_header($target, $attachErrors ? 5 : 2, implode('<br />', array_merge([$message], $attachErrors)));
			exit;
		}
		$errors = $outcome['errors'];
	}
}

// the values the form starts with: the request after an error, otherwise the post or a quote
if (!$values) {
	$values = ['subject' => '', 'post_text' => '', 'attachsig' => 1, 'dosmiley' => 1, 'doxcode' => 1, 'doimage' => 1, 'dobr' => 1, 'post_karma' => 0, 'require_reply' => 0, 'prefix' => 0];
	if ($mode === 'edit') {
		foreach (['subject', 'post_text', 'attachsig', 'post_karma', 'require_reply', 'dohtml', 'dosmiley', 'doxcode', 'doimage', 'dobr'] as $field) {
			$values[$field] = $post->getVar($field, 'n');
		}
		$values['prefix'] = (int) $topic->getVar('topic_subject', 'e');
	} elseif ($mode === 'reply') {
		$parent = $param('post') > 0 ? $postHandler->get($param('post')) : null;
		$values['subject'] = preg_replace('/^(Re:\s*)+/i', '', (string) $topic->getVar('topic_title', 'n'));
		$values['subject'] = 'Re: ' . $values['subject'];
		if ($parent !== null && !$parent->isNew() && (int) $parent->getVar('topic_id', 'e') === (int) $topic->id() && (int) $parent->getVar('approved', 'e') === 1) {
			$values['pid'] = (int) $parent->id();
			// a quote of a locked post would hand out the text the reader may not see
			$karma = new mod_imforum_KarmaService($permissions->getUid());
			if (!isset($_GET['quote']) || $karma->getLock($parent, $permissions->isModerator($forumId)) === mod_imforum_KarmaService::LOCK_NONE) {
				if (isset($_GET['quote'])) {
					$author = (int) $parent->getVar('uid', 'e') > 0 ? (string) icms_member_user_Handler::getUserLink((int) $parent->getVar('uid', 'e')) : (string) $parent->getVar('poster_name', 'n');
					$author = trim(html_entity_decode(strip_tags($author), ENT_QUOTES, 'UTF-8'));
					$author = $author !== '' ? $author : _MD_IMFORUM_ANONYMOUS;
					$values['post_text'] = '[quote]' . sprintf(_MD_IMFORUM_QUOTE_WROTE, $author) . "\n" . (string) $parent->getVar('post_text', 'n') . "[/quote]\n";
				}
			}
		}
	}
}

$xoopsOption['template_main'] = 'imforum_post.html';
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
	$trail[] = [$topic->getVar('topic_title', 'n'), mod_imforum_View::url('topic', ['topic' => (int) $topic->id()])];
}
$trail[] = [match ($mode) {
	'new' => _MD_IMFORUM_NEW_TOPIC,
	'reply' => _MD_IMFORUM_REPLY,
	'edit' => _MD_IMFORUM_EDIT,
	default => _MD_IMFORUM_DELETE,
}];

$icmsTpl->assign('imforum_breadcrumb', mod_imforum_View::breadcrumb($trail));
$icmsTpl->assign('imforum_mode', $mode);
$icmsTpl->assign('imforum_errors', $errors);
$icmsTpl->assign('imforum_back', mod_imforum_View::escape($back));
$disclaimer = (int) mod_imforum_Settings::get('disclaimer_show') === 1 ? trim((string) mod_imforum_Settings::get('disclaimer')) : '';
$icmsTpl->assign('imforum_disclaimer', $disclaimer !== '' ? icms_core_DataFilter::filterTextareaDisplay($disclaimer, 0, 1, 0, 1) : '');

if ($mode === 'delete') {
	$icmsTpl->assign('imforum_delete', [
		'subject' => $post->getVar('subject'),
		'action' => mod_imforum_View::link('post'),
		'token' => icms::$security->getTokenHTML(),
		'post' => (int) $post->id(),
		'topic_deleted' => (int) $topic->getVar('topic_replies', 'e') === 0 && (int) $topic->getVar('topic_first_post_id', 'e') === (int) $post->id(),
	]);
} else {
	if ($preview) {
		$sample = $postHandler->create();
		foreach (['subject', 'post_text'] as $field) {
			$sample->setVar($field, is_string($values[$field] ?? null) ? $values[$field] : '');
		}
		$sample->setVar('dohtml', (int) $forum->getVar('allow_html', 'e') === 1 && !empty($values['dohtml']) ? 1 : 0);
		foreach (['dosmiley', 'doxcode', 'doimage', 'dobr'] as $field) {
			$sample->setVar($field, !empty($values[$field]) ? 1 : 0);
		}
		$sample->unlock();
		$icmsTpl->assign('imforum_preview', $sample->toArray());
	}

	$post_form = $postHandler->create();
	$form = mod_imforum_PostForm::build($post_form, $forum, $topic, $post, $mode, $values, $permissions, $captcha);
	$icmsTpl->assign('imforum_form', $form->render());
}

include_once ICMS_ROOT_PATH . '/footer.php';
