<?php
/**
 * Handler for forums
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_ForumHandler extends icms_ipf_Handler {

	/**
	 * Group permissions per forum, with the gperm names iForum used, so the
	 * importer can copy them unchanged. forum_addpoll and forum_vote are gone
	 * with polls.
	 */
	public const PERMISSIONS = [
		'forum_access', 'forum_view', 'forum_post', 'forum_reply',
		'forum_edit', 'forum_delete', 'forum_attach', 'forum_noapprove',
	];

	public function __construct(icms_db_legacy_Database $db) {
		parent::__construct($db, 'forum', 'forum_id', 'forum_name', 'forum_desc', IMFORUM_DIRNAME);
		foreach (self::PERMISSIONS as $permission) {
			$caption = '_CO_IMFORUM_PERM_' . strtoupper($permission);
			$this->addPermission($permission, constant($caption), constant($caption . '_DSC'));
		}
	}

	/**
	 * All forums, parents before their children, as a tree
	 */
	public function getTree(?icms_db_criteria_Element $criteria = null): icms_ipf_Tree {
		$criteria ??= new icms_db_criteria_Compo();
		$criteria->setSort('weight');
		$criteria->setOrder('ASC');
		$forums = $this->getObjects($criteria, true);
		return new icms_ipf_Tree($forums, 'forum_id', 'parent_forum');
	}

	/**
	 * Options for a forum select box, indented to show the tree
	 *
	 * @return array<int, string>
	 */
	public function getForumSelectList(): array {
		$tree = $this->getTree();
		$options = [];
		$this->addTreeOptions($tree, 0, 0, $options);
		return $options;
	}

	/**
	 * Options for the parent forum field: the forum list plus "no parent"
	 *
	 * @return array<int, string>
	 */
	public function getParentSelectList(): array {
		return [0 => _CO_IMFORUM_FORUM_NO_PARENT] + $this->getForumSelectList();
	}

	/**
	 * Options for the forum of a suspension: the forum list plus "every forum"
	 *
	 * @return array<int, string>
	 */
	public function getScopeSelectList(): array {
		return [0 => _CO_IMFORUM_SUSPENSION_ALL_FORUMS] + $this->getForumSelectList();
	}

	/**
	 * @param array<int, string> $options
	 */
	private function addTreeOptions(icms_ipf_Tree $tree, int $parent, int $depth, array &$options): void {
		foreach ($tree->getFirstChild($parent) as $forum) {
			$options[(int) $forum->id()] = str_repeat('-', $depth * 2) . ($depth ? ' ' : '') . $forum->getVar('forum_name', 'e');
			$this->addTreeOptions($tree, (int) $forum->id(), $depth + 1, $options);
		}
	}

	/**
	 * Recount topics, posts and the last post of a forum
	 *
	 * Only approved topics and posts count. Recounting instead of adding and
	 * subtracting keeps the numbers right after moves, merges and failed
	 * requests, and stays cheap thanks to the indexes from imforum_db_upgrade_1().
	 */
	public function synchronise(int $forumId): bool {
		$topics = $this->db->prefix(IMFORUM_DIRNAME . '_topic');
		$posts = $this->db->prefix(IMFORUM_DIRNAME . '_post');

		[$topicCount] = $this->fetchRow("SELECT COUNT(*) FROM $topics WHERE forum_id = $forumId AND approved = 1");
		[$postCount, $lastPost] = $this->fetchRow("SELECT COUNT(*), MAX(post_id) FROM $posts WHERE forum_id = $forumId AND approved = 1");

		// queryF: the counters follow a write that has already been allowed
		return (bool) $this->db->queryF(sprintf(
			'UPDATE %s SET forum_topics = %d, forum_posts = %d, forum_last_post_id = %d WHERE forum_id = %d',
			$this->table, (int) $topicCount, (int) $postCount, (int) $lastPost, $forumId
		));
	}

	/**
	 * Recount every forum in two queries; used after an import or a prune
	 */
	public function synchroniseAll(): bool {
		$topics = $this->db->prefix(IMFORUM_DIRNAME . '_topic');
		$posts = $this->db->prefix(IMFORUM_DIRNAME . '_post');

		$ok = (bool) $this->db->queryF("UPDATE {$this->table} f"
			. " LEFT JOIN (SELECT forum_id, COUNT(*) AS n FROM $topics WHERE approved = 1 GROUP BY forum_id) t ON t.forum_id = f.forum_id"
			. ' SET f.forum_topics = COALESCE(t.n, 0)');
		return (bool) $this->db->queryF("UPDATE {$this->table} f"
			. " LEFT JOIN (SELECT forum_id, COUNT(*) AS n, MAX(post_id) AS last FROM $posts WHERE approved = 1 GROUP BY forum_id) p ON p.forum_id = f.forum_id"
			. ' SET f.forum_posts = COALESCE(p.n, 0), f.forum_last_post_id = COALESCE(p.last, 0)') && $ok;
	}

	/**
	 * @return list<int|string|null>
	 */
	private function fetchRow(string $sql): array {
		$result = $this->db->queryF($sql);
		$row = $result ? $this->db->fetchRow($result) : false;
		return $row ? array_values($row) : [0, 0];
	}

	/**
	 * A forum may not become its own parent or the child of one of its children
	 */
	protected function beforeSave(mod_imforum_Forum $forum): bool {
		$ownId = (int) $forum->id();
		$seen = [];
		for ($id = (int) $forum->getVar('parent_forum', 'e'); $id !== 0; $id = $this->getParentId($id)) {
			if ($id === $ownId || isset($seen[$id])) {
				$forum->setErrors(_CO_IMFORUM_FORUM_PARENT_LOOP);
				return false;
			}
			$seen[$id] = true;
		}
		return true;
	}

	/**
	 * Write the moderators chosen on the admin form, and recount
	 *
	 * The counters travel through the form as hidden fields; recounting
	 * afterwards undoes a stale or tampered value.
	 */
	protected function afterSave(mod_imforum_Forum $forum): bool {
		$forumId = (int) $forum->id();
		$moderators = $forum->getVar('moderators', 'n');
		$ok = !is_array($moderators)
			|| icms_getModuleHandler('moderator', IMFORUM_DIRNAME)->setModerators($forumId, array_map('intval', $moderators));
		return $this->synchronise($forumId) && $ok;
	}

	private function getParentId(int $forumId): int {
		[$parent] = $this->fetchRow("SELECT parent_forum FROM {$this->table} WHERE forum_id = $forumId");
		return (int) $parent;
	}

	/**
	 * Remove everything that belongs to a deleted forum
	 *
	 * Subforums move up one level instead of being deleted with it.
	 */
	protected function afterDelete(mod_imforum_Forum $forum): bool {
		$forumId = (int) $forum->id();
		$byForum = new icms_db_criteria_Item('forum_id', $forumId);

		$topicHandler = icms_getModuleHandler('topic', IMFORUM_DIRNAME);
		foreach ($topicHandler->getObjects($byForum) as $topic) {
			$topicHandler->delete($topic, true);
		}
		icms_getModuleHandler('moderator', IMFORUM_DIRNAME)->deleteAll($byForum);
		icms_getModuleHandler('suspension', IMFORUM_DIRNAME)->deleteAll($byForum);
		icms_getModuleHandler('online', IMFORUM_DIRNAME)->deleteAll($byForum);
		icms_getModuleHandler('readmark', IMFORUM_DIRNAME)->deleteByItem(mod_imforum_ReadmarkHandler::TYPE_FORUM, $forumId);

		return (bool) $this->db->queryF(sprintf(
			'UPDATE %s SET parent_forum = %d WHERE parent_forum = %d',
			$this->table, (int) $forum->getVar('parent_forum', 'e'), $forumId
		));
	}
}
