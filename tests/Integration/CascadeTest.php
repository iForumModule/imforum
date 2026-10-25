<?php

declare(strict_types=1);

namespace Imforum\Tests\Integration;

/**
 * Deleting something removes what belongs to it, and nothing else
 */
final class CascadeTest extends IntegrationTestCase
{
	public function testDeletingATopicRemovesItsPostsAttachmentsAndReports(): void
	{
		$forum = self::forum((int) self::category()->id());
		$keep = self::topic((int) $forum->id(), 'Keep');
		$topic = self::topic((int) $forum->id(), 'Delete');
		$reply = self::post((int) $topic->id());
		$file = self::attachment((int) $reply->id());
		self::make('report', ['post_id' => (int) $reply->id(), 'report_text' => 'Spam']);
		self::handler('readmark')->markRead(2, 5, (int) $topic->id(), (int) $reply->id());

		self::assertTrue(self::handler('topic')->delete($topic, true));

		self::assertSame(1, self::rows('topic'));
		self::assertSame(1, self::rows('post'));
		self::assertSame(0, self::rows('attachment'));
		self::assertSame(0, self::rows('report'));
		self::assertSame(0, self::rows('readmark'));
		self::assertFileDoesNotExist($file);
		$forum = self::reload('forum', (int) $forum->id());
		self::assertSame(1, (int) $forum->getVar('forum_topics'));
		self::assertSame((int) $keep->getVar('topic_last_post_id'), (int) $forum->getVar('forum_last_post_id'));
	}

	public function testDeletingTheLastPostDeletesTheTopic(): void
	{
		$forum = self::forum((int) self::category()->id());
		$topic = self::topic((int) $forum->id());
		$first = self::reload('post', (int) $topic->getVar('topic_first_post_id'));

		self::assertTrue(self::handler('post')->delete($first, true));

		self::assertSame(0, self::rows('topic'));
		self::assertSame(0, (int) self::reload('forum', (int) $forum->id())->getVar('forum_topics'));
	}

	public function testRepliesToADeletedPostMoveUp(): void
	{
		$forum = self::forum((int) self::category()->id());
		$topic = self::topic((int) $forum->id());
		$firstId = (int) $topic->getVar('topic_first_post_id');
		$middle = self::post((int) $topic->id(), 'Middle', 1, $firstId);
		$child = self::post((int) $topic->id(), 'Child', 1, (int) $middle->id());

		self::assertTrue(self::handler('post')->delete($middle, true));

		self::assertSame($firstId, (int) self::reload('post', (int) $child->id())->getVar('pid'));
	}

	public function testDeletingAForumRemovesItsContentAndKeepsSubforums(): void
	{
		$catId = (int) self::category()->id();
		$parent = self::forum($catId, 'Parent');
		$forum = self::forum($catId, 'Doomed', (int) $parent->id());
		$sub = self::forum($catId, 'Sub', (int) $forum->id());
		self::topic((int) $forum->id());
		self::topic((int) $sub->id());
		self::handler('moderator')->setModerators((int) $forum->id(), [7]);
		self::make('suspension', ['uid' => 8, 'forum_id' => (int) $forum->id(), 'end_time' => time() + 3600]);

		self::assertTrue(self::handler('forum')->delete($forum, true));

		self::assertSame(1, self::rows('topic'));
		self::assertSame(0, self::rows('moderator'));
		self::assertSame(0, self::rows('suspension'));
		self::assertSame((int) $parent->id(), (int) self::reload('forum', (int) $sub->id())->getVar('parent_forum'));
	}

	public function testACategoryWithForumsCannotBeDeleted(): void
	{
		$category = self::category();
		self::forum((int) $category->id());

		self::assertFalse(self::handler('category')->delete($category, true));
		self::assertSame(1, self::rows('category'));
	}

	public function testAnEmptyCategoryCanBeDeleted(): void
	{
		self::assertTrue(self::handler('category')->delete(self::category(), true));
		self::assertSame(0, self::rows('category'));
	}

	private static function attachment(int $postId): string
	{
		$name = 'test-' . uniqid() . '.txt';
		file_put_contents(IMFORUM_UPLOAD_PATH . $name, 'attachment');
		self::make('attachment', ['post_id' => $postId, 'name_saved' => $name, 'name_display' => 'notes.txt']);
		self::assertFileExists(IMFORUM_UPLOAD_PATH . $name);
		return IMFORUM_UPLOAD_PATH . $name;
	}
}
