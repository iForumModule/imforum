<?php
/**
 * Topics of all forums at once: the latest, the unread and the featured
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

include_once dirname(__DIR__, 2) . '/mainfile.php';
include_once __DIR__ . '/include/common.php';

$permissions = mod_imforum_PermissionService::forCurrentUser();
$type = in_array($_GET['type'] ?? '', ['unread', 'digest'], true) ? (string) $_GET['type'] : '';
if ($type === 'unread' && $permissions->getUid() < 1) {
	redirect_header(ICMS_URL . '/user.php', 3, _NOPERM);
	exit;
}

$xoopsOption['template_main'] = 'imforum_viewall.html';
include_once ICMS_ROOT_PATH . '/header.php';
global $icmsTpl, $xoTheme;
$xoTheme->addStylesheet(IMFORUM_URL . 'assets/css/imforum.css');

$topicHandler = icms_getModuleHandler('topic', IMFORUM_DIRNAME);
$perPage = mod_imforum_Settings::getCount('topics_per_page');
$start = mod_imforum_View::intParam('start');
$found = $topicHandler->findRecent($permissions->getReadableForumIds(), $start, $perPage, $type, $permissions->getUid());
$tracker = new mod_imforum_ReadTracker($permissions->getUid());
$rows = mod_imforum_TopicList::rows($topicHandler->getByIds($found['ids']), $permissions->getAllForums(), $tracker, true);

$titles = ['' => _MD_IMFORUM_VIEWALL_LATEST, 'unread' => _MD_IMFORUM_VIEWALL_UNREAD, 'digest' => _MD_IMFORUM_VIEWALL_DIGEST];
$icmsTpl->assign('imforum_breadcrumb', mod_imforum_View::breadcrumb([[$titles[$type]]]));
$icmsTpl->assign('imforum_title', mod_imforum_View::escape($titles[$type]));
$icmsTpl->assign('imforum_topics', $rows);
$icmsTpl->assign('imforum_filters', [
	['title' => _MD_IMFORUM_VIEWALL_LATEST, 'url' => mod_imforum_View::link('viewall'), 'active' => $type === ''],
	['title' => _MD_IMFORUM_VIEWALL_UNREAD, 'url' => mod_imforum_View::link('viewall', ['type' => 'unread']), 'active' => $type === 'unread', 'members_only' => true],
	['title' => _MD_IMFORUM_VIEWALL_DIGEST, 'url' => mod_imforum_View::link('viewall', ['type' => 'digest']), 'active' => $type === 'digest'],
]);
$icmsTpl->assign('imforum_is_member', $permissions->getUid() > 0);
$icmsTpl->assign('imforum_pager', mod_imforum_View::pager('viewall', ['type' => $type], $found['total'], $perPage, $start));

include_once ICMS_ROOT_PATH . '/footer.php';
