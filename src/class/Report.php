<?php
/**
 * A report of a post to the moderators
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_Report extends icms_ipf_Object {

	public const RESULT_OPEN = 0;
	public const RESULT_HANDLED = 1;

	public function __construct(mod_imforum_ReportHandler $handler) {
		parent::__construct($handler);

		$this->quickInitVar('report_id', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('post_id', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('reporter_uid', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('reporter_ip', XOBJ_DTYPE_TXTBOX);
		$this->quickInitVar('report_time', XOBJ_DTYPE_LTIME, false, '', '', 0);
		$this->quickInitVar('report_text', XOBJ_DTYPE_TXTAREA, true);
		$this->quickInitVar('report_result', XOBJ_DTYPE_INT, false, '', '', self::RESULT_OPEN);
		$this->quickInitVar('report_memo', XOBJ_DTYPE_TXTAREA);

		$this->hideFieldFromForm(['post_id', 'reporter_uid', 'reporter_ip', 'report_time']);
		$this->setControl('report_result', ['name' => 'yesno']);
	}
}
