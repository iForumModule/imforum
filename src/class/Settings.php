<?php
/**
 * The preferences of the module, with a default for each
 *
 * Reads the preferences itself, so blocks and the search function work
 * outside the module's own pages, where icms::$module is another module.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_Settings {

	public const DEFAULTS = [
		'topics_per_page' => 20,
		'posts_per_page' => 10,
		'default_view' => 'flat',
		'show_online' => 1,
		'online_minutes' => 15,
		'rss_items' => 20,
		'edit_timelimit' => 30,
		'flood_seconds' => 30,
		'quickreply_enabled' => 1,
		'captcha_guests' => 1,
		'attach_max_files' => 3,
		'subject_prefix' => '',
		'disclaimer_show' => 0,
		'disclaimer' => '',
	];

	/** @var array<string, mixed>|null */
	private static ?array $values = null;

	public static function get(string $name): mixed {
		if (self::$values === null) {
			self::$values = self::DEFAULTS;
			$module = icms::handler('icms_module')->getByDirname(IMFORUM_DIRNAME);
			if (is_object($module)) {
				$stored = icms::handler('icms_config')->getConfigsByCat(0, (int) $module->getVar('mid'));
				foreach (self::DEFAULTS as $key => $default) {
					if (isset($stored[$key])) {
						self::$values[$key] = is_int($default) ? (int) $stored[$key] : (string) $stored[$key];
					}
				}
			}
		}
		return self::$values[$name] ?? self::DEFAULTS[$name] ?? null;
	}

	/**
	 * Number settings are never below one, a page size of zero would show nothing
	 */
	public static function getCount(string $name): int {
		return max(1, (int) self::get($name));
	}

	/**
	 * For tests
	 *
	 * @param array<string, mixed>|null $values
	 */
	public static function override(?array $values): void {
		self::$values = $values === null ? null : $values + self::DEFAULTS;
	}
}
