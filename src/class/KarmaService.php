<?php
/**
 * Hides posts that need karma or a reply from the reader
 *
 * iForum let an author ask for a minimum karma or for a reply before others
 * could read a post. Karma here is the number of approved posts a member has
 * written in the forum: the core has no karma of its own. Moderators, the
 * author and webmasters always read the post.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_KarmaService {

	public const LOCK_NONE = '';
	public const LOCK_KARMA = 'karma';
	public const LOCK_REPLY = 'reply';

	private int $uid;

	private ?int $karma = null;

	/** @var array<int, bool> topic id => whether the reader has replied */
	private array $replied = [];

	public function __construct(int $uid) {
		$this->uid = $uid;
	}

	public function getKarma(): int {
		if ($this->karma === null) {
			$this->karma = $this->uid > 0
				? icms_getModuleHandler('post', IMFORUM_DIRNAME)->countByUsers([$this->uid])[$this->uid] ?? 0
				: 0;
		}
		return $this->karma;
	}

	/**
	 * Why the reader may not see the text, or LOCK_NONE
	 */
	public function getLock(mod_imforum_Post $post, bool $moderator): string {
		$required = (int) $post->getVar('post_karma', 'e');
		$replyFirst = (int) $post->getVar('require_reply', 'e') === 1;
		if ($required <= 0 && !$replyFirst) {
			return self::LOCK_NONE;
		}
		$author = (int) $post->getVar('uid', 'e');
		if ($moderator || ($this->uid > 0 && $author === $this->uid)) {
			return self::LOCK_NONE;
		}
		if ($required > 0 && $this->getKarma() < $required) {
			return self::LOCK_KARMA;
		}
		if ($replyFirst && !$this->hasReplied((int) $post->getVar('topic_id', 'e'))) {
			return self::LOCK_REPLY;
		}
		return self::LOCK_NONE;
	}

	/**
	 * Unlock the post for this reader when the rules allow it
	 */
	public function apply(mod_imforum_Post $post, bool $moderator): string {
		$lock = $this->getLock($post, $moderator);
		if ($lock === self::LOCK_NONE) {
			$post->unlock();
		} else {
			$post->setLock($lock);
		}
		return $lock;
	}

	private function hasReplied(int $topicId): bool {
		if ($this->uid < 1) {
			return false;
		}
		return $this->replied[$topicId] ??= icms_getModuleHandler('post', IMFORUM_DIRNAME)->hasPosted($topicId, $this->uid);
	}
}
