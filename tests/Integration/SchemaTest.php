<?php

declare(strict_types=1);

namespace Imforum\Tests\Integration;

use icms;
use PHPUnit\Framework\TestCase;

/**
 * What a fresh install leaves in the database
 */
final class SchemaTest extends TestCase
{
	public function testInstallCreatesOneTablePerObject(): void
	{
		$expected = ['archive', 'attachment', 'category', 'digest', 'forum', 'moderator', 'online',
			'permtemplate', 'post', 'readmark', 'report', 'suspension', 'topic'];
		$tables = array_map(static fn (string $table): string => substr($table, strlen(icms::$xoopsDB->prefix('imforum_'))), Database::tables('imforum_'));
		sort($tables);
		self::assertSame($expected, $tables);
	}

	public function testDbVersionIsStored(): void
	{
		$module = icms::handler('icms_module')->getByDirname('imforum');
		self::assertSame(IMFORUM_DB_VERSION, (int) $module->getVar('dbversion'));
	}

	public function testEveryIndexExists(): void
	{
		foreach (imforum_indexes() as $item => $indexes) {
			foreach ($indexes as $name => $columns) {
				self::assertSame($columns, self::indexColumns($item, $name), "$item.$name");
			}
		}
	}

	public function testFulltextIndexExistsWhereTheEngineSupportsIt(): void
	{
		$columns = self::indexColumns('post', 'ft_post_text');
		if ($columns === [] && !self::engineSupportsFulltext()) {
			$this->markTestSkipped('InnoDB before MySQL 5.6 has no FULLTEXT; the install carried on without it');
		}
		self::assertSame(['subject', 'post_text'], $columns);
	}

	public function testUpgradeFunctionCanRunTwice(): void
	{
		// an interrupted update may run it again
		self::assertTrue(imforum_db_upgrade_1());
	}

	/**
	 * @return list<string>
	 */
	private static function indexColumns(string $item, string $name): array
	{
		$db = icms::$xoopsDB;
		$result = Database::exec('SHOW INDEX FROM `' . $db->prefix('imforum_' . $item) . '` WHERE Key_name = ' . $db->quoteString($name));
		$columns = [];
		while ($row = $db->fetchArray($result)) {
			$columns[(int) $row['Seq_in_index']] = (string) $row['Column_name'];
		}
		ksort($columns);
		return array_values($columns);
	}

	private static function engineSupportsFulltext(): bool
	{
		$db = icms::$xoopsDB;
		$result = Database::exec("SHOW TABLE STATUS LIKE " . $db->quoteString($db->prefix('imforum_post')));
		$status = $db->fetchArray($result);
		$version = $db->fetchRow(Database::exec('SELECT VERSION()'))[0];
		return strcasecmp((string) $status['Engine'], 'MyISAM') === 0
			|| str_contains((string) $version, 'MariaDB')
			|| version_compare((string) $version, '5.6', '>=');
	}
}
