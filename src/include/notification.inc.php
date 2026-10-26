<?php
/**
 * Lookup for the core notification system
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once __DIR__ . '/common.php';

/**
 * The name and address of the item somebody subscribed to, for the mail
 *
 * @return array{name: string, url: string}
 */
function imforum_notify_iteminfo(string $category, int $itemId): array {
	switch ($category) {
		case 'forum':
			$forum = icms_getModuleHandler('forum', IMFORUM_DIRNAME)->get($itemId);
			if (!$forum->isNew()) {
				return ['name' => (string) $forum->getVar('forum_name', 'n'), 'url' => mod_imforum_View::url('forum', ['forum' => $itemId])];
			}
			break;
		case 'thread':
			$topic = icms_getModuleHandler('topic', IMFORUM_DIRNAME)->get($itemId);
			if (!$topic->isNew()) {
				return ['name' => (string) $topic->getVar('topic_title', 'n'), 'url' => mod_imforum_View::url('topic', ['topic' => $itemId])];
			}
			break;
	}
	return ['name' => '', 'url' => IMFORUM_URL];
}
