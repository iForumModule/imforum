<?php

declare(strict_types=1);

namespace Imforum\Tests\Integration;

/**
 * Topic and forum counters follow every insert, update and delete
 */
final class CountersTest extends IntegrationTestCase
{
	public function testNewTopicCountsInItsForum(): void
	{
		$forum = self::forum((int) self::category()->id());
		$topic = self::topic((int) $forum->id());

		$forum = self::reload('forum', (int) $forum->id());
		self::assertSame(1, (int) $forum->getVar('forum_topics'));
		self::assertSame(1, (int) $forum->getVar('forum_posts'));
		self::assertSame((int) $topic->getVar('topic_first_post_id'), (int) $forum->getVar('forum_last_post_id'));
		self::assertSame(0, (int) $topic->getVar('topic_replies'));
	}

	public function testRepliesUpdateTopicAndForum(): void
	{
		$forum = self::forum((int) self::category()->id());
		$topic = self::topic((int) $forum->id());
		self::post((int) $topic->id());
		$last = self::post((int) $topic->id());

		$topic = self::reload('topic', (int) $topic->id());
		self::assertSame(2, (int) $topic->getVar('topic_replies'));
		self::assertSame((int) $last->id(), (int) $topic->getVar('topic_last_post_id'));
		self::assertSame(3, (int) self::reload('forum', (int) $forum->id())->getVar('forum_posts'));
	}

	public function testPostTakesItsForumFromTheTopic(): void
	{
		$forum = self::forum((int) self::category()->id());
		$topic = self::topic((int) $forum->id());

		self::assertSame((int) $forum->id(), (int) self::post((int) $topic->id())->getVar('forum_id'));
	}

	public function testPostForAMissingTopicIsRefused(): void
	{
		$handler = self::handler('post');
		$post = $handler->create();
		$post->setVar('topic_id', 999);
		$post->setVar('subject', 'Lost');
		$post->setVar('post_text', 'Nobody reads this');

		self::assertFalse($handler->insert($post, true));
		self::assertSame(0, self::rows('post'));
	}

	public function testUnapprovedPostsDoNotCount(): void
	{
		$forum = self::forum((int) self::category()->id());
		$topic = self::topic((int) $forum->id());
		$waiting = self::post((int) $topic->id(), 'Waiting', 1, 0, 0);

		self::assertSame(0, (int) self::reload('topic', (int) $topic->id())->getVar('topic_replies'));
		self::assertSame(1, (int) self::reload('forum', (int) $forum->id())->getVar('forum_posts'));

		$waiting->setVar('approved', 1);
		self::assertTrue(self::handler('post')->insert($waiting, true));
		self::assertSame(1, (int) self::reload('topic', (int) $topic->id())->getVar('topic_replies'));
		self::assertSame(2, (int) self::reload('forum', (int) $forum->id())->getVar('forum_posts'));
	}

	public function testDeletingAReplyRecounts(): void
	{
		$forum = self::forum((int) self::category()->id());
		$topic = self::topic((int) $forum->id());
		$reply = self::post((int) $topic->id());

		self::assertTrue(self::handler('post')->delete($reply, true));
		$topic = self::reload('topic', (int) $topic->id());
		self::assertSame(0, (int) $topic->getVar('topic_replies'));
		self::assertSame((int) $topic->getVar('topic_first_post_id'), (int) $topic->getVar('topic_last_post_id'));
		self::assertSame(1, (int) self::reload('forum', (int) $forum->id())->getVar('forum_posts'));
	}
}
