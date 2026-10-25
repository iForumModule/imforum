<?php
/**
 * Categories: list, add, edit, delete
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

include_once __DIR__ . '/admin_header.php';

$handler = icms_getModuleHandler('category', IMFORUM_DIRNAME);
$op = imforum_admin_op(['addcategory']);

switch ($op) {
	case 'mod':
		$category = $handler->get(imforum_admin_int('cat_id'));
		$title = $category->isNew() ? _AM_IMFORUM_CATEGORY_ADD : _AM_IMFORUM_CATEGORY_EDIT;
		imforum_admin_page(1, $title, static fn (): string => $category->getSecureForm($title, 'addcategory')->render());
		break;

	case 'addcategory':
		imforum_admin_store($handler, _AM_IMFORUM_CATEGORY_CREATED, _AM_IMFORUM_CATEGORY_MODIFIED, imforum_admin_url('category.php'));
		break;

	case 'del':
		$controller = new icms_ipf_Controller($handler);
		$controller->handleObjectDeletion();
		break;

	default:
		$table = new icms_ipf_view_Table($handler);
		$table->addColumn(new icms_ipf_view_Column('cat_title', _GLOBAL_LEFT, false, 'getAdminLink'));
		$table->addColumn(new icms_ipf_view_Column('weight', 'center', 80));
		$table->setDefaultSort('weight');
		$table->addIntroButton('addcategory', imforum_admin_url('category.php', 'op=mod'), _AM_IMFORUM_CATEGORY_ADD);
		imforum_admin_page(1, _AM_IMFORUM_CATEGORIES, $table->fetch(), _AM_IMFORUM_CATEGORIES_DSC);
		break;
}
