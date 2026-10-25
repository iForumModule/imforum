<?php
/**
 * Permission template: the groups a new forum starts with
 *
 * Optionally applies the template to existing forums, replacing their
 * permissions. iForum kept the template in a cache file; here it is a table.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

include_once __DIR__ . '/admin_header.php';

$handler = icms_getModuleHandler('permtemplate', IMFORUM_DIRNAME);
$forumHandler = icms_getModuleHandler('forum', IMFORUM_DIRNAME);
$op = imforum_admin_op(['save']);

if ($op === 'save') {
	$template = [];
	foreach (mod_imforum_ForumHandler::PERMISSIONS as $permission) {
		$template[$permission] = array_map('intval', (array) ($_POST[$permission] ?? []));
	}
	$ok = $handler->saveTemplate($template);

	$applyTo = array_map('intval', (array) ($_POST['apply_forums'] ?? []));
	if ($applyTo) {
		$permissions = new icms_ipf_permission_Handler($forumHandler);
		foreach ($applyTo as $forumId) {
			if ($forumHandler->get($forumId)->isNew()) {
				continue;
			}
			foreach ($template as $permission => $groups) {
				$ok = $permissions->saveItem_Permissions($groups, $forumId, $permission) && $ok;
			}
		}
	}
	redirect_header(imforum_admin_url('permtemplate.php'), 2, $ok ? _AM_IMFORUM_PERMTEMPLATE_SAVED : _AM_IMFORUM_PERMTEMPLATE_ERROR);
	exit;
}

$form = new icms_form_Theme(_AM_IMFORUM_PERMTEMPLATE, 'permtemplate', imforum_admin_url('permtemplate.php'), 'post', true);
foreach ($handler->getTemplate() as $permission => $groups) {
	$caption = '_CO_IMFORUM_PERM_' . strtoupper($permission);
	$select = new icms_form_elements_select_Group(constant($caption), $permission, true, $groups, 5, true);
	$select->setDescription(constant($caption . '_DSC'));
	imforum_form_add($form, $select);
}
$forums = new icms_form_elements_Select(_AM_IMFORUM_PERMTEMPLATE_APPLY, 'apply_forums', null, 8, true);
$forums->addOptionArray($forumHandler->getForumSelectList());
$forums->setDescription(_AM_IMFORUM_PERMTEMPLATE_APPLY_DSC);
imforum_form_add($form, $forums);
imforum_form_add($form, new icms_form_elements_Hidden('op', 'save'));
imforum_form_add($form, new icms_form_elements_Button('', 'submit', _CO_ICMS_SUBMIT, 'submit'));

imforum_admin_page(3, _AM_IMFORUM_PERMTEMPLATE, $form->render(), _AM_IMFORUM_PERMTEMPLATE_DSC);
