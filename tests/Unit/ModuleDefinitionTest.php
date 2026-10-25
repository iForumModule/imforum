<?php

declare(strict_types=1);

namespace Imforum\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Checks icms_version.php against what the core expects from an IPF module
 */
final class ModuleDefinitionTest extends TestCase
{
	/** @var array<string, mixed> */
	private static array $modversion;

	public static function setUpBeforeClass(): void
	{
		$modversion = [];
		include IMFORUM_TEST_MODULE_PATH . '/icms_version.php';
		self::$modversion = $modversion;
	}

	public function testDirnameAndModnameMatch(): void
	{
		// The IPF updater names tables after the dirname, the handlers after the modname.
		self::assertSame('imforum', self::$modversion['dirname']);
		self::assertSame('imforum', self::$modversion['modname']);
	}

	public function testObjectItemsEnablesIpfMode(): void
	{
		// icms_module_Object::loadInfo sets ipf = 1 only when object_items is an array,
		// and modulesadmin.php only builds tables when it is not empty.
		self::assertIsArray(self::$modversion['object_items']);
		self::assertNotEmpty(self::$modversion['object_items']);
	}

	public function testEveryObjectItemHasItsClasses(): void
	{
		// icms_ipf_Handler looks for mod_<module>_<Item>, the autoloader for class/<Item>.php
		foreach (self::$modversion['object_items'] as $item) {
			self::assertMatchesRegularExpression('/^[a-z]+$/', $item, 'item names are single lower-case words');
			foreach ([ucfirst($item), ucfirst($item) . 'Handler'] as $class) {
				$file = IMFORUM_TEST_MODULE_PATH . '/class/' . $class . '.php';
				self::assertFileExists($file);
				self::assertStringContainsString('class mod_imforum_' . $class . ' extends', (string) file_get_contents($file));
			}
		}
	}

	public function testNoObjectItemIsAReservedWord(): void
	{
		// IPF uses the item name as a table alias (SELECT ... FROM table AS <item>),
		// so a reserved word breaks every query of that handler.
		$reserved = ['add', 'all', 'alter', 'and', 'as', 'by', 'call', 'case', 'check', 'column', 'condition',
			'create', 'cross', 'current', 'database', 'default', 'delete', 'desc', 'distinct', 'drop', 'each',
			'else', 'exists', 'fetch', 'for', 'from', 'function', 'grant', 'group', 'having', 'ignore', 'in',
			'index', 'insert', 'interval', 'into', 'is', 'join', 'key', 'keys', 'kill', 'like', 'limit', 'lock',
			'match', 'not', 'option', 'or', 'order', 'out', 'range', 'read', 'reads', 'release', 'rename',
			'repeat', 'replace', 'require', 'return', 'select', 'set', 'show', 'signal', 'table', 'then', 'to',
			'trigger', 'union', 'unique', 'update', 'usage', 'use', 'using', 'values', 'when', 'where', 'while',
			'with', 'write', 'rank', 'rows', 'row', 'system', 'window', 'groups', 'member', 'role'];
		self::assertSame([], array_values(array_intersect(self::$modversion['object_items'], $reserved)));
	}

	public function testReferencedFilesExist(): void
	{
		foreach (['onInstall', 'onUpdate', 'adminindex', 'adminmenu', 'iconsmall', 'iconbig', 'image'] as $key) {
			self::assertFileExists(IMFORUM_TEST_MODULE_PATH . '/' . self::$modversion[$key], $key);
		}
	}

	public function testEveryTemplateExists(): void
	{
		self::assertNotEmpty(self::$modversion['templates']);
		foreach (self::$modversion['templates'] as $template) {
			self::assertFileExists(IMFORUM_TEST_MODULE_PATH . '/templates/' . $template['file']);
		}
	}

	public function testDatabaseVersionIsDefined(): void
	{
		// moduleUpgrade() reads it with constant() and would throw if it were missing.
		self::assertTrue(defined('IMFORUM_DB_VERSION'));
		self::assertIsInt(IMFORUM_DB_VERSION);
		self::assertGreaterThanOrEqual(1, IMFORUM_DB_VERSION);
	}
}
