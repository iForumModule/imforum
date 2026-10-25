<?php
/**
 * Rows for the lists of topics on the forum page and the overview pages
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_TopicList {

	/** How many page links follow a topic title */
	private const PAGE_LINKS = 5;

	/**
	 * @param array<int, mod_imforum_Topic> $topics by topic id
	 * @param array<int, mod_imforum_Forum> $forums by forum id; the forums the topics are in
	 * @return list<array<string, mixed>> every text is ready for output
	 */
	public static function rows(array $topics, array $forums, mod_imforum_ReadTracker $tracker, bool $showForum): array {
		if (!$topics) {
			return [];
		}
		$lastPostIds = [];
		$progress = [];
		foreach ($topics as $topicId => $topic) {
			$lastPostIds[] = (int) $topic->getVar('topic_last_post_id', 'e');
			$progress[$topicId] = [
				'forum_id' => (int) $topic->getVar('forum_id', 'e'),
				'last_post_id' => (int) $topic->getVar('topic_last_post_id', 'e'),
			];
		}
		$summaries = icms_getModuleHandler('post', IMFORUM_DIRNAME)->getSummaries($lastPostIds);
		$unread = $tracker->getUnreadTopics($progress);
		$perPage = mod_imforum_Settings::getCount('posts_per_page');

		$rows = [];
		foreach ($topics as $topicId => $topic) {
			$forum = $forums[(int) $topic->getVar('forum_id', 'e')] ?? null;
			$replies = (int) $topic->getVar('topic_replies', 'e');
			$lastPostId = (int) $topic->getVar('topic_last_post_id', 'e');
			$last = $summaries[$lastPostId] ?? null;

			$pages = [];
			$pageCount = (int) ceil(($replies + 1) / $perPage);
			for ($page = 2; $page <= min($pageCount, self::PAGE_LINKS); $page++) {
				$pages[] = ['number' => $page, 'url' => mod_imforum_View::link('topic', ['topic' => $topicId, 'start' => ($page - 1) * $perPage])];
			}

			$rows[] = [
				'id' => $topicId,
				'url' => mod_imforum_View::link('topic', ['topic' => $topicId]),
				'title' => $topic->getVar('topic_title'),
				'poster' => mod_imforum_View::poster((int) $topic->getVar('topic_poster', 'e'), (string) $topic->getVar('poster_name', 'n')),
				'time' => mod_imforum_View::time((int) $topic->getVar('topic_time', 'e')),
				'replies' => $replies,
				'views' => (int) $topic->getVar('topic_views', 'e'),
				'sticky' => (int) $topic->getVar('topic_sticky', 'e') === 1,
				'digest' => (int) $topic->getVar('topic_digest', 'e') === 1,
				'locked' => $topic->isLocked(),
				'hot' => $forum !== null && $replies >= max(1, (int) $forum->getVar('hot_threshold', 'e')),
				'unread' => isset($unread[$topicId]),
				'pages' => count($pages) ? $pages : [],
				'more_pages' => $pageCount > self::PAGE_LINKS,
				'last_url' => mod_imforum_View::link('topic', ['topic' => $topicId, 'post' => $lastPostId]) . '#post-' . $lastPostId,
				'last' => $last === null ? null : [
					'poster' => mod_imforum_View::poster($last['uid'], $last['poster_name']),
					'time' => mod_imforum_View::time($last['post_time']),
				],
				'forum' => ($showForum && $forum !== null) ? [
					'name' => $forum->getVar('forum_name'),
					'url' => mod_imforum_View::link('forum', ['forum' => (int) $forum->id()]),
				] : null,
			];
		}
		return $rows;
	}
}
