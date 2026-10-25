<?php

declare(strict_types=1);

namespace Imforum\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every field of every object has a form label in language/english/common.php
 *
 * IPF builds the constant name from the module, item and field
 * (icms_ipf_Object::initVar); a missing label leaves the form field blank.
 */
final class LabelsTest extends TestCase
{
	/** @return iterable<string, array{string}> */
	public static function items(): iterable
	{
		$modversion = [];
		include IMFORUM_TEST_MODULE_PATH . '/icms_version.php';
		foreach ($modversion['object_items'] as $item) {
			yield $item => [$item];
		}
	}

	#[DataProvider('items')]
	public function testEveryFieldHasALabel(string $item): void
	{
		$source = (string) file_get_contents(IMFORUM_TEST_MODULE_PATH . '/class/' . ucfirst($item) . '.php');
		preg_match_all("/quickInitVar\\('([a-z_]+)'/", $source, $matches);
		self::assertNotEmpty($matches[1]);

		$missing = [];
		foreach ($matches[1] as $field) {
			$constant = strtoupper('_CO_IMFORUM_' . $item . '_' . $field);
			if (!defined($constant)) {
				$missing[] = $constant;
			}
		}
		self::assertSame([], $missing);
	}

	public function testEveryPermissionHasACaption(): void
	{
		$source = (string) file_get_contents(IMFORUM_TEST_MODULE_PATH . '/class/ForumHandler.php');
		preg_match("/PERMISSIONS = \\[(.*?)\\];/s", $source, $match);
		preg_match_all("/'([a-z_]+)'/", $match[1] ?? '', $permissions);
		self::assertCount(8, $permissions[1]);

		foreach ([...$permissions[1], 'category_access'] as $permission) {
			self::assertTrue(defined(strtoupper('_CO_IMFORUM_PERM_' . $permission)), $permission);
			self::assertTrue(defined(strtoupper('_CO_IMFORUM_PERM_' . $permission . '_DSC')), $permission);
		}
	}
}
