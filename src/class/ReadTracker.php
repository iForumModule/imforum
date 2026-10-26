<?php
/**
 * What a member has read
 *
 * A member's mark on a topic holds the last post seen; a mark on a forum
 * ("mark all as read") holds the last post of the forum at that moment. Marks
 * only move forward. Guests have no marks: nothing is new for them.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_ReadTracker {

	private int $uid;

	private mod_imforum_ReadmarkHandler $marks;

	public function __construct(int $uid) {
		$this->uid = $uid;
		$this->marks = icms_getModuleHandler('readmark', IMFORUM_DIRNAME);
	}

	public function isTracking(): bool {
		return $this->uid > 0;
	}

	/**
	 * Which of these topics have posts the member has not seen
	 *
	 * @param array<int, array{forum_id: int, last_post_id: int}> $topics by topic id
	 * @return array<int, true> topic ids
	 */
	public function getUnreadTopics(array $topics): array {
		if (!$this->isTracking() || !$topics) {
			return [];
		}
		$topicReads = $this->marks->getLastRead(mod_imforum_ReadmarkHandler::TYPE_TOPIC, $this->uid, array_keys($topics));
		$forumIds = array_unique(array_column($topics, 'forum_id'));
		$forumReads = $this->marks->getLastRead(mod_imforum_ReadmarkHandler::TYPE_FORUM, $this->uid, $forumIds);

		$unread = [];
		foreach ($topics as $topicId => $topic) {
			$seen = max($topicReads[$topicId] ?? 0, $forumReads[$topic['forum_id']] ?? 0);
			if ($topic['last_post_id'] > $seen) {
				$unread[$topicId] = true;
			}
		}
		return $unread;
	}

	/**
	 * @param list<int> $forumIds
	 * @return array<int, int> forum id => unread topics
	 */
	public function countUnreadByForum(array $forumIds): array {
		return $this->marks->countUnreadByForum($this->uid, $forumIds);
	}

	public function markTopicRead(int $topicId, int $upToPostId): void {
		if (!$this->isTracking() || $upToPostId < 1) {
			return;
		}
		$seen = $this->marks->getLastRead(mod_imforum_ReadmarkHandler::TYPE_TOPIC, $this->uid, [$topicId])[$topicId] ?? 0;
		if ($upToPostId > $seen) {
			$this->marks->markRead(mod_imforum_ReadmarkHandler::TYPE_TOPIC, $this->uid, $topicId, $upToPostId);
		}
	}

	/**
	 * Mark everything in a forum as read, and forget the marks on its topics
	 */
	public function markForumRead(int $forumId, int $lastPostId): void {
		if (!$this->isTracking()) {
			return;
		}
		$this->marks->markRead(mod_imforum_ReadmarkHandler::TYPE_FORUM, $this->uid, $forumId, $lastPostId);
		$this->marks->deleteTopicMarksInForum($this->uid, $forumId);
	}
}
