<?php

declare(strict_types=1);

namespace Imforum\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The old iForum addresses must land on the same content in imforum
 */
final class LegacyUrlTest extends TestCase
{
	public static function setUpBeforeClass(): void
	{
		require_once IMFORUM_TEST_MODULE_PATH . '/class/LegacyUrl.php';
	}

	/**
	 * @return array<string, array{string, array<string, mixed>, string}>
	 */
	public static function addresses(): array
	{
		return [
			'index' => ['index', ['cat' => '2'], 'index.php'],
			'forum' => ['viewforum', ['forum' => '7'], 'forum.php?forum=7'],
			'forum page, sort options dropped' => ['viewforum', ['forum' => '7', 'start' => '20', 'sortname' => 'u.uname', 'since' => '5'], 'forum.php?forum=7&start=20'],
			'forum without id' => ['viewforum', [], 'index.php'],
			'topic' => ['viewtopic', ['topic_id' => '12', 'forum' => '3'], 'topic.php?topic=12'],
			'topic page' => ['viewtopic', ['topic_id' => '12', 'start' => '30'], 'topic.php?topic=12&start=30'],
			'topic thread view' => ['viewtopic', ['topic_id' => '12', 'viewmode' => 'thread'], 'topic.php?topic=12&mode=tree'],
			'topic compact view is flat' => ['viewtopic', ['topic_id' => '12', 'viewmode' => 'compact'], 'topic.php?topic=12'],
			'topic and post' => ['viewtopic', ['topic_id' => '12', 'post_id' => '99', 'start' => '30'], 'topic.php?topic=12&post=99'],
			'only a forum known' => ['viewtopic', ['forum' => '3'], 'forum.php?forum=3'],
			'nothing known' => ['viewtopic', [], 'index.php'],
			'latest' => ['viewall', ['type' => 'new'], 'viewall.php'],
			'unread' => ['viewall', ['type' => 'unread', 'start' => '10'], 'viewall.php?type=unread&start=10'],
			'digest' => ['viewall', ['type' => 'digest'], 'viewall.php?type=digest'],
			'posts list' => ['viewpost', ['uid' => '4'], 'viewall.php'],
			'one feed' => ['rss', ['f' => '5'], 'rss.php?forum=5'],
			'feeds of several forums' => ['rss', ['f' => '5|6'], 'rss.php'],
			'all feeds' => ['rss', ['c' => '1'], 'rss.php'],
			'unknown script' => ['whatever', [], 'index.php'],
			'array value is ignored' => ['viewforum', ['forum' => ['1']], 'index.php'],
		];
	}

	/**
	 * @param array<string, mixed> $query
	 */
	#[DataProvider('addresses')]
	public function testAddress(string $script, array $query, string $expected): void
	{
		self::assertSame($expected, \mod_imforum_LegacyUrl::target($script, $query));
	}

	public function testTheTopicOfAPostIsLookedUp(): void
	{
		$lookup = static fn (int $postId): int => $postId === 99 ? 12 : 0;

		self::assertSame('topic.php?topic=12&post=99', \mod_imforum_LegacyUrl::target('viewtopic', ['post_id' => '99'], $lookup));
		self::assertSame('forum.php?forum=3', \mod_imforum_LegacyUrl::target('viewtopic', ['post_id' => '5', 'forum' => '3'], $lookup));
	}

	public function testEveryScriptHasAStubFile(): void
	{
		foreach (\mod_imforum_LegacyUrl::SCRIPTS as $script) {
			self::assertFileExists(dirname(__DIR__, 2) . '/stub/iforum/' . $script . '.php');
		}
	}
}
