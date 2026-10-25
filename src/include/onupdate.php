<?php
/**
 * Install and update routines
 *
 * Order of events in the core (modules/system/admin/modulesadmin/modulesadmin.php):
 * 1. this file is included;
 * 2. moduleUpgrade($module, TRUE) creates or alters the tables of all object_items,
 *    then calls imforum_db_upgrade_<n>() for every new database version;
 * 3. icms_module_install_imforum() or icms_module_update_imforum() runs.
 *
 * Because the tables are synchronised before the upgrade functions run, a column
 * that disappears from an object is dropped before any upgrade function sees it.
 * Rename fields over two releases: add the new var and copy the data first, drop
 * the old var in the next release.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once __DIR__ . '/common.php';

/**
 * Database version 1: indexes that the IPF updater does not create
 *
 * Filled in phase 1, once the objects exist.
 */
function imforum_db_upgrade_1(): bool {
	return true;
}

/**
 * Runs once after a fresh install
 */
function icms_module_install_imforum(icms_module_Object $module): bool {
	return imforum_create_upload_folder();
}

/**
 * Runs after every module update
 *
 * @param string|null $oldversion module version before the update
 * @param int|null $olddbversion database version before the update
 */
function icms_module_update_imforum(icms_module_Object $module, ?string $oldversion = null, ?int $olddbversion = null): bool {
	return imforum_create_upload_folder();
}

/**
 * Make sure uploads/imforum exists, without granting world-write access
 */
function imforum_create_upload_folder(): bool {
	if (is_dir(IMFORUM_UPLOAD_PATH)) {
		return true;
	}
	return mkdir(IMFORUM_UPLOAD_PATH, 0755, true);
}
