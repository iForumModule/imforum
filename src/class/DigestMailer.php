<?php
/**
 * The periodic digest: one mail with the new topics and posts since the last one
 *
 * Members subscribe with the notification "Digest" for all forums. Every
 * recipient only gets the forums that member may read, and the mail holds
 * titles, counts and links, never the text of a post. The complete digest
 * is kept in the digest table for the administrators.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_DigestMailer {

	/** Topics listed in one mail; the rest is summed up in one line */
	public const MAX_TOPICS = 50;

	/** Without an earlier digest the mail covers this many seconds */
	public const FIRST_PERIOD = 86400;

	private ?mod_imforum_Notifier $notifier;

	public function __construct(?mod_imforum_Notifier $notifier = null) {
		$this->notifier = $notifier;
	}

	/**
	 * What happened between two moments, per forum
	 *
	 * @return array<int, list<array{topic_id: int, title: string, posts: int, is_new: bool}>> by forum id, most recent activity first within a forum
	 */
	public function collect(int $since, int $until): array {
		$found = icms_getModuleHandler('post', IMFORUM_DIRNAME)->getActivity($since, $until);
		if (!$found) {
			return [];
		}

		$topicObjects = icms_getModuleHandler('topic', IMFORUM_DIRNAME)->getByIds(array_column($found, 1));
		$byForum = [];
		foreach ($found as [$forumId, $topicId, $count, $firstNew]) {
			if (!isset($topicObjects[$topicId])) {
				continue;
			}
			$byForum[$forumId][] = [
				'topic_id' => $topicId,
				'title' => trim((string) preg_replace('/\s+/', ' ', (string) $topicObjects[$topicId]->getVar('topic_title', 'n'))),
				'posts' => $count,
				'is_new' => (int) $topicObjects[$topicId]->getVar('topic_first_post_id', 'e') === $firstNew,
			];
		}
		return $byForum;
	}

	/**
	 * The mail text for some forums; plain text with links
	 *
	 * @param array<int, list<array{topic_id: int, title: string, posts: int, is_new: bool}>> $byForum
	 * @param list<int> $forumIds the forums that may appear, in the order they are listed
	 * @param array<int, string> $forumNames by forum id
	 * @return array{text: string, topics: int}
	 */
	public static function render(array $byForum, array $forumIds, array $forumNames): array {
		$lines = [];
		$topicCount = 0;
		$left = 0;
		foreach ($forumIds as $forumId) {
			if (empty($byForum[$forumId])) {
				continue;
			}
			$section = [];
			foreach ($byForum[$forumId] as $topic) {
				$topicCount++;
				if ($topicCount > self::MAX_TOPICS) {
					$left++;
					continue;
				}
				$section[] = '* ' . $topic['title'] . ' (' . ($topic['is_new'] ? _MD_IMFORUM_DIGEST_NEW_TOPIC . ', ' : '')
					. sprintf(_MD_IMFORUM_DIGEST_POSTS, $topic['posts']) . ')' . "\n  "
					. mod_imforum_View::url('topic', ['topic' => $topic['topic_id']]);
			}
			if ($section) {
				$lines[] = '== ' . ($forumNames[$forumId] ?? '#' . $forumId) . ' ==';
				$lines = array_merge($lines, $section, ['']);
			}
		}
		if ($left > 0) {
			$lines[] = sprintf(_MD_IMFORUM_DIGEST_MORE, $left) . ' ' . mod_imforum_View::url('viewall');
		}
		return ['text' => rtrim(implode("\n", $lines)), 'topics' => $topicCount];
	}

	/**
	 * Build and send the digest for the time since the last one
	 *
	 * The digest is stored before the mails go out, so a failure halfway never
	 * leads to a second mail for the same period.
	 *
	 * @param (callable(int): list<int>)|null $groupsOf the groups of a member; the core member handler by default
	 * @return array{topics: int, recipients: int, digest_id: int}
	 */
	public function run(?int $now = null, ?callable $groupsOf = null): array {
		$now ??= time();
		$digests = icms_getModuleHandler('digest', IMFORUM_DIRNAME);
		$last = $digests->getLastDigestTime();
		$since = $last > 0 ? $last : $now - self::FIRST_PERIOD;
		$empty = ['topics' => 0, 'recipients' => 0, 'digest_id' => 0];
		if ($since >= $now) {
			return $empty;
		}

		$byForum = $this->collect($since, $now);
		if (!$byForum) {
			return $empty;
		}

		$permissions = new mod_imforum_PermissionService([(int) ICMS_GROUP_ADMIN], 1, true);
		$forums = $permissions->getAllForums();
		$forumNames = [];
		foreach ($forums as $forumId => $forum) {
			$forumNames[(int) $forumId] = (string) $forum->getVar('forum_name', 'n');
		}
		$allForumIds = array_map('intval', array_keys($forums));
		$all = self::render($byForum, $allForumIds, $forumNames);

		$digest = $digests->create();
		$digest->setVar('digest_time', $now);
		$digest->setVar('digest_content', $all['text']);
		if (!$digests->insert($digest, true)) {
			return $empty;
		}

		$notifier = $this->notifier ?? new mod_imforum_Notifier();
		$groupsOf ??= static fn (int $uid): array => array_map('intval', (array) icms::handler('icms_member')->getGroupsByUser($uid));
		$readable = [];
		$byContent = [];
		foreach ($notifier->getGlobalSubscribers('digest') as $uid) {
			$groups = $groupsOf($uid);
			sort($groups);
			$key = implode(',', $groups);
			if (!isset($readable[$key])) {
				$visitor = new mod_imforum_PermissionService($groups, $uid, in_array((int) ICMS_GROUP_ADMIN, $groups, true));
				$readable[$key] = array_values(array_intersect($allForumIds, $visitor->getReadableForumIds()));
			}
			$mail = self::render($byForum, $readable[$key], $forumNames);
			if ($mail['topics'] > 0) {
				$byContent[md5($mail['text'])]['text'] = $mail['text'];
				$byContent[md5($mail['text'])]['uids'][] = $uid;
			}
		}

		$recipients = 0;
		foreach ($byContent as $content) {
			$recipients += count($content['uids']);
			$this->send($content['text'], $content['uids'], $since, $now);
		}
		return ['topics' => $all['topics'], 'recipients' => $recipients, 'digest_id' => (int) $digest->id()];
	}

	/**
	 * @param list<int> $uids
	 */
	protected function send(string $text, array $uids, int $since, int $until): void {
		$moduleId = (int) icms_getModuleHandler('forum', IMFORUM_DIRNAME)->getModuleInfo()->getVar('mid');
		icms::handler('icms_data_notification')->triggerEvent('global', 0, 'digest', [
			'X_DIGEST_TEXT' => $text,
			'X_DIGEST_FROM' => (string) formatTimestamp($since, 'm'),
			'X_DIGEST_TO' => (string) formatTimestamp($until, 'm'),
			'X_DIGEST_URL' => mod_imforum_View::url('viewall'),
		], $uids, $moduleId, 0);
	}
}
