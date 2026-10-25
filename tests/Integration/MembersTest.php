<?php

declare(strict_types=1);

namespace Imforum\Tests\Integration;

/**
 * Moderators, suspensions, read marks and the permission template
 */
final class MembersTest extends IntegrationTestCase
{
	public function testSetModeratorsAddsAndRemoves(): void
	{
		$forumId = (int) self::forum((int) self::category()->id())->id();
		$handler = self::handler('moderator');

		self::assertTrue($handler->setModerators($forumId, [3, 4, 4, 0]));
		self::assertEqualsCanonicalizing([3, 4], $handler->getModeratorIds($forumId));

		self::assertTrue($handler->setModerators($forumId, [4, 5]));
		self::assertEqualsCanonicalizing([4, 5], $handler->getModeratorIds($forumId));
		self::assertTrue($handler->isModerator(5, $forumId));
		self::assertFalse($handler->isModerator(3, $forumId));
		self::assertFalse($handler->isModerator(0, $forumId));
	}

	public function testSuspensionsApplyPerForumOrEverywhere(): void
	{
		$now = time();
		self::make('suspension', ['uid' => 10, 'forum_id' => 2, 'start_time' => $now - 10, 'end_time' => $now + 3600]);
		self::make('suspension', ['ip' => '192.0.2.7', 'forum_id' => 0, 'start_time' => $now - 10, 'end_time' => $now + 3600]);
		self::make('suspension', ['uid' => 11, 'forum_id' => 0, 'start_time' => $now - 7200, 'end_time' => $now - 3600]);
		$handler = self::handler('suspension');

		self::assertTrue($handler->isSuspended(10, '', 2, $now));
		self::assertFalse($handler->isSuspended(10, '', 3, $now));
		self::assertTrue($handler->isSuspended(0, '192.0.2.7', 3, $now));
		self::assertFalse($handler->isSuspended(11, '', 3, $now), 'expired');
		self::assertFalse($handler->isSuspended(0, "' OR 1=1 --", 3, $now), 'invalid address');
		self::assertFalse($handler->isSuspended(0, '', 3, $now));
	}

	public function testMarkReadKeepsOneRowPerItem(): void
	{
		$handler = self::handler('readmark');

		self::assertTrue($handler->markRead(2, 5, 100, 1000));
		self::assertTrue($handler->markRead(2, 5, 100, 1005));
		self::assertTrue($handler->markRead(2, 5, 101, 1010));
		self::assertTrue($handler->markRead(1, 5, 100, 999));

		self::assertSame(3, self::rows('readmark'));
		self::assertSame([100 => 1005, 101 => 1010], $handler->getLastRead(2, 5, [100, 101, 102]));
		self::assertSame([], $handler->getLastRead(2, 0, [100]));
		self::assertFalse($handler->markRead(2, 0, 100, 1));
	}

	public function testPermissionTemplateRoundTrip(): void
	{
		$handler = self::handler('permtemplate');

		self::assertTrue($handler->saveTemplate(['forum_view' => [1, 2, 3, 3], 'forum_post' => [1, 2], 'forum_vote' => [2]]));
		$template = $handler->getTemplate();

		self::assertSame([1, 2, 3], $template['forum_view']);
		self::assertSame([1, 2], $template['forum_post']);
		self::assertSame([], $template['forum_attach']);
		self::assertArrayNotHasKey('forum_vote', $template);

		self::assertTrue($handler->saveTemplate(['forum_view' => [2]]));
		self::assertSame([2], $handler->getTemplate()['forum_view']);
	}

	public function testEditHistoryIsStoredAsJson(): void
	{
		$forum = self::forum((int) self::category()->id());
		$post = self::reload('post', (int) self::topic((int) $forum->id())->getVar('topic_first_post_id'));

		$post->addEdit(3, 'admin', 1700000000);
		self::assertTrue(self::handler('post')->insert($post, true));

		self::assertSame(
			[['uid' => 3, 'name' => 'admin', 'time' => 1700000000]],
			self::reload('post', (int) $post->id())->getEditHistory()
		);
	}

	public function testForumPermissionsAreDeclared(): void
	{
		$names = array_column(self::handler('forum')->getPermissions(), 'perm_name');
		self::assertSame(\mod_imforum_ForumHandler::PERMISSIONS, $names);
		self::assertSame(['category_access'], array_column(self::handler('category')->getPermissions(), 'perm_name'));
	}
}
