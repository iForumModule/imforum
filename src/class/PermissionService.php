<?php
/**
 * Who may see and read what
 *
 * The permission names are those of iForum:
 * - category_access: the category and its forums appear on the index
 * - forum_access: the forum appears in the list
 * - forum_view: topics and posts of the forum can be opened
 * Webmasters see everything. A forum that is switched off is visible to its
 * moderators and webmasters only.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_PermissionService {

	/** @var list<int> */
	private array $groups;

	private int $uid;

	private bool $webmaster;

	/** @var array<string, array<int, true>> permission name => item ids */
	private array $granted = [];

	/** @var array<int, mod_imforum_Forum>|null */
	private ?array $forums = null;

	/** @var array<int, true>|null */
	private ?array $moderated = null;

	/**
	 * @param list<int> $groups ids of the groups of the visitor
	 */
	public function __construct(array $groups, int $uid = 0, bool $webmaster = false) {
		$this->groups = array_map('intval', $groups);
		$this->uid = $uid;
		$this->webmaster = $webmaster;
	}

	/**
	 * The visitor of this request
	 */
	public static function forCurrentUser(): self {
		$user = icms::$user;
		if (!is_object($user)) {
			return new self([ICMS_GROUP_ANONYMOUS]);
		}
		$groups = array_map('intval', $user->getGroups());
		return new self($groups, (int) $user->getVar('uid'), in_array((int) ICMS_GROUP_ADMIN, $groups, true));
	}

	/**
	 * An anonymous visitor; the RSS feed shows only what a guest sees
	 */
	public static function forGuest(): self {
		return new self([ICMS_GROUP_ANONYMOUS]);
	}

	public function getUid(): int {
		return $this->uid;
	}

	/**
	 * @return list<int>
	 */
	public function getGroups(): array {
		return $this->groups;
	}

	public function isWebmaster(): bool {
		return $this->webmaster;
	}

	public function canSeeCategory(int $catId): bool {
		return $this->has('category_access', $catId);
	}

	/**
	 * The forum appears in lists
	 */
	public function canSeeForum(mod_imforum_Forum $forum): bool {
		if ($this->webmaster) {
			return true;
		}
		$forumId = (int) $forum->id();
		if (!(int) $forum->getVar('forum_active', 'e') && !$this->isModerator($forumId)) {
			return false;
		}
		return $this->canSeeCategory((int) $forum->getVar('cat_id', 'e')) && $this->has('forum_access', $forumId);
	}

	/**
	 * Topics and posts of the forum can be opened
	 */
	public function canReadForum(mod_imforum_Forum $forum): bool {
		return $this->webmaster || ($this->canSeeForum($forum) && $this->has('forum_view', (int) $forum->id()));
	}

	/**
	 * Start a topic in the forum
	 */
	public function canStartTopic(mod_imforum_Forum $forum): bool {
		return $this->canReadForum($forum) && $this->has('forum_post', (int) $forum->id()) && !$this->isSuspended((int) $forum->id());
	}

	/**
	 * Reply in a topic; a locked topic takes replies from moderators only
	 */
	public function canReply(mod_imforum_Forum $forum, mod_imforum_Topic $topic): bool {
		$forumId = (int) $forum->id();
		if (!$this->canReadForum($forum) || !$this->has('forum_reply', $forumId) || $this->isSuspended($forumId)) {
			return false;
		}
		return !$topic->isLocked() || $this->isModerator($forumId);
	}

	/**
	 * Members change their own posts for a while; moderators change any post
	 */
	public function canEdit(mod_imforum_Forum $forum, mod_imforum_Topic $topic, mod_imforum_Post $post): bool {
		$forumId = (int) $forum->id();
		if (!$this->canReadForum($forum)) {
			return false;
		}
		if ($this->isModerator($forumId)) {
			return true;
		}
		return $this->isAuthor($post) && $this->has('forum_edit', $forumId) && !$topic->isLocked()
			&& !$this->isSuspended($forumId) && $this->isWithinTimeLimit($post);
	}

	/**
	 * Members delete their own last post of a topic for a while, so no reply loses what it answered
	 */
	public function canDelete(mod_imforum_Forum $forum, mod_imforum_Topic $topic, mod_imforum_Post $post): bool {
		$forumId = (int) $forum->id();
		if (!$this->canReadForum($forum)) {
			return false;
		}
		if ($this->isModerator($forumId)) {
			return true;
		}
		return $this->isAuthor($post) && $this->has('forum_delete', $forumId) && !$topic->isLocked()
			&& !$this->isSuspended($forumId) && $this->isWithinTimeLimit($post)
			&& (int) $topic->getVar('topic_last_post_id', 'e') === (int) $post->id();
	}

	/**
	 * Lock, move, merge, split, approve and handle reports of the forum
	 */
	public function canModerate(mod_imforum_Forum $forum): bool {
		return $this->isModerator((int) $forum->id());
	}

	/**
	 * Members tell the moderators about a post they can read; guests cannot, so nobody can flood the list unseen
	 */
	public function canReport(mod_imforum_Forum $forum): bool {
		return $this->uid > 0 && $this->canReadForum($forum);
	}

	public function canAttach(mod_imforum_Forum $forum): bool {
		return $this->has('forum_attach', (int) $forum->id());
	}

	/**
	 * Posts of these members appear at once instead of waiting for a moderator
	 */
	public function skipsApproval(mod_imforum_Forum $forum): bool {
		return $this->isModerator((int) $forum->id()) || $this->has('forum_noapprove', (int) $forum->id());
	}

	/**
	 * A suspension on the member or the address, for this forum or every forum; moderators are exempt
	 */
	public function isSuspended(int $forumId): bool {
		if ($this->webmaster || $this->isModerator($forumId)) {
			return false;
		}
		return icms_getModuleHandler('suspension', IMFORUM_DIRNAME)->isSuspended($this->uid, mod_imforum_View::clientIp(), $forumId);
	}

	/**
	 * Guests never own a post: their name is only text
	 */
	private function isAuthor(mod_imforum_Post $post): bool {
		return $this->uid > 0 && (int) $post->getVar('uid', 'e') === $this->uid;
	}

	/**
	 * 0 minutes means no limit
	 */
	private function isWithinTimeLimit(mod_imforum_Post $post): bool {
		$minutes = (int) mod_imforum_Settings::get('edit_timelimit');
		return $minutes <= 0 || time() - (int) $post->getVar('post_time', 'e') <= $minutes * 60;
	}

	public function isModerator(int $forumId): bool {
		if ($this->webmaster) {
			return true;
		}
		if ($this->uid < 1) {
			return false;
		}
		if ($this->moderated === null) {
			$this->moderated = [];
			$criteria = new icms_db_criteria_Item('uid', $this->uid);
			foreach (icms_getModuleHandler('moderator', IMFORUM_DIRNAME)->getObjects($criteria) as $moderator) {
				$this->moderated[(int) $moderator->getVar('forum_id', 'e')] = true;
			}
		}
		return isset($this->moderated[$forumId]);
	}

	/**
	 * Every forum, switched off or not, by id in index order
	 *
	 * @return array<int, mod_imforum_Forum>
	 */
	public function getAllForums(): array {
		if ($this->forums === null) {
			$criteria = new icms_db_criteria_Compo();
			$criteria->setSort('weight');
			$criteria->setOrder('ASC');
			$this->forums = icms_getModuleHandler('forum', IMFORUM_DIRNAME)->getObjects($criteria, true);
		}
		return $this->forums;
	}

	/**
	 * The forums that appear in lists
	 *
	 * @return array<int, mod_imforum_Forum>
	 */
	public function getVisibleForums(): array {
		return array_filter($this->getAllForums(), fn (mod_imforum_Forum $forum): bool => $this->canSeeForum($forum));
	}

	/**
	 * The forums whose topics can be opened
	 *
	 * @return list<int>
	 */
	public function getReadableForumIds(): array {
		$ids = [];
		foreach ($this->getAllForums() as $forumId => $forum) {
			if ($this->canReadForum($forum)) {
				$ids[] = (int) $forumId;
			}
		}
		return $ids;
	}

	public function getForum(int $forumId): ?mod_imforum_Forum {
		return $this->getAllForums()[$forumId] ?? null;
	}

	private function has(string $permission, int $itemId): bool {
		if ($this->webmaster) {
			return true;
		}
		if (!isset($this->granted[$permission])) {
			$handler = icms_getModuleHandler('forum', IMFORUM_DIRNAME);
			$moduleId = (int) $handler->getModuleInfo()->getVar('mid');
			$ids = icms::handler('icms_member_groupperm')->getItemIds($permission, $this->groups, $moduleId);
			$this->granted[$permission] = array_fill_keys(array_map('intval', $ids), true);
		}
		return isset($this->granted[$permission][$itemId]);
	}
}
