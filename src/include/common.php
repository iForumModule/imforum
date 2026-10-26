<?php
/**
 * Constants shared by every imforum entry point
 *
 * The core reads IMFORUM_DB_VERSION through constant() without a defined()
 * check (icms_db_legacy_updater_Handler::moduleUpgrade), so this file must be
 * loaded before every install or update.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

if (!defined('IMFORUM_DIRNAME')) {
	/** The directory and module name; IPF derives table and class names from it */
	define('IMFORUM_DIRNAME', basename(dirname(__DIR__)));
	define('IMFORUM_ROOT_PATH', ICMS_ROOT_PATH . '/modules/' . IMFORUM_DIRNAME . '/');
	define('IMFORUM_URL', ICMS_URL . '/modules/' . IMFORUM_DIRNAME . '/');
	define('IMFORUM_UPLOAD_PATH', ICMS_UPLOAD_PATH . '/' . IMFORUM_DIRNAME . '/');

	/** Raise by one for every release that needs an imforum_db_upgrade_<n>() step */
	define('IMFORUM_DB_VERSION', 1);
}

// Form labels and permission captions. The core does not load a module's
// common.php by itself, and the handlers need it as soon as they are built.
if (function_exists('icms_loadLanguageFile')) {
	icms_loadLanguageFile(IMFORUM_DIRNAME, 'common');
	// the notification forms and the block list show the titles of icms_version.php
	icms_loadLanguageFile(IMFORUM_DIRNAME, 'modinfo');
}

/**
 * Make sure uploads/imforum exists and cannot be browsed or run, without granting world-write access
 *
 * Attachments are stored under random names without extension and only
 * served by attachment.php, so the web server needs no direct access.
 */
function imforum_create_upload_folder(): bool {
	if (!is_dir(IMFORUM_UPLOAD_PATH) && !mkdir(IMFORUM_UPLOAD_PATH, 0755, true) && !is_dir(IMFORUM_UPLOAD_PATH)) {
		return false;
	}
	if (!is_file(IMFORUM_UPLOAD_PATH . '.htaccess')) {
		file_put_contents(IMFORUM_UPLOAD_PATH . '.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
	}
	if (!is_file(IMFORUM_UPLOAD_PATH . 'index.html')) {
		file_put_contents(IMFORUM_UPLOAD_PATH . 'index.html', '');
	}
	return true;
}
