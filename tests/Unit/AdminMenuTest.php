<?php

declare(strict_types=1);

namespace Imforum\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Checks admin/menu.php against what icms_module_Object expects
 */
final class AdminMenuTest extends TestCase
{
	/** @var array<int, array<string, string>> */
	private static array $adminmenu;

	/** @var array<int, array<string, string>> */
	private static array $headermenu;

	public static function setUpBeforeClass(): void
	{
		$adminmenu = $headermenu = null;
		include IMFORUM_TEST_MODULE_PATH . '/admin/menu.php';
		self::$adminmenu = $adminmenu;
		self::$headermenu = $headermenu;
	}

	public function testAdminMenuHasEntries(): void
	{
		self::assertNotEmpty(self::$adminmenu);
	}

	public function testEveryEntryLinksToAnExistingScriptThatShowsItsOwnTab(): void
	{
		foreach (self::$adminmenu as $index => $entry) {
			$file = IMFORUM_TEST_MODULE_PATH . '/' . $entry['link'];
			self::assertFileExists($file);
			if ($index > 0) {
				self::assertMatchesRegularExpression('/imforum_admin_page\\(' . $index . ',/', (string) file_get_contents($file), $entry['link']);
			}
		}
	}

	public function testHeaderMenuIsANonEmptyArray(): void
	{
		// displayAdminMenu() calls count() on it; null is a fatal error on PHP 8.
		self::assertNotEmpty(self::$headermenu);
		foreach (self::$headermenu as $item) {
			self::assertArrayHasKey('title', $item);
			self::assertArrayHasKey('link', $item);
		}
	}
}
