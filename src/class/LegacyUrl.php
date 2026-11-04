<?php
/**
 * Translates the addresses of the old iForum module into those of imforum
 *
 * The stub module that takes the place of iForum after the import calls this
 * class for every old script, so bookmarks, search engines and links in
 * imported posts keep working. The ids stay the same during the import, so the
 * translation only has to rename the scripts and their parameters.
 *
 * @copyright	The iForum project
 * @license		GNU GPL v2, see LICENSE
 * @package		imforum
 * @since		1.0
 */

class mod_imforum_LegacyUrl {

	/** The scripts of iForum that have a counterpart */
	public const SCRIPTS = ['index', 'viewforum', 'viewtopic', 'viewpost', 'viewall', 'rss'];

	/**
	 * The page of imforum for an old script and its query, relative to the module folder
	 *
	 * @param array<string, mixed> $query              the old $_GET
	 * @param (callable(int): int)|null $topicOfPost   finds the topic of a post, 0 when unknown
	 */
	public static function target(string $script, array $query, ?callable $topicOfPost = null): string {
		$int = static function (string $name) use ($query): int {
			$value = $query[$name] ?? 0;
			return is_scalar($value) ? max(0, (int) $value) : 0;
		};
		switch ($script) {
			case 'viewforum':
				return self::build('forum', ['forum' => $int('forum'), 'start' => $int('start')], $int('forum') > 0);

			case 'viewtopic':
				$topicId = $int('topic_id');
				$postId = $int('post_id');
				if ($topicId < 1 && $postId > 0 && $topicOfPost !== null) {
					$topicId = max(0, $topicOfPost($postId));
				}
				if ($topicId < 1) {
					// only a forum is known: go there, else to the index
					return $int('forum') > 0 ? self::build('forum', ['forum' => $int('forum')], true) : 'index.php';
				}
				$viewmode = (string) ($query['viewmode'] ?? '');
				return self::build('topic', [
					'topic' => $topicId,
					'post' => $postId,
					'start' => $postId > 0 ? 0 : $int('start'),
					'mode' => in_array($viewmode, ['thread', 'tree'], true) ? 'tree' : '',
				], true);

			case 'viewall':
				$type = (string) ($query['type'] ?? '');
				return self::build('viewall', [
					'type' => in_array($type, ['unread', 'digest'], true) ? $type : '',
					'start' => $int('start'),
				], true);

			case 'viewpost':
				// the list of the latest posts has no counterpart: the latest topics are the nearest
				return 'viewall.php';

			case 'rss':
				// the old feed took a pipe separated list; imforum has one feed per forum or all
				$forums = array_values(array_filter(array_map('intval', explode('|', (string) ($query['f'] ?? ''))), static fn (int $id): bool => $id > 0));
				return self::build('rss', ['forum' => count($forums) === 1 ? $forums[0] : 0], true);

			default:
				return 'index.php';
		}
	}

	/**
	 * Page and query; the zero and empty values are left out
	 *
	 * @param array<string, int|string> $query
	 */
	private static function build(string $page, array $query, bool $valid): string {
		if (!$valid) {
			return 'index.php';
		}
		$query = array_filter($query, static fn ($value): bool => $value !== '' && $value !== 0);
		return $page . '.php' . ($query ? '?' . http_build_query($query, '', '&') : '');
	}

	/**
	 * Sends the visitor on with a permanent redirect
	 *
	 * Called from the scripts of the stub module. Without imforum there is
	 * nothing to go to, so the visitor ends up on the home page.
	 */
	public static function redirect(string $script): never {
		$base = defined('IMFORUM_URL') ? IMFORUM_URL : ICMS_URL . '/modules/imforum/';
		$lookup = static function (int $postId): int {
			$post = icms_getModuleHandler('post', 'imforum')->get($postId);
			return is_object($post) && !$post->isNew() ? (int) $post->getVar('topic_id', 'e') : 0;
		};
		$target = $base . self::target($script, $_GET, $lookup);
		header('Location: ' . $target, true, 301);
		exit;
	}
}
