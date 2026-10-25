<?php

declare(strict_types=1);

namespace Imforum\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Checks include/onupdate.php without a database
 */
final class InstallRoutinesTest extends TestCase
{
	public static function setUpBeforeClass(): void
	{
		require_once IMFORUM_TEST_MODULE_PATH . '/include/onupdate.php';
	}

	public function testCoreEntryPointsExist(): void
	{
		// The core calls icms_module_<install|update>_<modname>.
		self::assertTrue(function_exists('icms_module_install_imforum'));
		self::assertTrue(function_exists('icms_module_update_imforum'));
	}

	public function testUpgradeFunctionExistsForEveryDatabaseVersion(): void
	{
		for ($version = 1; $version <= IMFORUM_DB_VERSION; $version++) {
			self::assertTrue(function_exists('imforum_db_upgrade_' . $version), 'missing imforum_db_upgrade_' . $version);
		}
	}

	public function testUploadFolderIsCreatedWithoutWorldWrite(): void
	{
		if (PHP_OS_FAMILY === 'Windows') {
			self::markTestSkipped('Windows reports every directory as 0777');
		}
		self::assertTrue(imforum_create_upload_folder());
		self::assertDirectoryExists(IMFORUM_UPLOAD_PATH);
		self::assertSame(0, fileperms(IMFORUM_UPLOAD_PATH) & 0002, 'upload folder must not be world-writable');
		// a second call is a no-op
		self::assertTrue(imforum_create_upload_folder());
	}
}
