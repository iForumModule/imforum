<?php

declare(strict_types=1);

namespace Imforum\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * English is the source for Crowdin; Dutch ships with the module and must stay complete
 */
final class LanguageParityTest extends TestCase
{
	/** @return iterable<string, array{string}> */
	public static function languageFiles(): iterable
	{
		foreach (glob(dirname(__DIR__, 2) . '/src/language/english/*.php') ?: [] as $file) {
			yield basename($file) => [basename($file)];
		}
	}

	#[DataProvider('languageFiles')]
	public function testDutchDefinesEveryEnglishConstant(string $file): void
	{
		$english = self::constantsIn(dirname(__DIR__, 2) . '/src/language/english/' . $file);
		$dutch = self::constantsIn(dirname(__DIR__, 2) . '/src/language/nederlands/' . $file);

		self::assertSame([], array_values(array_diff($english, $dutch)), "missing in nederlands/$file");
		self::assertSame([], array_values(array_diff($dutch, $english)), "unknown in nederlands/$file");
	}

	/** @return list<string> */
	private static function constantsIn(string $path): array
	{
		self::assertFileExists($path);
		preg_match_all("/define\\(\\s*['\"]([A-Z0-9_]+)['\"]/", (string) file_get_contents($path), $matches);
		return $matches[1];
	}
}
