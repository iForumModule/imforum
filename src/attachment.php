<?php
/**
 * Download an attachment
 *
 * The files sit in uploads/imforum under random names and are not reachable
 * directly. This script checks that the reader may read the post, that the
 * karma lock is open, counts the download and sends the file. Everything but
 * a few picture types is sent as a download, never rendered by the browser.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

include_once dirname(__DIR__, 2) . '/mainfile.php';
include_once __DIR__ . '/include/common.php';

$permissions = mod_imforum_PermissionService::forCurrentUser();
$attachment = icms_getModuleHandler('attachment', IMFORUM_DIRNAME)->get(mod_imforum_View::intParam('id'));
$post = $attachment->isNew() ? null : icms_getModuleHandler('post', IMFORUM_DIRNAME)->get((int) $attachment->getVar('post_id', 'e'));
$forum = ($post !== null && !$post->isNew()) ? $permissions->getForum((int) $post->getVar('forum_id', 'e')) : null;

$allowed = $forum !== null
	&& $permissions->canReadForum($forum)
	&& (int) $attachment->getVar('online', 'e') === 1
	&& ((int) $post->getVar('approved', 'e') === 1 || $permissions->isModerator((int) $forum->id()));
if ($allowed) {
	$karma = new mod_imforum_KarmaService($permissions->getUid());
	$allowed = $karma->getLock($post, $permissions->isModerator((int) $forum->id())) === mod_imforum_KarmaService::LOCK_NONE;
}
$path = $allowed ? $attachment->getFilePath() : '';
if (!$allowed || !is_file($path)) {
	http_response_code($allowed ? 404 : 403);
	header('Content-Type: text/plain; charset=utf-8');
	echo $allowed ? 'Not found' : _NOPERM;
	exit;
}

icms_getModuleHandler('attachment', IMFORUM_DIRNAME)->incrementDownloads((int) $attachment->id());

$name = (string) $attachment->getVar('name_display', 'n');
$safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'attachment';
$mime = (string) $attachment->getVar('mimetype', 'e');
$inline = in_array($mime, ['image/gif', 'image/jpeg', 'image/png', 'image/webp'], true) && isset($_GET['inline']);

while (ob_get_level() > 0) {
	ob_end_clean();
}
header('Content-Type: ' . ($inline ? $mime : 'application/octet-stream'));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $safeName . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('Content-Length: ' . (int) filesize($path));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
readfile($path);
exit;
