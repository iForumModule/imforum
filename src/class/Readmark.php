<?php
/**
 * The last post a member has read in a forum or topic
 *
 * Merges bb_reads_forum and bb_reads_topic. The item is called readmark
 * because READ is a reserved word in MySQL and IPF uses the item name as a
 * table alias. Guests keep their read marks in a
 * cookie (phase 3).
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_Readmark extends icms_ipf_Object {

	public function __construct(mod_imforum_ReadmarkHandler $handler) {
		parent::__construct($handler);

		$this->quickInitVar('read_id', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('read_type', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('uid', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('item_id', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('post_id', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('read_time', XOBJ_DTYPE_LTIME, false, '', '', 0);
	}
}
