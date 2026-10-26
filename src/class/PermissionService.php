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
