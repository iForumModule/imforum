<?php
/**
 * RSS feed of the latest topics, for what a guest may read
 *
 * The feed is the same for everybody and may be cached by readers and proxies,
 * so it never contains anything a guest could not see: forums without guest
 * access are left out and so are the texts of posts that ask for karma or a
 * reply.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

include_once dirname(__DIR__, 2) . '/mainfile.php';
include_once __DIR__ . '/include/common.php';

$permissions = mod_imforum_PermissionService::forGuest();
$forumIds = $permissions->getReadableForumIds();
$forumId = mod_imforum_View::intParam('forum');
if ($forumId > 0) {
	$forumIds = in_array($forumId, $forumIds, true) ? [$forumId] : [];
}

$topicHandler = icms_getModuleHandler('topic', IMFORUM_DIRNAME);
$postHandler = icms_getModuleHandler('post', IMFORUM_DIRNAME);
$found = $topicHandler->findRecent($forumIds, 0, mod_imforum_Settings::getCount('rss_items'));
$topics = $topicHandler->getByIds($found['ids']);
$posts = $postHandler->getByIds(array_map(static fn (mod_imforum_Topic $topic): int => (int) $topic->getVar('topic_last_post_id', 'e'), $topics));
$lastPosts = [];
foreach ($posts as $post) {
	$lastPosts[(int) $post->getVar('topic_id', 'e')] = $post;
}

/** Text for an XML element: no control characters, escaped */
$xml = static fn (string $text): string => htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text) ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');

$site = (string) ($GLOBALS['icmsConfig']['sitename'] ?? '');
$feedTitle = $site . ' - ' . _MD_IMFORUM_TITLE;
if ($forumId > 0 && count($forumIds) === 1) {
	$feedTitle .= ' - ' . (string) $permissions->getForum($forumId)?->getVar('forum_name', 'n');
}

$items = '';
foreach ($topics as $topicId => $topic) {
	$post = $lastPosts[$topicId] ?? null;
	$link = mod_imforum_View::url('topic', ['topic' => $topicId, 'post' => $post ? (int) $post->id() : 0]);
	$data = $post ? $post->toArray() : null;
	$excerpt = $data ? trim(html_entity_decode(strip_tags((string) $data['text']), ENT_QUOTES, 'UTF-8')) : '';
	$items .= "\t<item>\n"
		. "\t\t<title>" . $xml((string) $topic->getVar('topic_title', 'n')) . "</title>\n"
		. "\t\t<link>" . $xml($link) . "</link>\n"
		. "\t\t<guid isPermaLink=\"true\">" . $xml($link) . "</guid>\n"
		. ($data ? "\t\t<pubDate>" . gmdate('D, d M Y H:i:s', (int) $data['post_time']) . " GMT</pubDate>\n" : '')
		. "\t\t<description>" . $xml(function_exists('mb_substr') ? mb_substr($excerpt, 0, 300) : substr($excerpt, 0, 300)) . "</description>\n"
		. "\t</item>\n";
}

header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
	. "<rss version=\"2.0\">\n<channel>\n"
	. "\t<title>" . $xml($feedTitle) . "</title>\n"
	. "\t<link>" . $xml(mod_imforum_View::url('index')) . "</link>\n"
	. "\t<description>" . $xml($feedTitle) . "</description>\n"
	. $items
	. "</channel>\n</rss>\n";
