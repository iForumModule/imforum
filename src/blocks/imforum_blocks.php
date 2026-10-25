<?php
/**
 * Blocks: the latest topics, the busiest topics and the most active members
 *
 * The blocks show only what the visitor may read.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';
icms_loadLanguageFile(IMFORUM_DIRNAME, 'main');

/**
 * Rows of topics for a block
 *
 * @param list<int> $topicIds
 * @return list<array<string, mixed>>
 */
function imforum_block_topic_rows(array $topicIds, mod_imforum_PermissionService $permissions): array {
	$topics = icms_getModuleHandler('topic', IMFORUM_DIRNAME)->getByIds($topicIds);
	$summaries = icms_getModuleHandler('post', IMFORUM_DIRNAME)->getSummaries(array_map(
		static fn (mod_imforum_Topic $topic): int => (int) $topic->getVar('topic_last_post_id', 'e'),
		$topics
	));
	$rows = [];
	foreach ($topics as $topicId => $topic) {
		$lastPostId = (int) $topic->getVar('topic_last_post_id', 'e');
		$forum = $permissions->getForum((int) $topic->getVar('forum_id', 'e'));
		$rows[] = [
			'url' => mod_imforum_View::link('topic', ['topic' => $topicId, 'post' => $lastPostId]) . '#post-' . $lastPostId,
			'title' => $topic->getVar('topic_title'),
			'forum' => $forum === null ? '' : $forum->getVar('forum_name'),
			'replies' => (int) $topic->getVar('topic_replies', 'e'),
			'time' => mod_imforum_View::time(isset($summaries[$lastPostId]) ? $summaries[$lastPostId]['post_time'] : (int) $topic->getVar('topic_time', 'e')),
		];
	}
	return $rows;
}

/**
 * @param array<int, string|int> $options 0: number of topics
 * @return array<string, mixed>|false
 */
function imforum_block_recent($options) {
	$permissions = mod_imforum_PermissionService::forCurrentUser();
	$found = icms_getModuleHandler('topic', IMFORUM_DIRNAME)->findRecent($permissions->getReadableForumIds(), 0, max(1, (int) ($options[0] ?? 10)));
	$rows = imforum_block_topic_rows($found['ids'], $permissions);
	return $rows ? ['topics' => $rows, 'all_url' => mod_imforum_View::link('viewall')] : false;
}

/**
 * @param array<int, string|int> $options 0: number of topics, 1: days to look back
 * @return array<string, mixed>|false
 */
function imforum_block_hot($options) {
	$permissions = mod_imforum_PermissionService::forCurrentUser();
	$days = max(1, (int) ($options[1] ?? 30));
	$ids = icms_getModuleHandler('topic', IMFORUM_DIRNAME)->findHot($permissions->getReadableForumIds(), time() - $days * 86400, max(1, (int) ($options[0] ?? 10)));
	$rows = imforum_block_topic_rows($ids, $permissions);
	return $rows ? ['topics' => $rows, 'all_url' => mod_imforum_View::link('viewall')] : false;
}

/**
 * @param array<int, string|int> $options 0: number of members
 * @return array<string, mixed>|false
 */
function imforum_block_topposters($options) {
	$permissions = mod_imforum_PermissionService::forCurrentUser();
	$rows = [];
	foreach (icms_getModuleHandler('post', IMFORUM_DIRNAME)->getTopPosters($permissions->getReadableForumIds(), max(1, (int) ($options[0] ?? 10))) as $poster) {
		$rows[] = ['member' => mod_imforum_View::poster($poster['uid'], ''), 'posts' => $poster['posts']];
	}
	return $rows ? ['posters' => $rows] : false;
}

/**
 * @param array<int, string|int> $options
 */
function imforum_block_edit_recent($options): string {
	return '<label>' . _MI_IMFORUM_BLOCK_OPT_COUNT . ' <input type="text" name="options[0]" value="' . (int) ($options[0] ?? 10) . '" size="3" /></label>';
}

/**
 * @param array<int, string|int> $options
 */
function imforum_block_edit_hot($options): string {
	return '<label>' . _MI_IMFORUM_BLOCK_OPT_COUNT . ' <input type="text" name="options[0]" value="' . (int) ($options[0] ?? 10) . '" size="3" /></label><br />'
		. '<label>' . _MI_IMFORUM_BLOCK_OPT_DAYS . ' <input type="text" name="options[1]" value="' . (int) ($options[1] ?? 30) . '" size="3" /></label>';
}
