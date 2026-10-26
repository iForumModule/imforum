<?php
/**
 * Handler for attachments
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_AttachmentHandler extends icms_ipf_Handler {

	public function __construct(icms_db_legacy_Database $db) {
		parent::__construct($db, 'attachment', 'attach_id', 'name_display', '', IMFORUM_DIRNAME);
	}

	/**
	 * The attachments of these posts that are online
	 *
	 * @param list<int> $postIds
	 * @return array<int, list<mod_imforum_Attachment>> post id => attachments, oldest first
	 */
	public function getByPosts(array $postIds): array {
		$postIds = array_values(array_filter(array_map('intval', $postIds)));
		if (!$postIds) {
			return [];
		}
		$criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('post_id', '(' . implode(',', $postIds) . ')', 'IN'));
		$criteria->add(new icms_db_criteria_Item('online', 1));
		$criteria->setSort('attach_id');
		$criteria->setOrder('ASC');
		$byPost = [];
		foreach ($this->getObjects($criteria) as $attachment) {
			$byPost[(int) $attachment->getVar('post_id', 'e')][] = $attachment;
		}
		return $byPost;
	}

	public function incrementDownloads(int $attachId): bool {
		return mod_imforum_Db::ok("UPDATE {$this->table} SET downloads = downloads + 1 WHERE attach_id = ?", [$attachId]);
	}

	public function countForPost(int $postId): int {
		return $this->getCount(new icms_db_criteria_Item('post_id', $postId));
	}

	protected function beforeInsert(mod_imforum_Attachment $attachment): bool {
		if ((int) $attachment->getVar('attach_time', 'e') === 0) {
			$attachment->setVar('attach_time', time());
		}
		return true;
	}

	/**
	 * Remove the file together with its record
	 *
	 * A file that is already gone is not an error.
	 */
	protected function afterDelete(mod_imforum_Attachment $attachment): bool {
		$path = $attachment->getFilePath();
		return !is_file($path) || unlink($path);
	}
}
