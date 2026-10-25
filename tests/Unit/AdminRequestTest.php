<?php

declare(strict_types=1);

namespace Imforum\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The rule every admin script applies before it runs an operation
 */
final class AdminRequestTest extends TestCase
{
	private const WRITES = ['addforum'];

	public static function setUpBeforeClass(): void
	{
		require_once IMFORUM_TEST_MODULE_PATH . '/include/admin.php';
	}

	/**
	 * @return array<string, array{string, string, bool, bool, ?string}>
	 */
	public static function requests(): array
	{
		return [
			'list on GET' => ['list', 'GET', false, false, null],
			'write on GET' => ['addforum', 'GET', false, true, '_AM_IMFORUM_POST_ONLY'],
			'write without token' => ['addforum', 'POST', false, false, '_AM_IMFORUM_BAD_TOKEN'],
			'write with token' => ['addforum', 'POST', false, true, null],
			'delete confirmation without token' => ['del', 'POST', true, false, '_AM_IMFORUM_BAD_TOKEN'],
			'delete confirmation with token' => ['del', 'POST', true, true, null],
			'delete question on GET' => ['del', 'GET', false, false, null],
		];
	}

	#[DataProvider('requests')]
	public function testRefusal(string $op, string $method, bool $confirming, bool $tokenValid, ?string $expected): void
	{
		$refusal = imforum_admin_refusal($op, self::WRITES, $method, $confirming, static fn (): bool => $tokenValid);
		self::assertSame($expected === null ? null : constant($expected), $refusal);
	}

	public function testTokenIsNotCheckedForAPlainRead(): void
	{
		$checked = false;
		imforum_admin_refusal('list', self::WRITES, 'GET', false, static function () use (&$checked): bool {
			$checked = true;
			return false;
		});
		self::assertFalse($checked, 'checking spends the token');
	}
}
