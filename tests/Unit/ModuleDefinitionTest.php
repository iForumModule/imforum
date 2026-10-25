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
		// icms_module_Object::loadInfo sets ipf = 1 only when object_items is an array.
		self::assertIsArray(self::$modversion['object_items']);
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
