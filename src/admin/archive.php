<?php
/**
 * Archive: copies of posts kept when topics were archived, to read or delete
 *
 * Moderation (phase 5) writes them; there is no "add" here.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

include_once __DIR__ . '/admin_header.php';

$handler = icms_getModuleHandler('archive', IMFORUM_DIRNAME);
$op = imforum_admin_op([]);

switch ($op) {
	case 'view':
		$archive = $handler->get(imforum_admin_int('archive_id'));
		if ($archive->isNew()) {
			redirect_header(imforum_admin_url('archive.php'), 3, _CO_ICMS_NOT_SELECTED);
			exit;
		}
		imforum_admin_page(7, $archive->getTopicTitle(), '<div class="imforum-archive">' . $archive->getVar('post_text') . '</div>');
		break;

	case 'del':
		$controller = new icms_ipf_Controller($handler);
		$controller->handleObjectDeletion();
		break;

	default:
		$table = new icms_ipf_view_Table($handler, new icms_db_criteria_Compo(), ['delete']);
		$table->addColumn(new icms_ipf_view_Column('topic_id', _GLOBAL_LEFT, false, 'getTopicTitle'));
		$table->addColumn(new icms_ipf_view_Column('post_text', _GLOBAL_LEFT, false, 'getExcerpt'));
		$table->addColumn(new icms_ipf_view_Column('archived_time', 'center', 140));
		$table->addCustomAction('getViewLink');
		$table->setDefaultSort('archived_time');
		$table->setDefaultOrder('DESC');
		imforum_admin_page(7, _AM_IMFORUM_ARCHIVE, $table->fetch(), _AM_IMFORUM_ARCHIVE_DSC);
		break;
}
