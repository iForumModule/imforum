<?php

declare(strict_types=1);

namespace Imforum\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The report of the importer is built from names in the old data, so it must escape them
 */
final class ImportReportTest extends TestCase
{
	public static function setUpBeforeClass(): void
	{
		require_once IMFORUM_TEST_MODULE_PATH . '/language/english/admin.php';
		require_once IMFORUM_TEST_MODULE_PATH . '/include/admin.php';
	}

	public function testNamesFromTheOldDataAreEscaped(): void
	{
		$html = imforum_import_report_html([
			'ok' => false,
			'errors' => ['<script>alert(1)</script>'],
			'warnings' => ['<b>gone.zip</b>'],
			'rows' => [['label' => '<i>Posts</i>', 'source' => 3, 'target' => 2, 'note' => '"x"']],
			'log' => ['<img src=x>'],
		]);

		self::assertStringNotContainsString('<script>', $html);
		self::assertStringNotContainsString('<b>gone', $html);
		self::assertStringNotContainsString('<i>Posts', $html);
		self::assertStringNotContainsString('<img', $html);
		self::assertStringContainsString('&lt;script&gt;', $html);
	}

	public function testAReportWithoutErrorsSaysSo(): void
	{
		$html = imforum_import_report_html(['ok' => true, 'errors' => [], 'warnings' => [], 'rows' => [], 'log' => []]);

		self::assertStringContainsString(_AM_IMFORUM_IMPORT_RESULT_OK, $html);
		self::assertStringNotContainsString('errorMsg', $html);
	}
}
