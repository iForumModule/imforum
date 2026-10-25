<?php
/**
 * Maintenance: prune inactive topics and recount all counters
 *
 * Pruning goes in two steps. The first shows how many topics match; only the
 * confirmation deletes or moves them.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

include_once __DIR__ . '/admin_header.php';

$forumHandler = icms_getModuleHandler('forum', IMFORUM_DIRNAME);
$topicHandler = icms_getModuleHandler('topic', IMFORUM_DIRNAME);
$op = imforum_admin_op(['preview', 'prune', 'recount']);

/** The prune settings as posted; the same names travel from step one to step two */
$settings = [
	'forums' => array_values(array_filter(array_map('intval', (array) ($_POST['forums'] ?? [])))),
	'days' => max(0, imforum_admin_int('days')),
	'keep_sticky' => (int) ($_POST['keep_sticky'] ?? 1) === 1,
	'keep_digest' => (int) ($_POST['keep_digest'] ?? 1) === 1,
	'move_to' => imforum_admin_int('move_to'),
];
$before = time() - $settings['days'] * 86400;

switch ($op) {
	case 'recount':
		$ok = $topicHandler->synchroniseAll() && $forumHandler->synchroniseAll();
		redirect_header(imforum_admin_url('prune.php'), 2, $ok ? _AM_IMFORUM_RECOUNT_DONE : _AM_IMFORUM_RECOUNT_ERROR);
		exit;

	case 'preview':
		if (!$settings['forums'] || $settings['days'] < 1) {
			redirect_header(imforum_admin_url('prune.php'), 3, _AM_IMFORUM_PRUNE_INCOMPLETE);
			exit;
		}
		$count = count($topicHandler->findInactive($settings['forums'], $before, $settings['keep_sticky'], $settings['keep_digest']));
		if ($count === 0) {
			redirect_header(imforum_admin_url('prune.php'), 3, _AM_IMFORUM_PRUNE_NOTHING);
			exit;
		}
		$hiddens = [
			'op' => 'prune',
			'days' => $settings['days'],
			'keep_sticky' => (int) $settings['keep_sticky'],
			'keep_digest' => (int) $settings['keep_digest'],
			'move_to' => $settings['move_to'],
			'confirm' => 1,
		];
		foreach ($settings['forums'] as $i => $forumId) {
			$hiddens["forums[$i]"] = $forumId;
		}
		$message = $settings['move_to'] > 0
			? sprintf(_AM_IMFORUM_PRUNE_CONFIRM_MOVE, $count, $forumHandler->getForumSelectList()[$settings['move_to']] ?? '')
			: sprintf(_AM_IMFORUM_PRUNE_CONFIRM_DELETE, $count);
		icms_cp_header();
		imforum_admin_menu(8, _AM_IMFORUM_PRUNE);
		icms_core_Message::confirm($hiddens, imforum_admin_url('prune.php'), $message, _AM_IMFORUM_PRUNE_GO);
		icms_cp_footer();
		break;

	case 'prune':
		$done = 0;
		foreach ($topicHandler->findInactive($settings['forums'], $before, $settings['keep_sticky'], $settings['keep_digest']) as $topicId) {
			if ($settings['move_to'] > 0) {
				$done += (int) $topicHandler->moveToForum($topicId, $settings['move_to']);
			} else {
				$topic = $topicHandler->get($topicId);
				$done += (int) (!$topic->isNew() && $topicHandler->delete($topic, true));
			}
		}
		redirect_header(imforum_admin_url('prune.php'), 3, sprintf(_AM_IMFORUM_PRUNE_DONE, $done));
		exit;

	default:
		$forums = $forumHandler->getForumSelectList();

		$prune = new icms_form_Theme(_AM_IMFORUM_PRUNE, 'prune', imforum_admin_url('prune.php'), 'post', true);
		$select = new icms_form_elements_Select(_AM_IMFORUM_PRUNE_FORUMS, 'forums', null, 8, true);
		$select->addOptionArray($forums);
		imforum_form_add($prune, $select, true);
		imforum_form_add($prune, new icms_form_elements_Text(_AM_IMFORUM_PRUNE_DAYS, 'days', 5, 5, '365'), true);
		imforum_form_add($prune, new icms_form_elements_Radioyn(_AM_IMFORUM_PRUNE_KEEP_STICKY, 'keep_sticky', '1'));
		imforum_form_add($prune, new icms_form_elements_Radioyn(_AM_IMFORUM_PRUNE_KEEP_DIGEST, 'keep_digest', '1'));
		$target = new icms_form_elements_Select(_AM_IMFORUM_PRUNE_MOVE_TO, 'move_to', 0);
		$target->addOptionArray([0 => _AM_IMFORUM_PRUNE_DELETE] + $forums);
		imforum_form_add($prune, $target);
		imforum_form_add($prune, new icms_form_elements_Hidden('op', 'preview'));
		imforum_form_add($prune, new icms_form_elements_Button('', 'submit', _AM_IMFORUM_PRUNE_PREVIEW, 'submit'));

		$recount = new icms_form_Theme(_AM_IMFORUM_RECOUNT, 'recount', imforum_admin_url('prune.php'), 'post', true);
		imforum_form_add($recount, new icms_form_elements_Label('', _AM_IMFORUM_RECOUNT_DSC));
		imforum_form_add($recount, new icms_form_elements_Hidden('op', 'recount'));
		imforum_form_add($recount, new icms_form_elements_Button('', 'submit', _AM_IMFORUM_RECOUNT, 'submit'));

		imforum_admin_page(8, _AM_IMFORUM_PRUNE, $prune->render() . '<br />' . $recount->render(), _AM_IMFORUM_PRUNE_DSC);
		break;
}
