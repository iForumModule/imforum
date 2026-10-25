<?php

declare(strict_types=1);

namespace Imforum\Tests\Integration;

/**
 * The handler methods behind the admin screens: moderators on the forum form,
 * prune and recount
 */
final class AdminToolsTest extends IntegrationTestCase
{
	public function testForumFormWritesModeratorsAndUndoesStaleCounters(): void
	{
		$forum = self::forum((int) self::category()->id());
		self::topic((int) $forum->id());

		// As the controller does it: every var from $_POST, hidden counters included
		$forum = self::reload('forum', (int) $forum->id());
		$forum->setVar('moderators', [3, 5]);
		$forum->setVar('forum_topics', 99);
		self::assertTrue(self::handler('forum')->insert($forum, true));

		self::assertEqualsCanonicalizing([3, 5], self::handler('moderator')->getModeratorIds((int) $forum->id()));
		self::assertSame(1, (int) self::reload('forum', (int) $forum->id())->getVar('forum_topics'));
	}

	public function testSavingAForumWithoutTheModeratorsFieldKeepsThem(): void
	{
		$forum = self::forum((int) self::category()->id());
		self::handler('moderator')->setModerators((int) $forum->id(), [4]);

		$forum = self::reload('forum', (int) $forum->id());
		$forum->setVar('forum_name', 'Renamed');
		self::assertTrue(self::handler('forum')->insert($forum, true));

		self::assertSame([4], self::handler('moderator')->getModeratorIds((int) $forum->id()));
	}

	public function testSynchroniseAllRepairsEveryCounter(): void
	{
		$cat = (int) self::category()->id();
		$busy = self::forum($cat, 'Busy');
		$empty = self::forum($cat, 'Empty');
		$topic = self::topic((int) $busy->id());
		$reply = self::post((int) $topic->id());

		$prefix = \icms::$xoopsDB->prefix('imforum_');
		Database::exec("UPDATE {$prefix}forum SET forum_topics = 7, forum_posts = 7, forum_last_post_id = 7");
		Database::exec("UPDATE {$prefix}topic SET topic_replies = 7, topic_first_post_id = 7, topic_last_post_id = 7");

		self::assertTrue(self::handler('topic')->synchroniseAll());
		self::assertTrue(self::handler('forum')->synchroniseAll());

		$topic = self::reload('topic', (int) $topic->id());
		self::assertSame(1, (int) $topic->getVar('topic_replies'));
		self::assertSame((int) $reply->id(), (int) $topic->getVar('topic_last_post_id'));
		$busy = self::reload('forum', (int) $busy->id());
		self::assertSame([1, 2, (int) $reply->id()], [(int) $busy->getVar('forum_topics'), (int) $busy->getVar('forum_posts'), (int) $busy->getVar('forum_last_post_id')]);
		$empty = self::reload('forum', (int) $empty->id());
		self::assertSame([0, 0, 0], [(int) $empty->getVar('forum_topics'), (int) $empty->getVar('forum_posts'), (int) $empty->getVar('forum_last_post_id')]);
	}

	public function testFindInactiveLooksAtTheLastPost(): void
	{
		$forumId = (int) self::forum((int) self::category()->id())->id();
		$old = self::topic($forumId, 'Old');
		$revived = self::topic($forumId, 'Revived');
		$sticky = self::topic($forumId, 'Sticky');

		$prefix = \icms::$xoopsDB->prefix('imforum_');
		$longAgo = time() - 100 * 86400;
		Database::exec("UPDATE {$prefix}post SET post_time = $longAgo");
		Database::exec("UPDATE {$prefix}topic SET topic_time = $longAgo");
		Database::exec("UPDATE {$prefix}topic SET topic_sticky = 1 WHERE topic_id = " . (int) $sticky->id());
		self::post((int) $revived->id());

		$before = time() - 30 * 86400;
		self::assertSame([(int) $old->id()], self::handler('topic')->findInactive([$forumId], $before));
		self::assertEqualsCanonicalizing(
			[(int) $old->id(), (int) $sticky->id()],
			self::handler('topic')->findInactive([$forumId], $before, false)
		);
		self::assertSame([], self::handler('topic')->findInactive([], $before));
	}

	public function testMoveToForumTakesThePostsAlong(): void
	{
		$cat = (int) self::category()->id();
		$from = self::forum($cat, 'From');
		$to = self::forum($cat, 'To');
		$topic = self::topic((int) $from->id());
		self::post((int) $topic->id());

		self::assertTrue(self::handler('topic')->moveToForum((int) $topic->id(), (int) $to->id()));
		self::assertFalse(self::handler('topic')->moveToForum((int) $topic->id(), 999));

		$prefix = \icms::$xoopsDB->prefix('imforum_');
		self::assertSame(0, (int) \icms::$xoopsDB->fetchRow(Database::exec("SELECT COUNT(*) FROM {$prefix}post WHERE forum_id <> " . (int) $to->id()))[0]);
		self::assertSame(0, (int) self::reload('forum', (int) $from->id())->getVar('forum_posts'));
		$to = self::reload('forum', (int) $to->id());
		self::assertSame([1, 2], [(int) $to->getVar('forum_topics'), (int) $to->getVar('forum_posts')]);
	}
}
