<?php
/**
 * Handler for reports
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_ReportHandler extends icms_ipf_Handler {

	public function __construct(icms_db_legacy_Database $db) {
		parent::__construct($db, 'report', 'report_id', 'report_text', '', IMFORUM_DIRNAME);
	}

	public function getOpenCount(): int {
		return (int) $this->getCount(new icms_db_criteria_Item('report_result', mod_imforum_Report::RESULT_OPEN));
	}

	protected function beforeInsert(mod_imforum_Report $report): bool {
		if ((int) $report->getVar('report_time', 'e') === 0) {
			$report->setVar('report_time', time());
		}
		return true;
	}
}
