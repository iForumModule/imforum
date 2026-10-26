<?php
/**
 * Helpers for the pages for visitors: URLs, escaping, times and paging
 *
 * Every string handed to a template is already escaped. The templates print
 * values as they are.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_View {

	/**
	 * The address of a page of the module, not yet escaped for HTML
	 *
	 * @param array<string, scalar> $query
	 */
	public static function url(string $page, array $query = []): string {
		$url = IMFORUM_URL . $page . '.php';
		$query = array_filter($query, static fn ($value): bool => $value !== '' && $value !== 0);
		return $query ? $url . '?' . http_build_query($query, '', '&') : $url;
	}

	/**
	 * The same address, ready to print in an attribute
	 *
	 * @param array<string, scalar> $query
	 */
	public static function link(string $page, array $query = []): string {
		return self::escape(self::url($page, $query));
	}

	/**
	 * The author of a post or topic: a link to the member, or the name a guest gave
	 */
	public static function poster(int $uid, string $name): string {
		global $icmsConfig;
		if ($uid > 0) {
			return (string) icms_member_user_Handler::getUserLink($uid);
		}
		return self::escape(trim($name) !== '' ? trim($name) : (string) ($icmsConfig['anonymous'] ?? ''));
	}

	/**
	 * The list of visitors for the templates, names as links
	 *
	 * @param array{members: list<array{uid: int, uname: string}>, guests: int} $snapshot
	 * @return array{members: list<array{url: string, name: string}>, member_count: int, guests: int}
	 */
	public static function online(array $snapshot): array {
		$members = [];
		foreach ($snapshot['members'] as $member) {
			$members[] = [
				'url' => self::escape(ICMS_URL . '/userinfo.php?uid=' . $member['uid']),
				'name' => $member['uname'],
			];
		}
		return ['members' => $members, 'member_count' => count($members), 'guests' => $snapshot['guests']];
	}

	/**
	 * The address of the visitor, or an empty string when the server does not give a valid one
	 */
	public static function clientIp(): string {
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '';
	}

	/**
	 * The prefix of a topic ("Question") ready to print, or an empty string
	 */
	public static function prefix(int $index): string {
		$prefixes = mod_imforum_PostService::getPrefixes();
		return isset($prefixes[$index]) ? self::escape($prefixes[$index]) : '';
	}

	public static function escape(string $text): string {
		return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	}

	public static function time(int $timestamp): string {
		return $timestamp > 0 ? (string) formatTimestamp($timestamp, 'm') : '';
	}

	/**
	 * A positive number from the request, 0 when it is missing or not a number
	 */
	public static function intParam(string $name): int {
		$value = $_GET[$name] ?? 0;
		return is_scalar($value) ? max(0, (int) $value) : 0;
	}

	/**
	 * Previous, next and a window of page numbers
	 *
	 * The core's page navigation prints $_SERVER['PHP_SELF'] unescaped, which a
	 * visitor controls on servers that accept a path after the script name.
	 *
	 * @param array<string, scalar> $query the other parameters of the page
	 * @return string HTML, empty when there is one page
	 */
	public static function pager(string $page, array $query, int $total, int $perPage, int $start, string $startName = 'start'): string {
		$perPage = max(1, $perPage);
		$pages = (int) ceil($total / $perPage);
		if ($pages < 2) {
			return '';
		}
		$current = (int) floor($start / $perPage) + 1;
		$link = fn (int $number, string $label, string $class = ''): string => '<a' . ($class ? ' class="' . $class . '"' : '')
			. ' href="' . self::link($page, [$startName => ($number - 1) * $perPage] + $query) . '">' . $label . '</a>';

		$out = [];
		if ($current > 1) {
			$out[] = $link($current - 1, '&laquo;', 'imforum-prev');
		}
		$last = 0;
		for ($number = 1; $number <= $pages; $number++) {
			if ($number !== 1 && $number !== $pages && abs($number - $current) > 3) {
				continue;
			}
			if ($number - $last > 1) {
				$out[] = '<span class="imforum-gap">&hellip;</span>';
			}
			$out[] = $number === $current ? '<strong class="imforum-current">' . $number . '</strong>' : $link($number, (string) $number);
			$last = $number;
		}
		if ($current < $pages) {
			$out[] = $link($current + 1, '&raquo;', 'imforum-next');
		}
		return '<nav class="imforum-pager">' . implode(' ', $out) . '</nav>';
	}

	/**
	 * The path above a page: [['title' => escaped, 'url' => escaped or '']]
	 *
	 * @param list<array{0: string, 1?: string}> $trail raw title and optional raw url
	 * @return list<array{title: string, url: string}>
	 */
	public static function breadcrumb(array $trail): array {
		$crumbs = [['title' => self::escape(_MD_IMFORUM_TITLE), 'url' => self::link('index')]];
		foreach ($trail as $step) {
			$crumbs[] = ['title' => self::escape($step[0]), 'url' => isset($step[1]) ? self::escape($step[1]) : ''];
		}
		return $crumbs;
	}
}
