<?php
/**
 * Forums: list, add, edit, delete, with group permissions and moderators
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

include_once __DIR__ . '/admin_header.php';

$handler = icms_getModuleHandler('forum', IMFORUM_DIRNAME);
$op = imforum_admin_op(['addforum']);

switch ($op) {
	case 'mod':
		if (!icms_getModuleHandler('category', IMFORUM_DIRNAME)->getCategorySelectList()) {
			redirect_header(imforum_admin_url('category.php', 'op=mod'), 3, _AM_IMFORUM_FORUM_NEEDS_CATEGORY);
			exit;
		}
		$forum = $handler->get(imforum_admin_int('forum_id'));
		$forum->setVar('moderators', icms_getModuleHandler('moderator', IMFORUM_DIRNAME)->getModeratorIds((int) $forum->id()));
		if ($forum->isNew()) {
			$forum->setVar('cat_id', imforum_admin_int('cat_id'));
		}

		$title = $forum->isNew() ? _AM_IMFORUM_FORUM_ADD : _AM_IMFORUM_FORUM_EDIT;
		imforum_admin_page(2, $title, static function () use ($forum, $title): string {
			$form = $forum->getSecureForm($title, 'addforum');
			if ($forum->isNew()) {
				// a new forum starts with the groups of the permission template
				foreach (icms_getModuleHandler('permtemplate', IMFORUM_DIRNAME)->getTemplate() as $permission => $groups) {
					$form->setElementValue($permission, $groups);
				}
			}
			return $form->render();
		});
		break;

	case 'addforum':
		// no moderators is a choice too, so an unposted select clears them
		imforum_admin_store($handler, _AM_IMFORUM_FORUM_CREATED, _AM_IMFORUM_FORUM_MODIFIED, imforum_admin_url('forum.php'), ['moderators']);
		break;

	case 'del':
		$controller = new icms_ipf_Controller($handler);
		$controller->handleObjectDeletion(_AM_IMFORUM_FORUM_DELETE_CONFIRM);
		break;

	default:
		$table = new icms_ipf_view_Table($handler);
		$table->addColumn(new icms_ipf_view_Column('forum_name', _GLOBAL_LEFT, false, 'getAdminLink'));
		$table->addColumn(new icms_ipf_view_Column('cat_id', _GLOBAL_LEFT, false, 'getCategoryTitle'));
		$table->addColumn(new icms_ipf_view_Column('forum_topics', 'center', 80));
		$table->addColumn(new icms_ipf_view_Column('forum_posts', 'center', 80));
		$table->addColumn(new icms_ipf_view_Column('forum_active', 'center', 60));
		$table->addColumn(new icms_ipf_view_Column('weight', 'center', 60));
		$table->setDefaultSort('cat_id');
		$table->addIntroButton('addforum', imforum_admin_url('forum.php', 'op=mod'), _AM_IMFORUM_FORUM_ADD);
		$table->addQuickSearch(['forum_name']);
		imforum_admin_page(2, _AM_IMFORUM_FORUMS, $table->fetch(), _AM_IMFORUM_FORUMS_DSC);
		break;
}
