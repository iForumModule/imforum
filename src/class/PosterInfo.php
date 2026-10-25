<?php
/**
 * What is shown next to a post about the member who wrote it
 *
 * Loads all members of a page of posts at once: avatar, rank, groups, the
 * number of posts in the forum, the date of registration and the signature.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_PosterInfo {

	/**
	 * @param list<int> $uids
	 * @return array<int, array<string, mixed>> by member id; text values are ready for output
	 */
	public static function load(array $uids, int $onlineMinutes = 15): array {
		$uids = array_values(array_unique(array_filter(array_map('intval', $uids), static fn (int $uid): bool => $uid > 0)));
		if (!$uids) {
			return [];
		}

		$members = icms::handler('icms_member');
		$users = $members->getUsers(new icms_db_criteria_Item('uid', '(' . implode(',', $uids) . ')', 'IN'), true);
		$counts = icms_getModuleHandler('post', IMFORUM_DIRNAME)->countByUsers($uids);
		$online = icms_getModuleHandler('online', IMFORUM_DIRNAME)->getOnlineMembers($uids, $onlineMinutes);
		$groupNames = $members->getGroupList();

		$info = [];
		foreach ($users as $uid => $user) {
			$uid = (int) $uid;
			$rank = $user->rank();
			$groups = [];
			foreach ($user->getGroups() as $groupId) {
				if (!in_array((int) $groupId, [(int) ICMS_GROUP_USERS, (int) ICMS_GROUP_ANONYMOUS], true) && isset($groupNames[$groupId])) {
					$groups[] = mod_imforum_View::escape((string) $groupNames[$groupId]);
				}
			}
			$avatar = (string) $user->getVar('user_avatar', 'n');
			$info[$uid] = [
				'uid' => $uid,
				'name' => mod_imforum_View::escape((string) $user->getVar('uname', 'n')),
				'url' => mod_imforum_View::escape(ICMS_URL . '/userinfo.php?uid=' . $uid),
				'avatar' => ($avatar !== '' && $avatar !== 'blank.gif') ? mod_imforum_View::escape(ICMS_UPLOAD_URL . '/' . $avatar) : '',
				'rank_title' => mod_imforum_View::escape((string) ($rank['title'] ?? '')),
				'rank_image' => !empty($rank['title']) ? mod_imforum_View::escape((string) ($rank['image'] ?? '')) : '',
				'groups' => $groups,
				'posts' => $counts[$uid] ?? 0,
				'regdate' => mod_imforum_View::time((int) $user->getVar('user_regdate', 'n')),
				'signature' => self::formatSignature((string) $user->getVar('user_sig', 'n')),
				'online' => isset($online[$uid]),
			];
		}
		return $info;
	}

	private static function formatSignature(string $signature): string {
		return trim($signature) === '' ? '' : (string) icms_core_DataFilter::filterTextareaDisplay($signature, 1, 1, 0, 1);
	}
}
