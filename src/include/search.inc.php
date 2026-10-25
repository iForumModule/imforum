<?php
/**
 * Search function for the site search of the core
 *
 * Looks in the subject and the text of approved posts, in the forums the
 * visitor may read. Posts that ask for karma or a reply are matched on their
 * subject only, so the search cannot be used to read around the rule.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once __DIR__ . '/common.php';

/**
 * @param array<int, string>|false $queryarray the words to look for
 * @param string $andor 'AND', 'OR' or 'exact'
 * @param int $limit
 * @param int $offset
 * @param int $userid only posts of this member, 0 for all
 * @return list<array{image: string, link: string, title: string, time: int, uid: int}>
 */
function imforum_search($queryarray, $andor, $limit, $offset, $userid) {
	$permissions = mod_imforum_PermissionService::forCurrentUser();
	$forumIds = $permissions->getReadableForumIds();
	if (!$forumIds) {
		return [];
	}
	$db = icms::$xoopsDB;
	$posts = $db->prefix(IMFORUM_DIRNAME . '_post');

	$where = 'p.approved = 1 AND p.forum_id IN (' . implode(',', array_map('intval', $forumIds)) . ')';
	if ((int) $userid > 0) {
		$where .= ' AND p.uid = ' . (int) $userid;
	}
	if (is_array($queryarray) && $queryarray) {
		$conditions = [];
		foreach ($queryarray as $word) {
			// % and _ in a word are plain characters
			$like = $db->quoteString('%' . addcslashes((string) $word, '\\%_') . '%');
			$conditions[] = "(p.subject LIKE $like OR (p.post_karma = 0 AND p.require_reply = 0 AND p.post_text LIKE $like))";
		}
		$where .= ' AND (' . implode($andor === 'OR' ? ' OR ' : ' AND ', $conditions) . ')';
	}

	$hits = [];
	$result = $db->query(
		"SELECT p.post_id, p.topic_id, p.uid, p.post_time, p.subject FROM $posts p WHERE $where ORDER BY p.post_id DESC",
		max(1, (int) $limit),
		max(0, (int) $offset)
	);
	while ($result && ($row = $db->fetchRow($result))) {
		$hits[] = [
			'image' => 'images/imforum_iconsmall.png',
			'link' => 'topic.php?topic=' . (int) $row[1] . '&post=' . (int) $row[0] . '#post-' . (int) $row[0],
			'title' => (string) $row[4],
			'time' => (int) $row[3],
			'uid' => (int) $row[2],
		];
	}
	return $hits;
}
