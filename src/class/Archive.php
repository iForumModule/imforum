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
}
