<?php
/**
 * Import the data of a running iForum installation
 *
 * The check reads and reports. Importing and rolling back first ask for a
 * confirmation and then run only on a POST with a security token.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

include_once __DIR__ . '/admin_header.php';

$op = imforum_admin_op(['run', 'rollback']);
$encoding = ($_POST['encoding'] ?? $_GET['encoding'] ?? '') === mod_imforum_Importer::ENCODING_UTF8_BYTES
	? mod_imforum_Importer::ENCODING_UTF8_BYTES
	: mod_imforum_Importer::ENCODING_AS_IS;
$importer = new mod_imforum_Importer($encoding);

/** The form that starts every action; the choice of encoding travels with it */
$form = static function () use ($encoding): string {
	$e = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	$form = new icms_form_Theme(_AM_IMFORUM_IMPORT, 'import', imforum_admin_url('import.php'), 'post', true);
	$select = new icms_form_elements_Select(_AM_IMFORUM_IMPORT_ENCODING, 'encoding', $encoding);
	$select->addOptionArray([
		mod_imforum_Importer::ENCODING_AS_IS => _AM_IMFORUM_IMPORT_ENCODING_AS_IS,
		mod_imforum_Importer::ENCODING_UTF8_BYTES => _AM_IMFORUM_IMPORT_ENCODING_BYTES,
	]);
	imforum_form_add($form, new icms_form_elements_Label('', $e(_AM_IMFORUM_IMPORT_STEPS)));
	imforum_form_add($form, $select);
	$buttons = '';
	foreach (['check' => _AM_IMFORUM_IMPORT_CHECK, 'askrun' => _AM_IMFORUM_IMPORT_RUN, 'verify' => _AM_IMFORUM_IMPORT_VERIFY, 'askrollback' => _AM_IMFORUM_IMPORT_ROLLBACK] as $value => $label) {
		$buttons .= '<button type="submit" name="op" value="' . $value . '">' . $e($label) . '</button> ';
	}
	imforum_form_add($form, new icms_form_elements_Label('', $buttons));
	return $form->render();
};

switch ($op) {
	case 'askrun':
	case 'askrollback':
		icms_cp_header();
		imforum_admin_menu(9, _AM_IMFORUM_IMPORT);
		icms_core_Message::confirm(
			['op' => $op === 'askrun' ? 'run' : 'rollback', 'encoding' => $encoding, 'confirm' => 1],
			imforum_admin_url('import.php'),
			$op === 'askrun' ? _AM_IMFORUM_IMPORT_CONFIRM_RUN : _AM_IMFORUM_IMPORT_CONFIRM_ROLLBACK,
			_AM_IMFORUM_IMPORT_GO
		);
		icms_cp_footer();
		break;

	case 'run':
		// a long import must not stop when the browser gives up waiting
		@set_time_limit(0);
		ignore_user_abort(true);
		$report = $importer->run();
		$html = imforum_import_report_html($report);
		if ($report['ok']) {
			$html .= '<h2>' . htmlspecialchars(_AM_IMFORUM_IMPORT_VERIFY, ENT_QUOTES, 'UTF-8') . '</h2>' . imforum_import_report_html($importer->verify());
		}
		imforum_admin_page(9, _AM_IMFORUM_IMPORT, static fn (): string => $form() . $html, $report['ok'] ? _AM_IMFORUM_IMPORT_DONE : '');
		break;

	case 'rollback':
		imforum_admin_page(9, _AM_IMFORUM_IMPORT, static fn (): string => $form() . imforum_import_report_html($importer->rollback()), _AM_IMFORUM_IMPORT_ROLLED_BACK);
		break;

	case 'check':
		imforum_admin_page(9, _AM_IMFORUM_IMPORT, static fn (): string => $form() . imforum_import_report_html($importer->check()));
		break;

	case 'verify':
		imforum_admin_page(9, _AM_IMFORUM_IMPORT, static fn (): string => $form() . imforum_import_report_html($importer->verify()));
		break;

	default:
		imforum_admin_page(9, _AM_IMFORUM_IMPORT, $form, _AM_IMFORUM_IMPORT_DSC);
		break;
}
