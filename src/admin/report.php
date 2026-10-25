<?php
/**
 * Reports: open reports first, mark them handled with a note, delete
 *
 * Reports come from members (phase 5); there is no "add" here.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

include_once __DIR__ . '/admin_header.php';

$handler = icms_getModuleHandler('report', IMFORUM_DIRNAME);
$op = imforum_admin_op(['addreport']);

switch ($op) {
	case 'mod':
		$report = $handler->get(imforum_admin_int('report_id'));
		if ($report->isNew()) {
			redirect_header(imforum_admin_url('report.php'), 3, _CO_ICMS_NOT_SELECTED);
			exit;
		}
		// the member's text stays as written; only the outcome and the note change
		$report->makeFieldReadOnly('report_text');
		imforum_admin_page(4, _AM_IMFORUM_REPORT_HANDLE, static fn (): string => $report->getSecureForm(_AM_IMFORUM_REPORT_HANDLE, 'addreport')->render());
		break;

	case 'addreport':
		imforum_admin_store($handler, '', _AM_IMFORUM_REPORT_MODIFIED, imforum_admin_url('report.php'));
		break;

	case 'del':
		$controller = new icms_ipf_Controller($handler);
		$controller->handleObjectDeletion();
		break;

	default:
		$showAll = imforum_admin_int('all') === 1;
		$criteria = $showAll ? new icms_db_criteria_Compo() : new icms_db_criteria_Compo(new icms_db_criteria_Item('report_result', mod_imforum_Report::RESULT_OPEN));
		$table = new icms_ipf_view_Table($handler, $criteria);
		$table->addColumn(new icms_ipf_view_Column('post_id', _GLOBAL_LEFT, false, 'getPostSubject'));
		$table->addColumn(new icms_ipf_view_Column('report_text'));
		$table->addColumn(new icms_ipf_view_Column('reporter_uid', _GLOBAL_LEFT, 120, 'getReporterLink'));
		$table->addColumn(new icms_ipf_view_Column('report_time', 'center', 140));
		$table->addColumn(new icms_ipf_view_Column('report_result', 'center', 80));
		$table->setDefaultSort('report_time');
		$table->setDefaultOrder('DESC');

		$toggle = $showAll
			? '<a href="' . imforum_admin_url('report.php') . '">' . _AM_IMFORUM_REPORTS_SHOW_OPEN . '</a>'
			: '<a href="' . imforum_admin_url('report.php', 'all=1') . '">' . _AM_IMFORUM_REPORTS_SHOW_ALL . '</a>';
		imforum_admin_page(4, _AM_IMFORUM_REPORTS, '<p>' . $toggle . '</p>' . $table->fetch(), _AM_IMFORUM_REPORTS_DSC);
		break;
}
