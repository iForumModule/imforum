<?php
/**
 * Periodic housekeeping for the autotask: visitors list, read marks, ended suspensions
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_Cleanup {

	/** Suspensions stay in the admin list this long after they ended */
	public const KEEP_SUSPENSIONS_DAYS = 30;

	/**
	 * @return array{online: bool, read_marks: int, suspensions: int}
	 */
	public static function run(?int $now = null): array {
		$now ??= time();
		$minutes = mod_imforum_Settings::getCount('online_minutes');
		$online = icms_getModuleHandler('online', IMFORUM_DIRNAME)->purgeOlderThan($now - $minutes * 60);

		// 0 days: read marks never expire
		$days = (int) mod_imforum_Settings::get('read_expire_days');
		$marks = $days > 0 ? icms_getModuleHandler('readmark', IMFORUM_DIRNAME)->purgeOlderThan($now - $days * 86400) : 0;

		$ended = icms_getModuleHandler('suspension', IMFORUM_DIRNAME)->purgeEndedBefore($now - self::KEEP_SUSPENSIONS_DAYS * 86400);
		return ['online' => $online, 'read_marks' => $marks, 'suspensions' => $ended];
	}
}
