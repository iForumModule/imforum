<?php
/**
 * A copy of a post's text, kept when a topic is archived
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_Archive extends icms_ipf_Object {

	public function __construct(mod_imforum_ArchiveHandler $handler) {
		parent::__construct($handler);

		$this->quickInitVar('archive_id', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('topic_id', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('post_id', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('post_text', XOBJ_DTYPE_TXTAREA);
		$this->quickInitVar('archived_time', XOBJ_DTYPE_LTIME, false, '', '', 0);
	}

	public function getTopicTitle(): string {
		$topic = icms_getModuleHandler('topic', IMFORUM_DIRNAME)->get((int) $this->getVar('topic_id', 'e'));
		return $topic->isNew() ? '#' . (int) $this->getVar('topic_id', 'e') : (string) $topic->getVar('topic_title');
	}

	public function getExcerpt(): string {
		return htmlspecialchars(icms_core_DataFilter::icms_substr(strip_tags((string) $this->getVar('post_text', 'n')), 0, 120), ENT_QUOTES);
	}

	/**
	 * Admin link to read the whole archived post
	 */
	public function getViewLink(): string {
		return '<a href="' . IMFORUM_URL . 'admin/archive.php?op=view&amp;archive_id=' . (int) $this->id() . '">' . _CO_IMFORUM_VIEW . '</a>';
	}
}
