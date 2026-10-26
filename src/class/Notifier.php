<?php
/**
 * Tells the subscribers of a forum or topic about a new post
 *
 * Only members who may read the forum are told, and the mail holds the title
 * and the address but never the text, so a karma lock or a required reply
 * cannot be bypassed through a mailbox.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_Notifier {

	/**
	 * Notify for an approved post
	 *
	 * @param bool $newTopic whether the post starts the topic
	 */
	public function newPost(mod_imforum_Post $post, mod_imforum_Topic $topic, mod_imforum_Forum $forum, bool $newTopic): void {
		$notifications = icms::handler('icms_data_notification');
		$moduleId = (int) icms_getModuleHandler('forum', IMFORUM_DIRNAME)->getModuleInfo()->getVar('mid');
		$forumId = (int) $forum->id();
		$topicId = (int) $topic->id();

		$events = [];
		if ($newTopic) {
			$events[] = ['forum', $forumId, 'new_thread'];
			$events[] = ['global', 0, 'new_thread'];
		} else {
			$events[] = ['thread', $topicId, 'new_post'];
		}
		$events[] = ['forum', $forumId, 'new_post'];
		$events[] = ['global', 0, 'new_post'];

		$tags = [
			'X_POST_URL' => mod_imforum_View::url('topic', ['topic' => $topicId, 'post' => (int) $post->id()]) . '#post-' . (int) $post->id(),
			'X_POST_SUBJECT' => (string) $post->getVar('subject', 'n'),
			'X_TOPIC_TITLE' => (string) $topic->getVar('topic_title', 'n'),
			'X_FORUM_NAME' => (string) $forum->getVar('forum_name', 'n'),
			'X_POSTER' => $this->posterName($post),
		];
		$author = (int) $post->getVar('uid', 'e');
		$told = [];
		foreach ($events as [$category, $itemId, $event]) {
			$subscribers = $this->getSubscribers($moduleId, $category, $itemId, $event);
			// a member who gets the mail for one event is not told again for another
			$readers = array_values(array_diff($this->filterReaders($subscribers, $forum), $told, [$author]));
			if (!$readers) {
				continue;
			}
			$told = array_merge($told, $readers);
			$notifications->triggerEvent($category, $itemId, $event, $tags, $readers, $moduleId, $author);
		}
	}

	/**
	 * Of these members, those who may read the forum
	 *
	 * @param list<int> $uids
	 * @param (callable(int): list<int>)|null $groupsOf the groups of a member; the core member handler by default
	 * @return list<int>
	 */
	public function filterReaders(array $uids, mod_imforum_Forum $forum, ?callable $groupsOf = null): array {
		$groupsOf ??= static fn (int $uid): array => array_map('intval', (array) icms::handler('icms_member')->getGroupsByUser($uid));
		$readers = [];
		foreach (array_unique($uids) as $uid) {
			$groups = $groupsOf($uid);
			$permissions = new mod_imforum_PermissionService($groups, $uid, in_array((int) ICMS_GROUP_ADMIN, $groups, true));
			if ($permissions->canReadForum($forum)) {
				$readers[] = $uid;
			}
		}
		return $readers;
	}

	/**
	 * Members who follow all forums for an event of the global category
	 *
	 * @return list<int>
	 */
	public function getGlobalSubscribers(string $event): array {
		$moduleId = (int) icms_getModuleHandler('forum', IMFORUM_DIRNAME)->getModuleInfo()->getVar('mid');
		return array_values(array_unique($this->getSubscribers($moduleId, 'global', 0, $event)));
	}

	/**
	 * The category and event are names from icms_version.php, never request input
	 *
	 * @return list<int>
	 */
	private function getSubscribers(int $moduleId, string $category, int $itemId, string $event): array {
		$criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('not_modid', $moduleId));
		$criteria->add(new icms_db_criteria_Item('not_category', $category));
		$criteria->add(new icms_db_criteria_Item('not_itemid', $itemId));
		$criteria->add(new icms_db_criteria_Item('not_event', $event));
		$uids = [];
		foreach (icms::handler('icms_data_notification')->getObjects($criteria) as $notification) {
			$uids[] = (int) $notification->getVar('not_uid');
		}
		return $uids;
	}

	private function posterName(mod_imforum_Post $post): string {
		$uid = (int) $post->getVar('uid', 'e');
		if ($uid > 0) {
			$name = icms_member_user_Handler::getUserLink($uid);
			return trim(strip_tags((string) $name));
		}
		global $icmsConfig;
		$name = trim((string) $post->getVar('poster_name', 'n'));
		return $name !== '' ? $name : (string) ($icmsConfig['anonymous'] ?? '');
	}
}
