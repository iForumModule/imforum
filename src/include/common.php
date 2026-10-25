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
}
