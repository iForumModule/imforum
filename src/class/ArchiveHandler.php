<?php
/**
 * Handler for archived posts
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_ArchiveHandler extends icms_ipf_Handler {

	public function __construct(icms_db_legacy_Database $db) {
		parent::__construct($db, 'archive', 'archive_id', '', 'post_text', IMFORUM_DIRNAME);
	}

	/**
	 * Topics with archived posts, the most recently archived first
	 *
	 * @param list<int> $forumIds the forums the reader may open
	 * @return array{rows: list<array{topic_id: int, posts: int, archived_time: int}>, total: int}
	 */
	public function getTopicsPage(array $forumIds, int $start, int $limit): array {
		$forumIds = array_values(array_filter(array_map('intval', $forumIds)));
		if (!$forumIds) {
			return ['rows' => [], 'total' => 0];
		}
		$topics = mod_imforum_Db::table('topic');
		$from = " FROM {$this->table} a JOIN $topics t ON t.topic_id = a.topic_id WHERE t.forum_id IN (" . mod_imforum_Db::marks($forumIds) . ')';

		$total = mod_imforum_Db::value("SELECT COUNT(DISTINCT a.topic_id)$from", $forumIds);
		$rows = [];
		foreach (mod_imforum_Db::rows(
			"SELECT a.topic_id, COUNT(*), MAX(a.archived_time)$from GROUP BY a.topic_id ORDER BY MAX(a.archived_time) DESC, a.topic_id DESC LIMIT ?, ?",
			[...$forumIds, max(0, $start), max(1, $limit)]
		) as $row) {
			$rows[] = ['topic_id' => (int) $row[0], 'posts' => (int) $row[1], 'archived_time' => (int) $row[2]];
		}
		return ['rows' => $rows, 'total' => $total];
	}

	/**
	 * The archived posts of a topic, oldest first
	 *
	 * @return list<mod_imforum_Archive>
	 */
	public function getByTopic(int $topicId): array {
		$criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('topic_id', $topicId));
		$criteria->setSort('post_id');
		$criteria->setOrder('ASC');
		return array_values($this->getObjects($criteria));
	}

	protected function beforeInsert(mod_imforum_Archive $archive): bool {
		if ((int) $archive->getVar('archived_time', 'e') === 0) {
			$archive->setVar('archived_time', time());
		}
		return true;
	}
}
