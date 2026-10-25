<?php
/**
 * Digests: the mails sent so far, to read or delete
 *
 * The digest task (phase 6) writes them; there is no "add" here.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

include_once __DIR__ . '/admin_header.php';

$handler = icms_getModuleHandler('digest', IMFORUM_DIRNAME);
$op = imforum_admin_op([]);

switch ($op) {
	case 'view':
		$digest = $handler->get(imforum_admin_int('digest_id'));
		if ($digest->isNew()) {
			redirect_header(imforum_admin_url('digest.php'), 3, _CO_ICMS_NOT_SELECTED);
			exit;
		}
		imforum_admin_page(6, sprintf(_AM_IMFORUM_DIGEST_OF, $digest->getVar('digest_time')),
			'<div class="imforum-digest">' . $digest->getVar('digest_content') . '</div>');
		break;

	case 'del':
		$controller = new icms_ipf_Controller($handler);
		$controller->handleObjectDeletion();
		break;

	default:
		$table = new icms_ipf_view_Table($handler, new icms_db_criteria_Compo(), ['delete']);
		$table->addColumn(new icms_ipf_view_Column('digest_time', _GLOBAL_LEFT, 160));
		$table->addColumn(new icms_ipf_view_Column('digest_content', _GLOBAL_LEFT, false, 'getExcerpt'));
		$table->addCustomAction('getViewLink');
		$table->setDefaultSort('digest_time');
		$table->setDefaultOrder('DESC');
		imforum_admin_page(6, _AM_IMFORUM_DIGESTS, $table->fetch(), _AM_IMFORUM_DIGESTS_DSC);
		break;
}
