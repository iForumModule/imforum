<?php

declare(strict_types=1);

namespace Imforum\Tests\Integration;

/**
 * Stands in for icms::$config, which reads the site preferences from tables
 * these tests do not create. Every category returns the core's defaults that
 * matter here: no word censoring, English.
 */
final class CoreConfig
{
	/**
	 * @return array<string, mixed>
	 */
	public function getConfigsByCat(int $category, int $module = 0): array
	{
		return [
			'censor_enable' => false,
			'censor_words' => [],
			'censor_replace' => '',
			'language' => 'english',
		];
	}
}
