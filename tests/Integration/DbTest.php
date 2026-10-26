<?php

declare(strict_types=1);

namespace Imforum\Tests\Integration;

use mod_imforum_Db as Db;
use mod_imforum_Settings as Settings;

/**
 * The raw SQL helper over the core's PDO connection
 */
final class DbTest extends IntegrationTestCase
{
	public function testTableNamesCarryThePrefixAndTheModule(): void
	{
		self::assertSame('imft_imforum_topic', Db::table('topic'));
		self::assertSame(self::handler('topic')->table, Db::table('topic'));
	}

	public function testValuesAreBoundAndNeverGluedIntoTheSql(): void
	{
		$forumId = (int) self::forum((int) self::category()->id())->id();
		$evil = "x' OR '1'='1";
		$topic = self::make('topic', ['forum_id' => $forumId, 'topic_title' => $evil, 'topic_poster' => 1]);

		self::assertSame(1, Db::value('SELECT COUNT(*) FROM ' . Db::table('topic') . ' WHERE topic_title = ?', [$evil]));
		self::assertSame(0, Db::value('SELECT COUNT(*) FROM ' . Db::table('topic') . ' WHERE topic_title = ?', ["nothing' OR '1'='1"]));
		self::assertSame([(int) $topic->id()], array_map('intval', Db::row('SELECT topic_id FROM ' . Db::table('topic') . ' WHERE topic_title = ?', [$evil])));
	}

	public function testIntegersWorkInALimitAndListsGetOnePlaceholderPerValue(): void
	{
		$forumId = (int) self::forum((int) self::category()->id())->id();
		$ids = [];
		for ($i = 0; $i < 5; $i++) {
			$ids[] = (int) self::topic($forumId, "Topic $i")->id();
		}
		$table = Db::table('topic');
		self::assertSame('?,?,?', Db::marks([1, 2, 3]));
		$rows = Db::rows("SELECT topic_id FROM $table WHERE topic_id IN (" . Db::marks($ids) . ') ORDER BY topic_id LIMIT ?, ?', [...$ids, 1, 2]);
		self::assertSame([$ids[1], $ids[2]], array_map(static fn (array $row): int => (int) $row[0], $rows));
	}

	public function testWritesReportTheRowsChangedAndFailuresAreVisible(): void
	{
		$forumId = (int) self::forum((int) self::category()->id())->id();
		self::topic($forumId);
		self::topic($forumId, 'Second');
		$table = Db::table('topic');
		self::assertSame(2, Db::execute("UPDATE $table SET topic_views = ? WHERE forum_id = ?", [7, $forumId]));
		self::assertSame(0, Db::execute("UPDATE $table SET topic_views = ? WHERE forum_id = ?", [7, 99999]));
		self::assertNull(Db::execute('UPDATE imforum_no_such_table SET a = 1'));
		self::assertFalse(Db::ok('DELETE FROM imforum_no_such_table'));
		self::assertTrue(Db::ok("DELETE FROM $table WHERE forum_id = ?", [99999]));
		self::assertSame([], Db::rows('SELECT * FROM imforum_no_such_table'));
	}

	public function testItNeedsThePdoConnection(): void
	{
		$saved = \icms::$db;
		\icms::$db = null;
		try {
			$this->expectException(\RuntimeException::class);
			Db::connection();
		} finally {
			\icms::$db = $saved;
		}
	}

	public function testSearchKeepsWorkingWithBoundWords(): void
	{
		$category = (int) self::category()->id();
		$forumId = (int) self::forum($category)->id();
		self::grantCategory($category, [3]);
		self::grant($forumId, ['forum_access', 'forum_view'], [3]);
		Settings::override([]);
		$topic = self::topic($forumId, '100% sure');
		self::topic($forumId, 'Other thing');

		require_once ICMS_ROOT_PATH . '/modules/imforum/include/search.inc.php';
		$permissionsGuest = new \mod_imforum_PermissionService([3], 0);
		self::assertSame([$forumId], $permissionsGuest->getReadableForumIds());
		$user = \icms::$user;
		\icms::$user = null;
		try {
			$hits = imforum_search(['100%'], 'AND', 10, 0, 0);
			self::assertCount(1, $hits, '% in a word is a plain character');
			self::assertStringContainsString('topic=' . $topic->id(), $hits[0]['link']);
			self::assertCount(0, imforum_search(["' OR '1'='1"], 'AND', 10, 0, 0));
			self::assertCount(2, imforum_search(['sure', 'thing'], 'OR', 10, 0, 0));
			self::assertCount(1, imforum_search(['sure', 'thing'], 'OR', 1, 1, 0), 'limit and offset');
		} finally {
			\icms::$user = $user;
			Settings::override(null);
		}
	}
}
