<?php
/**
 * Helpers shared by the admin scripts
 *
 * Only function definitions: including this file has no side effects, so the
 * request checks can be unit tested.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

/**
 * Why a request may not run an admin operation, or null when it may
 *
 * Operations that write run only on POST with a valid security token. A POST
 * that confirms a deletion needs a valid token as well:
 * icms_ipf_Controller::handleObjectDeletion() and storeFromDefaultForm() do
 * not check it themselves.
 *
 * @param list<string> $writeOps operations that change data
 * @param callable(): bool $tokenValid checks (and spends) the token
 */
function imforum_admin_refusal(string $op, array $writeOps, string $method, bool $confirming, callable $tokenValid): ?string {
	$writes = in_array($op, $writeOps, true);
	if ($writes && $method !== 'POST') {
		return _AM_IMFORUM_POST_ONLY;
	}
	if (($writes || ($method === 'POST' && $confirming)) && !$tokenValid()) {
		return _AM_IMFORUM_BAD_TOKEN;
	}
	return null;
}

/**
 * The requested operation, after the checks of imforum_admin_refusal()
 *
 * A refused request goes back to the list of the current script.
 *
 * @param list<string> $writeOps
 */
function imforum_admin_op(array $writeOps): string {
	$op = strtolower((string) ($_POST['op'] ?? $_GET['op'] ?? ''));
	$op = (string) preg_replace('/[^a-z_]/', '', $op);

	$refusal = imforum_admin_refusal(
		$op,
		$writeOps,
		strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
		isset($_POST['confirm']),
		static fn (): bool => (bool) icms::$security->check()
	);
	if ($refusal !== null) {
		redirect_header(imforum_admin_url(basename((string) $_SERVER['SCRIPT_NAME'])), 3, $refusal);
		exit;
	}
	return $op;
}

function imforum_admin_url(string $script, string $query = ''): string {
	return IMFORUM_URL . 'admin/' . $script . ($query !== '' ? '?' . $query : '');
}

/**
 * An integer from the query string or the posted form
 */
function imforum_admin_int(string $name): int {
	return (int) ($_POST[$name] ?? $_GET[$name] ?? 0);
}

/**
 * Store the object posted from its IPF form, then redirect
 *
 * A multi-select with nothing chosen is not posted at all, and
 * icms_ipf_permission_Handler::saveItem_Permissions() fails on count(null)
 * when a permission is missing. Those fields become empty arrays first.
 *
 * @param list<string> $multiSelects other multi-select fields of the form
 */
function imforum_admin_store(icms_ipf_Handler $handler, string $created, string $modified, string $redirect, array $multiSelects = []): void {
	$fields = array_merge(array_column($handler->getPermissions() ?: [], 'perm_name'), $multiSelects);
	foreach ($fields as $field) {
		$_POST[$field] = array_map('intval', (array) ($_POST[$field] ?? []));
	}
	$controller = new icms_ipf_Controller($handler);
	$controller->storeFromDefaultForm($created, $modified, $redirect);
}

/**
 * Show the module's admin menu with one entry selected
 *
 * When the admin control panel rebuilds its menu cache it include_once's the
 * menu.php of every module into another module object, so on that first
 * request icms::$module ends up without a menu and displayAdminMenu() fails
 * on count(null). Including the file ourselves avoids that.
 */
function imforum_admin_menu(int $menuIndex, string $title): void {
	$module = icms::$module;
	// the core declares both as arrays, but they are null here
	if (empty($module->adminmenu) || empty($module->adminheadermenu)) {
		// in a scope of its own, so the file's loop variables stay there
		[$module->adminmenu, $module->adminheadermenu] = (static function (): array {
			$adminmenu = $headermenu = [];
			include dirname(__DIR__) . '/admin/menu.php';
			return [$adminmenu, $headermenu];
		})();
	}
	$module->displayAdminMenu($menuIndex, $title);
}

/**
 * Render one admin page: menu, title, optional intro and the content
 *
 * Pass a form as a closure: form elements such as the date picker add their
 * scripts to the theme, which exists only once icms_cp_header() has run.
 *
 * @param int $menuIndex position of the page in admin/menu.php
 * @param string|Closure(): string $content
 */
function imforum_admin_page(int $menuIndex, string $title, string|Closure $content, string $intro = ''): void {
	global $icmsAdminTpl;

	icms_cp_header();
	imforum_admin_menu($menuIndex, $title);
	$icmsAdminTpl->assign('imforum_title', $title);
	$icmsAdminTpl->assign('imforum_intro', $intro);
	$icmsAdminTpl->assign('imforum_content', $content instanceof Closure ? $content() : $content);
	$icmsAdminTpl->display('db:imforum_admin.html');
	icms_cp_footer();
}

/**
 * Add an element to a form
 *
 * icms_form_Base::addElement() keeps a reference to the variable it was
 * given, so reusing that variable for the next element would replace the
 * earlier one. Passing through this function gives every element its own.
 */
function imforum_form_add(icms_form_Base $form, icms_form_Element $element, bool $required = false): void {
	$form->addElement($element, $required);
}
