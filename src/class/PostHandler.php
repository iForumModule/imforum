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

	private function approvedIn(int $topicId): icms_db_criteria_Compo {
		$criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('topic_id', $topicId));
		$criteria->add(new icms_db_criteria_Item('approved', 1));
		return $criteria;
	}

	/**
	 * Approved posts written in a period, counted per topic, for the digest
	 *
	 * @return list<array{0: int, 1: int, 2: int, 3: int}> forum id, topic id, number of posts, id of the first new post; by forum, busiest topic first
	 */
	public function getActivity(int $since, int $until): array {
		$topics = $this->db->prefix(IMFORUM_DIRNAME . '_topic');
		$result = $this->db->queryF(
			'SELECT p.forum_id, p.topic_id, COUNT(*) AS n, MIN(p.post_id) AS first_new, MAX(p.post_id) AS last_new'
			. " FROM {$this->table} p JOIN $topics t ON t.topic_id = p.topic_id"
			. ' WHERE p.approved = 1 AND t.approved = 1 AND p.post_time >= ' . $since . ' AND p.post_time < ' . $until
			. ' GROUP BY p.forum_id, p.topic_id ORDER BY p.forum_id, last_new DESC'
		);
		$rows = [];
		while ($result && ($row = $this->db->fetchRow($result))) {
			$rows[] = [(int) $row[0], (int) $row[1], (int) $row[2], (int) $row[3]];
		}
		return $rows;
	}

	/**
	 * One page of the approved posts of a topic, oldest first
	 *
	 * @return array<int, mod_imforum_Post> by post id
	 */
	public function getPage(int $topicId, int $start, int $limit): array {
		$criteria = $this->approvedIn($topicId);
		$criteria->setSort('post_id');
		$criteria->setOrder('ASC');
		$criteria->setStart($start);
		$criteria->setLimit($limit);
		return $this->getObjects($criteria, true);
	}

	/**
	 * Posts of a forum that wait for a moderator
	 */
	public function countPending(int $forumId): int {
		$criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('forum_id', $forumId));
		$criteria->add(new icms_db_criteria_Item('approved', 0));
		return (int) $this->getCount($criteria);
	}

	public function countApproved(int $topicId): int {
		return (int) $this->getCount($this->approvedIn($topicId));
	}

	/**
	 * The approved posts of a topic in thread order: every reply under the post it answers
	 *
	 * A reply whose parent is missing or not approved counts as a first level
	 * post, so nothing disappears from the thread.
	 *
	 * @return list<array{post_id: int, depth: int}>
	 */
	public function getThreadOrder(int $topicId): array {
		$parentOf = [];
		$result = $this->db->queryF("SELECT post_id, pid FROM {$this->table} WHERE topic_id = $topicId AND approved = 1 ORDER BY post_id");
		while ($result && ($row = $this->db->fetchRow($result))) {
			$parentOf[(int) $row[0]] = (int) $row[1];
		}

		$children = [];
		foreach ($parentOf as $postId => $parent) {
			$children[isset($parentOf[$parent]) && $parent !== $postId ? $parent : 0][] = $postId;
		}

		$order = [];
		$seen = [];
		$stack = [];
		foreach (array_reverse($children[0] ?? []) as $root) {
			$stack[] = [$root, 0];
		}
		while ($stack) {
			[$postId, $depth] = array_pop($stack);
			if (isset($seen[$postId])) {
				continue;
			}
			$seen[$postId] = true;
			$order[] = ['post_id' => $postId, 'depth' => $depth];
			foreach (array_reverse($children[$postId] ?? []) as $child) {
				$stack[] = [$child, $depth + 1];
			}
		}
		// posts that only point at each other form a loop without a root
		foreach (array_diff_key($parentOf, $seen) as $postId => $parent) {
			$order[] = ['post_id' => $postId, 'depth' => 0];
		}
		return $order;
	}

	/**
	 * Posts by id, in the order of the ids; unknown ids are left out
	 *
	 * @param list<int> $postIds
	 * @return array<int, mod_imforum_Post>
	 */
	public function getByIds(array $postIds): array {
		$postIds = array_values(array_filter(array_map('intval', $postIds)));
		if (!$postIds) {
			return [];
		}
		$found = $this->getObjects(new icms_db_criteria_Item('post_id', '(' . implode(',', $postIds) . ')', 'IN'), true);
		$ordered = [];
		foreach ($postIds as $postId) {
			if (isset($found[$postId])) {
				$ordered[$postId] = $found[$postId];
			}
		}
		return $ordered;
	}

	/**
	 * Number of approved posts per member
	 *
	 * @param list<int> $uids
	 * @return array<int, int> uid => posts; members without posts are left out
	 */
	public function countByUsers(array $uids): array {
		$uids = array_values(array_filter(array_map('intval', $uids), static fn (int $uid): bool => $uid > 0));
		if (!$uids) {
			return [];
		}
		$counts = [];
		$result = $this->db->queryF('SELECT uid, COUNT(*) FROM ' . $this->table . ' WHERE approved = 1 AND uid IN (' . implode(',', $uids) . ') GROUP BY uid');
		while ($result && ($row = $this->db->fetchRow($result))) {
			$counts[(int) $row[0]] = (int) $row[1];
		}
		return $counts;
	}

	/**
	 * The members with the most approved posts
	 *
	 * @param list<int> $forumIds the forums the reader may open
	 * @return list<array{uid: int, posts: int}>
	 */
	public function getTopPosters(array $forumIds, int $limit): array {
		$forumIds = array_values(array_filter(array_map('intval', $forumIds)));
		if (!$forumIds) {
			return [];
		}
		$top = [];
		$result = $this->db->queryF('SELECT uid, COUNT(*) AS n FROM ' . $this->table . ' WHERE approved = 1 AND uid > 0 AND forum_id IN (' . implode(',', $forumIds) . ') GROUP BY uid ORDER BY n DESC, uid ASC LIMIT ' . max(1, $limit));
		while ($result && ($row = $this->db->fetchRow($result))) {
			$top[] = ['uid' => (int) $row[0], 'posts' => (int) $row[1]];
		}
		return $top;
	}

	public function hasPosted(int $topicId, int $uid): bool {
		$criteria = $this->approvedIn($topicId);
		$criteria->add(new icms_db_criteria_Item('uid', $uid));
		return $uid > 0 && $this->getCount($criteria) > 0;
	}

	/**
	 * Who wrote what and when, without the texts; for the lists of topics
	 *
	 * @param list<int> $postIds
	 * @return array<int, array{post_id: int, topic_id: int, uid: int, poster_name: string, post_time: int, subject: string}>
	 */
	public function getSummaries(array $postIds): array {
		$postIds = array_values(array_filter(array_map('intval', $postIds)));
		$summaries = [];
		foreach (array_chunk($postIds, 500) as $chunk) {
			$result = $this->db->queryF('SELECT post_id, topic_id, uid, poster_name, post_time, subject FROM ' . $this->table . ' WHERE post_id IN (' . implode(',', $chunk) . ')');
			while ($result && ($row = $this->db->fetchRow($result))) {
				$summaries[(int) $row[0]] = [
					'post_id' => (int) $row[0], 'topic_id' => (int) $row[1], 'uid' => (int) $row[2],
					'poster_name' => (string) $row[3], 'post_time' => (int) $row[4], 'subject' => (string) $row[5],
				];
			}
		}
		return $summaries;
	}

	/**
	 * How many approved posts of a topic come before a post
	 */
	public function getPosition(int $topicId, int $postId): int {
		$criteria = $this->approvedIn($topicId);
		$criteria->add(new icms_db_criteria_Item('post_id', $postId, '<'));
		return (int) $this->getCount($criteria);
	}

	/**
	 * When the member or the address posted last, for the flood check; 0 when never
	 */
	public function getLastPostTime(int $uid, string $ip): int {
		$who = new icms_db_criteria_Compo();
		if ($uid > 0) {
			$who->add(new icms_db_criteria_Item('uid', $uid));
		} elseif (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
			// criteria values are not escaped by the core, so only a valid address goes in
			$who->add(new icms_db_criteria_Item('poster_ip', $ip));
			$who->add(new icms_db_criteria_Item('uid', 0));
		} else {
			return 0;
		}
		$who->setSort('post_time');
		$who->setOrder('DESC');
		$who->setLimit(1);
		$posts = $this->getObjects($who);
		return $posts ? (int) reset($posts)->getVar('post_time', 'e') : 0;
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
