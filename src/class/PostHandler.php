<?php
/**
 * Handler for posts
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_PostHandler extends icms_ipf_Handler {

	public function __construct(icms_db_legacy_Database $db) {
		parent::__construct($db, 'post', 'post_id', 'subject', 'post_text', IMFORUM_DIRNAME);
	}

	/**
	 * Delete all posts of a topic, with their attachments and reports
	 *
	 * Used when a whole topic goes. One DELETE instead of one per post, and no
	 * recount per post: the topic handler recounts the forum once afterwards.
	 */
	public function deleteByTopic(int $topicId): bool {
		$postIds = [];
		$result = $this->db->queryF("SELECT post_id FROM {$this->table} WHERE topic_id = $topicId");
		while ($result && ($row = $this->db->fetchRow($result))) {
			$postIds[] = (int) $row[0];
		}
		if (!$postIds) {
			return true;
		}
		foreach (array_chunk($postIds, 500) as $chunk) {
			$this->deletePostChildren($chunk);
		}
		return (bool) $this->db->queryF("DELETE FROM {$this->table} WHERE topic_id = $topicId");
	}

	/**
	 * Attachments (with their files) and reports of these posts
	 *
	 * @param list<int> $postIds
	 */
	private function deletePostChildren(array $postIds): void {
		$byPost = new icms_db_criteria_Item('post_id', '(' . implode(',', array_map('intval', $postIds)) . ')', 'IN');
		icms_getModuleHandler('attachment', IMFORUM_DIRNAME)->deleteAll($byPost);
		icms_getModuleHandler('report', IMFORUM_DIRNAME)->deleteAll($byPost);
	}

	protected function beforeInsert(mod_imforum_Post $post): bool {
		if ((int) $post->getVar('post_time', 'e') === 0) {
			$post->setVar('post_time', time());
		}
		if ((int) $post->getVar('forum_id', 'e') === 0) {
			$topic = icms_getModuleHandler('topic', IMFORUM_DIRNAME)->get((int) $post->getVar('topic_id', 'e'));
			if ($topic->isNew()) {
				$post->setErrors(_CO_IMFORUM_POST_NO_TOPIC);
				return false;
			}
			$post->setVar('forum_id', (int) $topic->getVar('forum_id', 'e'));
		}
		return true;
	}

	protected function afterSave(mod_imforum_Post $post): bool {
		return $this->synchroniseParents($post);
	}

	/**
	 * Clean up after one post
	 *
	 * Replies to the post move up to its parent, so the tree view stays whole.
	 * A topic without posts is deleted.
	 */
	protected function afterDelete(mod_imforum_Post $post): bool {
		$postId = (int) $post->id();
		$this->deletePostChildren([$postId]);
		$this->db->queryF(sprintf(
			'UPDATE %s SET pid = %d WHERE pid = %d',
			$this->table, (int) $post->getVar('pid', 'e'), $postId
		));

		$topicId = (int) $post->getVar('topic_id', 'e');
		if ($this->getCount(new icms_db_criteria_Item('topic_id', $topicId)) === 0) {
			$topicHandler = icms_getModuleHandler('topic', IMFORUM_DIRNAME);
			$topic = $topicHandler->get($topicId);
			if (!$topic->isNew()) {
				return $topicHandler->delete($topic, true);
			}
		}
		return $this->synchroniseParents($post);
	}

	private function synchroniseParents(mod_imforum_Post $post): bool {
		$topicOk = icms_getModuleHandler('topic', IMFORUM_DIRNAME)->synchronise((int) $post->getVar('topic_id', 'e'));
		$forumOk = icms_getModuleHandler('forum', IMFORUM_DIRNAME)->synchronise((int) $post->getVar('forum_id', 'e'));
		return $topicOk && $forumOk;
	}
}
