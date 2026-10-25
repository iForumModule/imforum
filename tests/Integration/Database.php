<?php

declare(strict_types=1);

namespace Imforum\Tests\Integration;

use icms;
use icms_db_legacy_Factory;
use RuntimeException;

/**
 * Installs imforum into the test database the way the core does
 */
final class Database
{
	/** Core tables the IPF handlers touch: the module row and group permissions */
	private const CORE_TABLES = ['modules', 'group_permission'];

	public static function install(): void
	{
		$db = icms::$xoopsDB;

		$engine = getenv('IMFORUM_TEST_DB_ENGINE');
		if ($engine) {
			self::exec('SET SESSION default_storage_engine = ' . $db->quoteString($engine));
		}

		foreach (self::tables('') as $table) {
			self::exec("DROP TABLE `$table`");
		}
		self::createCoreTables();
		self::exec(sprintf(
			"INSERT INTO %s (name, version, isactive, dirname, hasmain, hasadmin, dbversion, modname, ipf)"
			. " VALUES ('imforum', '1.0.0', 1, 'imforum', 1, 1, 0, 'imforum', 1)",
			$db->prefix('modules')
		));

		// Same steps as modulesadmin.php: load the definition, register the
		// class path, sync the tables and run the upgrade functions, then the
		// install routine.
		icms_loadLanguageFile('imforum', 'modinfo');
		$module = icms::handler('icms_module')->getByDirname('imforum');
		$module->loadInfoAsVar('imforum');
		$module->registerClassPath();
		include_once ICMS_ROOT_PATH . '/modules/imforum/include/onupdate.php';
		$updater = icms_db_legacy_Factory::getDatabaseUpdater();
		$updater->moduleUpgrade($module, true);
		if (!icms_module_install_imforum($module)) {
			throw new RuntimeException('icms_module_install_imforum() failed');
		}
	}

	/**
	 * Empty every imforum table and the group permissions
	 */
	public static function reset(): void
	{
		foreach (self::tables('imforum_') as $table) {
			self::exec("TRUNCATE TABLE `$table`");
		}
		self::exec('TRUNCATE TABLE `' . icms::$xoopsDB->prefix('group_permission') . '`');
	}

	/**
	 * Tables with the test prefix
	 *
	 * @return list<string>
	 */
	public static function tables(string $startingWith): array
	{
		$db = icms::$xoopsDB;
		$like = str_replace('_', '\\_', $db->prefix($startingWith)) . '%';
		$result = self::exec('SHOW TABLES LIKE ' . $db->quoteString($like));
		$tables = [];
		while ($row = $db->fetchRow($result)) {
			$tables[] = (string) $row[0];
		}
		return $tables;
	}

	/**
	 * @return mixed the query result
	 */
	public static function exec(string $sql): mixed
	{
		$result = icms::$xoopsDB->queryF($sql);
		if ($result === false) {
			throw new RuntimeException(icms::$xoopsDB->error() . "\n" . $sql);
		}
		return $result;
	}

	/**
	 * Copy the core tables from the core's own install script
	 */
	private static function createCoreTables(): void
	{
		$structure = (string) file_get_contents(ICMS_ROOT_PATH . '/install/sql/mysql.structure.sql');
		foreach (self::CORE_TABLES as $table) {
			if (!preg_match('/CREATE TABLE ' . $table . ' \((.*?)\n\);/s', $structure, $match)) {
				throw new RuntimeException("CREATE TABLE $table not found in mysql.structure.sql");
			}
			self::exec('CREATE TABLE `' . icms::$xoopsDB->prefix($table) . '` (' . $match[1] . ')');
		}
	}
}
