<?php
/**
 * Suspensions: members or IP addresses that may read but not post
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

include_once __DIR__ . '/admin_header.php';

$handler = icms_getModuleHandler('suspension', IMFORUM_DIRNAME);
$op = imforum_admin_op(['addsuspension']);

switch ($op) {
	case 'mod':
		$suspension = $handler->get(imforum_admin_int('suspension_id'));
		if ($suspension->isNew()) {
			$suspension->setVar('start_time', time());
			$suspension->setVar('end_time', time() + 7 * 86400);
		}
		$title = $suspension->isNew() ? _AM_IMFORUM_SUSPENSION_ADD : _AM_IMFORUM_SUSPENSION_EDIT;
		imforum_admin_page(5, $title, static fn (): string => $suspension->getSecureForm($title, 'addsuspension')->render(), _AM_IMFORUM_SUSPENSION_FORM_DSC);
		break;

	case 'addsuspension':
		imforum_admin_store($handler, _AM_IMFORUM_SUSPENSION_CREATED, _AM_IMFORUM_SUSPENSION_MODIFIED, imforum_admin_url('suspension.php'));
		break;

	case 'del':
		$controller = new icms_ipf_Controller($handler);
		$controller->handleObjectDeletion();
		break;

	default:
		$table = new icms_ipf_view_Table($handler);
		$table->addColumn(new icms_ipf_view_Column('uid', _GLOBAL_LEFT, false, 'getUserLink'));
		$table->addColumn(new icms_ipf_view_Column('ip'));
		$table->addColumn(new icms_ipf_view_Column('forum_id', _GLOBAL_LEFT, false, 'getForumName'));
		$table->addColumn(new icms_ipf_view_Column('start_time', 'center', 140));
		$table->addColumn(new icms_ipf_view_Column('end_time', 'center', 140));
		$table->setDefaultSort('end_time');
		$table->setDefaultOrder('DESC');
		$table->addIntroButton('addsuspension', imforum_admin_url('suspension.php', 'op=mod'), _AM_IMFORUM_SUSPENSION_ADD);
		imforum_admin_page(5, _AM_IMFORUM_SUSPENSIONS, $table->fetch(), _AM_IMFORUM_SUSPENSIONS_DSC);
		break;
}
