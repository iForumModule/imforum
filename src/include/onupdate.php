<?php
/**
 * Install and update routines
 *
 * Order of events in the core (modules/system/admin/modulesadmin/modulesadmin.php):
 * 1. this file is included;
 * 2. moduleUpgrade($module, TRUE) creates or alters the tables of all object_items,
 *    then calls imforum_db_upgrade_<n>() for every new database version. A site
 *    that installed phase 0 (empty object_items, dbversion 0) gets its tables and
 *    imforum_db_upgrade_1() on the first update to phase 1;
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
 * The updater only creates columns and the primary key. Every index here is
 * on integer columns, so the 767-byte key limit of MySQL 5.5 never applies.
 * The FULLTEXT index needs MyISAM, or InnoDB on MySQL 5.6 or newer; where the
 * server cannot build it, the search falls back to LIKE (phase 3) and the
 * install carries on.
 */
function imforum_db_upgrade_1(): bool {
	$ok = true;
	foreach (imforum_indexes() as $table => $indexes) {
		foreach ($indexes as $name => $columns) {
			$ok = imforum_add_index($table, $name, $columns) && $ok;
		}
	}
	imforum_add_index('post', 'ft_post_text', ['subject', 'post_text'], 'FULLTEXT');
	return $ok;
}

/**
 * Regular indexes per table, by index name
 *
 * @return array<string, array<string, list<string>>>
 */
function imforum_indexes(): array {
	return [
		'forum' => [
			'idx_cat' => ['cat_id', 'weight'],
			'idx_parent' => ['parent_forum'],
		],
		'moderator' => [
			'idx_forum_uid' => ['forum_id', 'uid'],
			'idx_uid' => ['uid'],
		],
		'topic' => [
			'idx_forum_list' => ['forum_id', 'topic_sticky', 'topic_last_post_id'],
			'idx_poster' => ['topic_poster'],
			'idx_digest' => ['topic_digest', 'digest_time'],
		],
		'post' => [
			'idx_topic' => ['topic_id', 'post_id'],
			'idx_forum_uid' => ['forum_id', 'uid'],
			'idx_uid_time' => ['uid', 'post_time'],
			'idx_pid' => ['pid'],
		],
		'attachment' => [
			'idx_post' => ['post_id'],
		],
		'report' => [
			'idx_post' => ['post_id'],
			'idx_result' => ['report_result'],
		],
		'suspension' => [
			'idx_uid' => ['uid'],
			'idx_forum' => ['forum_id', 'end_time'],
		],
		'readmark' => [
			'idx_user_item' => ['uid', 'read_type', 'item_id'],
			'idx_item' => ['read_type', 'item_id'],
			'idx_time' => ['read_time'],
		],
		'online' => [
			'idx_forum' => ['forum_id', 'topic_id'],
			'idx_updated' => ['updated'],
		],
		'archive' => [
			'idx_topic' => ['topic_id'],
		],
	];
}

/**
 * Add an index unless one with that name exists already
 *
 * @param list<string> $columns
 * @param string $type '' for a regular index, 'UNIQUE' or 'FULLTEXT'
 */
function imforum_add_index(string $item, string $name, array $columns, string $type = ''): bool {
	$table = mod_imforum_Db::table($item);

	// column 2 of SHOW INDEX is the name of the index
	foreach (mod_imforum_Db::rows("SHOW INDEX FROM `$table`") as $index) {
		if ($index[2] === $name) {
			return true;
		}
	}
	return mod_imforum_Db::ok(sprintf('ALTER TABLE `%s` ADD %s INDEX `%s` (`%s`)', $table, $type, $name, implode('`, `', $columns)));
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
