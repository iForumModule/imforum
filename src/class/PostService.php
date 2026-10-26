<?php
/**
 * Writing: starting topics, replying, editing and deleting posts
 *
 * All rules live here and not in post.php, so they can be tested without a
 * request: permissions, suspensions, the flood limit, approval, what the
 * forum allows (HTML, signature, prefix) and the attachments. Messages are
 * language constants, ready for output.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_PostService {

	public const MAX_SUBJECT = 100;
	public const MAX_NAME = 25;
	public const MAX_KARMA = 10000;

	private mod_imforum_PermissionService $permissions;

	private ?mod_imforum_Notifier $notifier;

	public function __construct(mod_imforum_PermissionService $permissions, ?mod_imforum_Notifier $notifier = null) {
		$this->permissions = $permissions;
		$this->notifier = $notifier;
	}

	/**
	 * The topic prefixes of the preferences, numbered from 1 as topic_subject stores them
	 *
	 * @return array<int, string>
	 */
	public static function getPrefixes(): array {
		$prefixes = [];
		foreach (explode(',', (string) mod_imforum_Settings::get('subject_prefix')) as $prefix) {
			$prefix = trim($prefix);
			if ($prefix !== '') {
				$prefixes[count($prefixes) + 1] = $prefix;
			}
		}
		return $prefixes;
	}

	/**
	 * @param array<string, mixed> $input the request, unfiltered
	 * @return array{post: ?mod_imforum_Post, topic: ?mod_imforum_Topic, errors: list<string>, pending: bool}
	 */
	public function createTopic(mod_imforum_Forum $forum, array $input): array {
		$result = ['post' => null, 'topic' => null, 'errors' => [], 'pending' => false];
		if (!$this->permissions->canStartTopic($forum)) {
			$result['errors'][] = _NOPERM;
			return $result;
		}
		[$values, $errors] = $this->clean($input, $forum, true);
		$errors = array_merge($errors, $this->checkFlood($forum));
		if ($errors) {
			$result['errors'] = $errors;
			return $result;
		}

		$approved = $this->permissions->skipsApproval($forum) ? 1 : 0;
		$topicHandler = icms_getModuleHandler('topic', IMFORUM_DIRNAME);
		$topic = $topicHandler->create();
		$topic->setVar('forum_id', (int) $forum->id());
		$topic->setVar('topic_title', $values['subject']);
		$topic->setVar('topic_subject', $values['prefix']);
		$topic->setVar('topic_poster', $this->permissions->getUid());
		$topic->setVar('poster_name', $values['poster_name']);
		$topic->setVar('approved', $approved);
		if (!$topicHandler->insert($topic, true)) {
			$result['errors'][] = _MD_IMFORUM_ERR_SAVE_FAILED;
			return $result;
		}

		$post = $this->savePost($topic, $forum, $values, 0, $approved);
		if ($post === null) {
			$topicHandler->delete($topic, true);
			$result['errors'][] = _MD_IMFORUM_ERR_SAVE_FAILED;
			return $result;
		}
		return $this->finish($result, $post, $topic, $forum, true, $approved);
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array{post: ?mod_imforum_Post, topic: ?mod_imforum_Topic, errors: list<string>, pending: bool}
	 */
	public function reply(mod_imforum_Forum $forum, mod_imforum_Topic $topic, array $input): array {
		$result = ['post' => null, 'topic' => $topic, 'errors' => [], 'pending' => false];
		if ((int) $topic->getVar('forum_id', 'e') !== (int) $forum->id() || !$this->permissions->canReply($forum, $topic)) {
			$result['errors'][] = _NOPERM;
			return $result;
		}
		[$values, $errors] = $this->clean($input, $forum, false);
		$errors = array_merge($errors, $this->checkFlood($forum));
		if ($errors) {
			$result['errors'] = $errors;
			return $result;
		}

		$approved = $this->permissions->skipsApproval($forum) ? 1 : 0;
		$parent = $this->resolveParent((int) ($input['pid'] ?? 0), $topic);
		$post = $this->savePost($topic, $forum, $values, $parent, $approved);
		if ($post === null) {
			$result['errors'][] = _MD_IMFORUM_ERR_SAVE_FAILED;
			return $result;
		}
		return $this->finish($result, $post, $topic, $forum, false, $approved);
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array{post: ?mod_imforum_Post, topic: ?mod_imforum_Topic, errors: list<string>, pending: bool}
	 */
	public function edit(mod_imforum_Forum $forum, mod_imforum_Topic $topic, mod_imforum_Post $post, array $input): array {
		$result = ['post' => null, 'topic' => $topic, 'errors' => [], 'pending' => false];
		if ((int) $post->getVar('topic_id', 'e') !== (int) $topic->id() || !$this->permissions->canEdit($forum, $topic, $post)) {
			$result['errors'][] = _NOPERM;
			return $result;
		}
		$isFirst = (int) $topic->getVar('topic_first_post_id', 'e') === (int) $post->id() || (int) $post->getVar('pid', 'e') === 0 && $this->isOnlyPost($post);
		[$values, $errors] = $this->clean($input, $forum, $isFirst, $post);
		if ($errors) {
			$result['errors'] = $errors;
			return $result;
		}

		$this->applyValues($post, $values);
		$name = icms::$user ? (string) icms::$user->getVar('uname', 'n') : '';
		$post->addEdit($this->permissions->getUid(), $name);
		if (!icms_getModuleHandler('post', IMFORUM_DIRNAME)->insert($post, true)) {
			$result['errors'][] = _MD_IMFORUM_ERR_SAVE_FAILED;
			return $result;
		}
		if ($isFirst) {
			$topic->setVar('topic_title', $values['subject']);
			$topic->setVar('topic_subject', $values['prefix']);
			icms_getModuleHandler('topic', IMFORUM_DIRNAME)->insert($topic, true);
		}
		$result['post'] = $post;
		$result['pending'] = (int) $post->getVar('approved', 'e') === 0;
		return $result;
	}

	/**
	 * @return array{ok: bool, errors: list<string>, topic_gone: bool}
	 */
	public function delete(mod_imforum_Forum $forum, mod_imforum_Topic $topic, mod_imforum_Post $post): array {
		if ((int) $post->getVar('topic_id', 'e') !== (int) $topic->id() || !$this->permissions->canDelete($forum, $topic, $post)) {
			return ['ok' => false, 'errors' => [_NOPERM], 'topic_gone' => false];
		}
		$topicId = (int) $topic->id();
		if (!icms_getModuleHandler('post', IMFORUM_DIRNAME)->delete($post, true)) {
			return ['ok' => false, 'errors' => [_MD_IMFORUM_ERR_SAVE_FAILED], 'topic_gone' => false];
		}
		return ['ok' => true, 'errors' => [], 'topic_gone' => icms_getModuleHandler('topic', IMFORUM_DIRNAME)->get($topicId)->isNew()];
	}

	/**
	 * Store the uploaded files of the form and remove the ones the author ticked
	 *
	 * @param list<int> $remove ids of attachments to remove
	 * @return list<string> messages for files that were refused
	 */
	public function handleAttachments(mod_imforum_Forum $forum, mod_imforum_Post $post, array $remove = []): array {
		$handler = icms_getModuleHandler('attachment', IMFORUM_DIRNAME);
		foreach ($remove as $attachId) {
			$attachment = $handler->get((int) $attachId);
			if (!$attachment->isNew() && (int) $attachment->getVar('post_id', 'e') === (int) $post->id()) {
				$handler->delete($attachment, true);
			}
		}

		$errors = [];
		if (!$this->permissions->canAttach($forum)) {
			return $errors;
		}
		$uploader = new mod_imforum_Uploader($forum);
		$limit = max(1, (int) mod_imforum_Settings::get('attach_max_files'));
		for ($i = 0; $i < $limit; $i++) {
			if (!isset($_FILES['attachment_' . $i])) {
				continue;
			}
			if ($handler->countForPost((int) $post->id()) >= $limit) {
				$errors[] = sprintf(_MD_IMFORUM_ERR_ATTACH_LIMIT, $limit);
				break;
			}
			$stored = $uploader->store('attachment_' . $i, (int) $post->id());
			if ($stored['error'] !== null) {
				$errors[] = $stored['error'];
			}
		}
		return $errors;
	}

	/**
	 * Filter and check what the form sent
	 *
	 * @param array<string, mixed> $input
	 * @return array{0: array<string, mixed>, 1: list<string>}
	 */
	private function clean(array $input, mod_imforum_Forum $forum, bool $startsTopic, ?mod_imforum_Post $existing = null): array {
		$errors = [];
		$text = static fn (string $key): string => is_string($input[$key] ?? null) ? $input[$key] : '';
		$flag = static fn (string $key): int => !empty($input[$key]) ? 1 : 0;

		$subject = trim(preg_replace('/\s+/u', ' ', $text('subject')) ?? '');
		if ($subject === '') {
			$errors[] = _MD_IMFORUM_ERR_NO_SUBJECT;
		} elseif (mb_strlen($subject) > self::MAX_SUBJECT) {
			$errors[] = sprintf(_MD_IMFORUM_ERR_SUBJECT_LONG, self::MAX_SUBJECT);
		}
		$body = str_replace("\r\n", "\n", $text('post_text'));
		if (trim($body) === '') {
			$errors[] = _MD_IMFORUM_ERR_NO_TEXT;
		}

		$member = $this->permissions->getUid() > 0;
		$guestName = '';
		if (!$member && $existing === null) {
			$guestName = mb_substr(trim(strip_tags($text('poster_name'))), 0, self::MAX_NAME);
		}
		$prefix = 0;
		$prefixes = self::getPrefixes();
		if ($startsTopic && (int) $forum->getVar('allow_subject_prefix', 'e') === 1) {
			$prefix = (int) ($input['prefix'] ?? 0);
			if (!isset($prefixes[$prefix])) {
				$prefix = 0;
			}
		}
		$karma = $member ? max(0, min(self::MAX_KARMA, (int) ($input['post_karma'] ?? 0))) : 0;

		return [[
			'subject' => $subject,
			'post_text' => $body,
			'poster_name' => $guestName,
			'prefix' => $prefix,
			'icon' => preg_match('/^[A-Za-z0-9_.-]{1,50}$/', $text('icon')) === 1 ? $text('icon') : '',
			'attachsig' => $member && (int) $forum->getVar('allow_sig', 'e') === 1 ? $flag('attachsig') : 0,
			'post_karma' => $karma,
			'require_reply' => $member ? $flag('require_reply') : 0,
			'dohtml' => $flag('wysiwyg') === 1 || ((int) $forum->getVar('allow_html', 'e') === 1 && $flag('dohtml') === 1) ? 1 : 0,
			'dosmiley' => $flag('dosmiley'),
			'doxcode' => $flag('doxcode'),
			'doimage' => $flag('doimage'),
			'dobr' => $flag('wysiwyg') === 1 ? 0 : $flag('dobr'),
		], $errors];
	}

	/**
	 * @return list<string>
	 */
	private function checkFlood(mod_imforum_Forum $forum): array {
		$seconds = (int) mod_imforum_Settings::get('flood_seconds');
		if ($seconds <= 0 || $this->permissions->isModerator((int) $forum->id())) {
			return [];
		}
		$last = icms_getModuleHandler('post', IMFORUM_DIRNAME)->getLastPostTime($this->permissions->getUid(), mod_imforum_View::clientIp());
		$wait = $last + $seconds - time();
		return $wait > 0 ? [sprintf(_MD_IMFORUM_ERR_FLOOD, $wait)] : [];
	}

	/**
	 * The post a reply answers, or the first post of the topic
	 */
	private function resolveParent(int $pid, mod_imforum_Topic $topic): int {
		if ($pid > 0) {
			$parent = icms_getModuleHandler('post', IMFORUM_DIRNAME)->get($pid);
			if (!$parent->isNew() && (int) $parent->getVar('topic_id', 'e') === (int) $topic->id() && (int) $parent->getVar('approved', 'e') === 1) {
				return $pid;
			}
		}
		return (int) $topic->getVar('topic_first_post_id', 'e');
	}

	private function isOnlyPost(mod_imforum_Post $post): bool {
		return icms_getModuleHandler('post', IMFORUM_DIRNAME)->getCount(new icms_db_criteria_Item('topic_id', (int) $post->getVar('topic_id', 'e'))) === 1;
	}

	/**
	 * @param array<string, mixed> $values
	 */
	private function savePost(mod_imforum_Topic $topic, mod_imforum_Forum $forum, array $values, int $pid, int $approved): ?mod_imforum_Post {
		$handler = icms_getModuleHandler('post', IMFORUM_DIRNAME);
		$post = $handler->create();
		$post->setVar('topic_id', (int) $topic->id());
		$post->setVar('forum_id', (int) $forum->id());
		$post->setVar('pid', $pid);
		$post->setVar('uid', $this->permissions->getUid());
		$post->setVar('poster_ip', mod_imforum_View::clientIp());
		$post->setVar('approved', $approved);
		$post->setVar('poster_name', $values['poster_name']);
		$this->applyValues($post, $values);
		return $handler->insert($post, true) ? $post : null;
	}

	/**
	 * @param array<string, mixed> $values
	 */
	private function applyValues(mod_imforum_Post $post, array $values): void {
		foreach (['subject', 'post_text', 'icon', 'attachsig', 'post_karma', 'require_reply', 'dohtml', 'dosmiley', 'doxcode', 'doimage', 'dobr'] as $field) {
			$post->setVar($field, $values[$field]);
		}
	}

	/**
	 * @param array{post: ?mod_imforum_Post, topic: ?mod_imforum_Topic, errors: list<string>, pending: bool} $result
	 * @return array{post: ?mod_imforum_Post, topic: ?mod_imforum_Topic, errors: list<string>, pending: bool}
	 */
	private function finish(array $result, mod_imforum_Post $post, mod_imforum_Topic $topic, mod_imforum_Forum $forum, bool $newTopic, int $approved): array {
		// reload: the counters of the topic changed with the post
		$topic = icms_getModuleHandler('topic', IMFORUM_DIRNAME)->get((int) $topic->id());
		$result['post'] = $post;
		$result['topic'] = $topic;
		$result['pending'] = $approved === 0;
		if ($approved === 1) {
			($this->notifier ?? new mod_imforum_Notifier())->newPost($post, $topic, $forum, $newTopic);
		}
		return $result;
	}
}
