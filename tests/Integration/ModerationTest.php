<?php

declare(strict_types=1);

namespace Imforum\Tests\Integration;

use mod_imforum_ModerationService as Moderation;
use mod_imforum_Notifier;
use mod_imforum_PermissionService as Permissions;
use mod_imforum_PostService as PostService;
use mod_imforum_Report;
use mod_imforum_Settings as Settings;

/**
 * Lock, pin, move, merge, split, approve, report and suspend, and the counters that must stay right
 */
final class ModerationTest extends IntegrationTestCase
{
	private const MEMBERS = [2];
	private const MODERATOR = 8;
	private const MEMBER = 5;

	private int $forumId;

	private int $otherForumId;

	protected function setUp(): void
	{
		parent::setUp();
		Settings::override(['flood_seconds' => 0, 'edit_timelimit' => 30]);
		$category = self::category();
		$this->forumId = (int) self::forum((int) $category->id(), 'One')->id();
		$this->otherForumId = (int) self::forum((int) $category->id(), 'Two')->id();
		self::grantCategory((int) $category->id(), [2, 3]);
		foreach ([$this->forumId, $this->otherForumId] as $forumId) {
			self::grant($forumId, ['forum_access', 'forum_view'], [2, 3]);
			self::grant($forumId, ['forum_post', 'forum_reply', 'forum_noapprove'], self::MEMBERS);
		}
		self::handler('moderator')->setModerators($this->forumId, [self::MODERATOR]);
	}

	protected function tearDown(): void
	{
		Settings::override(null);
	}

	private function moderator(): Moderation
	{
		return new Moderation(new Permissions(self::MEMBERS, self::MODERATOR), new ApprovalNotifier());
	}

	private function member(?ApprovalNotifier $notifier = null): Moderation
	{
		return new Moderation(new Permissions(self::MEMBERS, self::MEMBER), $notifier ?? new ApprovalNotifier());
	}

	private function webmaster(): Moderation
	{
		return new Moderation(new Permissions([1], 1, true), new ApprovalNotifier());
	}

	private function topicWithReplies(int $forumId, string $title, int $replies): \mod_imforum_Topic
	{
		$topic = self::topic($forumId, $title, self::MEMBER);
		for ($i = 1; $i <= $replies; $i++) {
			self::post((int) $topic->id(), "Re $i of $title", self::MEMBER, (int) $topic->getVar('topic_first_post_id', 'e'));
		}
		return self::reload('topic', (int) $topic->id());
	}

	public function testOnlyModeratorsOfTheForumLockPinAndFeature(): void
	{
		$topic = self::topic($this->forumId);

		self::assertFalse($this->member()->setLocked($topic, true)['ok']);
		self::assertFalse(self::reload('topic', (int) $topic->id())->isLocked());

		self::assertTrue($this->moderator()->setLocked($topic, true)['ok']);
		self::assertTrue(self::reload('topic', (int) $topic->id())->isLocked());
		self::assertTrue($this->moderator()->setLocked($topic, false)['ok']);
		self::assertFalse(self::reload('topic', (int) $topic->id())->isLocked());

		self::assertTrue($this->moderator()->setSticky($topic, true)['ok']);
		self::assertSame(1, (int) self::reload('topic', (int) $topic->id())->getVar('topic_sticky', 'e'));
		self::assertTrue($this->moderator()->setDigest($topic, true)['ok']);
		$featured = self::reload('topic', (int) $topic->id());
		self::assertSame(1, (int) $featured->getVar('topic_digest', 'e'));
		self::assertGreaterThan(0, (int) $featured->getVar('digest_time', 'e'));
		self::assertTrue($this->moderator()->setDigest($topic, false)['ok']);
		self::assertSame(0, (int) self::reload('topic', (int) $topic->id())->getVar('digest_time', 'e'));

		$elsewhere = self::topic($this->otherForumId);
		self::assertFalse($this->moderator()->setLocked($elsewhere, true)['ok'], 'moderates only forum one');
	}

	public function testMovingNeedsModerationOfBothForumsAndRecountsThem(): void
	{
		$topic = $this->topicWithReplies($this->forumId, 'Move me', 2);
		self::assertFalse($this->moderator()->move($topic, $this->otherForumId)['ok'], 'not a moderator of forum two');

		self::handler('moderator')->setModerators($this->otherForumId, [self::MODERATOR]);
		self::assertFalse($this->moderator()->move($topic, $this->forumId)['ok'], 'same forum');
		self::assertTrue($this->moderator()->move($topic, $this->otherForumId)['ok']);

		self::assertSame(0, (int) self::reload('forum', $this->forumId)->getVar('forum_posts', 'e'));
		$target = self::reload('forum', $this->otherForumId);
		self::assertSame(1, (int) $target->getVar('forum_topics', 'e'));
		self::assertSame(3, (int) $target->getVar('forum_posts', 'e'));
		foreach (self::handler('post')->getObjects(new \icms_db_criteria_Item('topic_id', (int) $topic->id())) as $post) {
			self::assertSame($this->otherForumId, (int) $post->getVar('forum_id', 'e'));
		}
	}

	public function testMergingKeepsTheCountersRight(): void
	{
		$target = $this->topicWithReplies($this->forumId, 'Target', 1);
		$source = $this->topicWithReplies($this->forumId, 'Source', 2);
		self::handler('topic')->incrementViews((int) $source->id());
		self::handler('topic')->incrementViews((int) $source->id());
		$source = self::reload('topic', (int) $source->id());
		$lastOfSource = (int) $source->getVar('topic_last_post_id', 'e');

		self::assertFalse($this->moderator()->merge($target, $target)['ok']);
		$outcome = $this->moderator()->merge($source, $target);
		self::assertTrue($outcome['ok'], implode(', ', $outcome['errors']));

		self::assertTrue(self::handler('topic')->get((int) $source->id())->isNew(), 'the source topic is gone');
		$merged = self::reload('topic', (int) $target->id());
		self::assertSame('Target', $merged->getVar('topic_title', 'n'));
		self::assertSame(4, (int) $merged->getVar('topic_replies', 'e'), 'five posts, the first is no reply');
		self::assertSame($lastOfSource, (int) $merged->getVar('topic_last_post_id', 'e'));
		self::assertSame(2, (int) $merged->getVar('topic_views', 'e'));
		$forum = self::reload('forum', $this->forumId);
		self::assertSame(1, (int) $forum->getVar('forum_topics', 'e'));
		self::assertSame(5, (int) $forum->getVar('forum_posts', 'e'));
		self::assertSame($lastOfSource, (int) $forum->getVar('forum_last_post_id', 'e'));
	}

	public function testMergingAcrossForumsNeedsBoth(): void
	{
		$target = self::topic($this->otherForumId, 'Elsewhere');
		$source = self::topic($this->forumId, 'Here');
		self::assertFalse($this->moderator()->merge($source, $target)['ok']);
		self::assertTrue($this->webmaster()->merge($source, $target)['ok']);
		self::assertSame(2, (int) self::reload('forum', $this->otherForumId)->getVar('forum_posts', 'e'));
		self::assertSame(0, (int) self::reload('forum', $this->forumId)->getVar('forum_posts', 'e'));
	}

	public function testSplittingMovesThePostAndTheLaterOnes(): void
	{
		$topic = $this->topicWithReplies($this->forumId, 'Long', 3);
		$posts = array_values(self::handler('post')->getObjects(new \icms_db_criteria_Item('topic_id', (int) $topic->id())));
		usort($posts, static fn ($a, $b): int => (int) $a->id() <=> (int) $b->id());
		$first = $posts[0];
		$second = $posts[1];
		$third = $posts[2];

		self::assertFalse($this->moderator()->split($first, 'New')['ok'], 'the first post stays');
		self::assertFalse($this->moderator()->split($second, '  ')['ok'], 'a title is needed');
		self::assertFalse($this->member()->split($second, 'New')['ok']);

		$outcome = $this->moderator()->split($second, 'Split off');
		self::assertTrue($outcome['ok'], implode(', ', $outcome['errors']));
		$new = $outcome['topic'];
		self::assertNotNull($new);

		$old = self::reload('topic', (int) $topic->id());
		self::assertSame(0, (int) $old->getVar('topic_replies', 'e'));
		self::assertSame((int) $first->id(), (int) $old->getVar('topic_last_post_id', 'e'));
		self::assertSame('Split off', $new->getVar('topic_title', 'n'));
		self::assertSame(2, (int) $new->getVar('topic_replies', 'e'));
		self::assertSame((int) $second->id(), (int) $new->getVar('topic_first_post_id', 'e'));
		self::assertSame((int) $posts[3]->id(), (int) $new->getVar('topic_last_post_id', 'e'));

		self::assertSame(0, (int) self::reload('post', (int) $second->id())->getVar('pid', 'e'));
		self::assertSame((int) $second->id(), (int) self::reload('post', (int) $third->id())->getVar('pid', 'e'), 'answers to a post left behind now answer the new first post');
		$forum = self::reload('forum', $this->forumId);
		self::assertSame(2, (int) $forum->getVar('forum_topics', 'e'));
		self::assertSame(4, (int) $forum->getVar('forum_posts', 'e'));
	}

	public function testApprovingShowsTheTopicAndTellsSubscribersOnce(): void
	{
		$notifier = new ApprovalNotifier();
		$moderation = new Moderation(new Permissions(self::MEMBERS, self::MODERATOR), $notifier);
		$guestPosting = new PostService(new Permissions([3], 0), new ApprovalNotifier());
		self::grant($this->forumId, ['forum_post'], [3]);
		$created = $guestPosting->createTopic(self::reload('forum', $this->forumId), ['subject' => 'Waiting', 'post_text' => 'Hi', 'poster_name' => 'Guest']);
		self::assertSame([], $created['errors']);
		self::assertTrue($created['pending']);
		self::assertSame(0, (int) self::reload('forum', $this->forumId)->getVar('forum_topics', 'e'));

		$post = self::reload('post', (int) $created['post']->id());
		self::assertFalse($this->member($notifier)->approve($post)['ok']);
		self::assertSame([], $notifier->calls);

		self::assertTrue($moderation->approve($post)['ok']);
		self::assertSame([[true, (int) $post->id()]], $notifier->calls, 'a new topic is announced as one');
		self::assertTrue($moderation->approve($post)['ok'], 'approving twice changes nothing');
		self::assertCount(1, $notifier->calls);

		$topic = self::reload('topic', (int) $created['topic']->id());
		self::assertSame(1, (int) $topic->getVar('approved', 'e'));
		$forum = self::reload('forum', $this->forumId);
		self::assertSame(1, (int) $forum->getVar('forum_topics', 'e'));
		self::assertSame(1, (int) $forum->getVar('forum_posts', 'e'));
		self::assertSame(1, self::handler('post')->countApproved((int) $topic->id()));
	}

	public function testApprovingAReplyCountsItAndKeepsTheLastPostRight(): void
	{
		$topic = self::topic($this->forumId);
		$waiting = self::post((int) $topic->id(), 'Waiting reply', self::MEMBER, 0, 0);
		self::assertSame(0, (int) self::reload('topic', (int) $topic->id())->getVar('topic_replies', 'e'));

		self::assertTrue($this->moderator()->approve($waiting)['ok']);
		$after = self::reload('topic', (int) $topic->id());
		self::assertSame(1, (int) $after->getVar('topic_replies', 'e'));
		self::assertSame((int) $waiting->id(), (int) $after->getVar('topic_last_post_id', 'e'));
	}

	public function testDeletingATopicRemovesPostsAndRecounts(): void
	{
		$topic = $this->topicWithReplies($this->forumId, 'Gone', 2);
		self::assertFalse($this->member()->deleteTopic($topic)['ok']);
		self::assertTrue($this->moderator()->deleteTopic($topic)['ok']);
		self::assertSame(0, self::rows('post'));
		self::assertSame(0, (int) self::reload('forum', $this->forumId)->getVar('forum_topics', 'e'));
	}

	public function testMembersReportAndModeratorsHandle(): void
	{
		$topic = self::topic($this->forumId);
		$post = self::reload('post', (int) $topic->getVar('topic_first_post_id', 'e'));

		$guest = new Moderation(new Permissions([3], 0), new ApprovalNotifier());
		self::assertFalse($guest->report($post, 'Spam')['ok'], 'guests cannot report');
		self::assertFalse($this->member()->report($post, '  ')['ok'], 'a reason is needed');
		self::assertTrue($this->member()->report($post, 'Spam')['ok']);
		self::assertFalse($this->member()->report($post, 'Spam again')['ok'], 'one open report per member');
		self::assertSame(1, self::handler('report')->countOpenInForum($this->forumId));
		self::assertSame(0, self::handler('report')->countOpenInForum($this->otherForumId));

		$report = array_values(self::handler('report')->getObjects())[0];
		self::assertFalse($this->member()->handleReport($report, 'Mine')['ok']);
		self::assertTrue($this->moderator()->handleReport($report, 'Removed the link')['ok']);
		$closed = self::reload('report', (int) $report->id());
		self::assertSame(mod_imforum_Report::RESULT_HANDLED, (int) $closed->getVar('report_result', 'e'));
		self::assertSame(0, self::handler('report')->countOpenInForum($this->forumId));
		self::assertTrue($this->member()->report($post, 'It came back')['ok'], 'a handled report no longer blocks');
	}

	public function testASuspendedMemberReadsButCannotPost(): void
	{
		$forum = self::reload('forum', $this->forumId);
		$topic = self::topic($this->forumId);
		$member = new Permissions(self::MEMBERS, 6);
		self::assertTrue($member->canReply($forum, $topic));

		self::assertFalse($this->member()->suspend($forum, 6, 3, 'Rude')['ok'], 'members cannot suspend');
		self::assertFalse($this->moderator()->suspend($forum, 6, 0, 'Rude')['ok']);
		self::assertFalse($this->moderator()->suspend($forum, self::MODERATOR, 3, 'Self')['ok']);
		$outcome = $this->moderator()->suspend($forum, 6, 3, 'Rude');
		self::assertTrue($outcome['ok'], implode(', ', $outcome['errors']));

		$suspended = new Permissions(self::MEMBERS, 6);
		self::assertTrue($suspended->canReadForum($forum), 'reading stays possible');
		self::assertFalse($suspended->canReply($forum, $topic));
		self::assertFalse($suspended->canStartTopic($forum));
		$refused = (new PostService($suspended, new ApprovalNotifier()))->reply($forum, $topic, ['subject' => 'Hi', 'post_text' => 'Text']);
		self::assertNotSame([], $refused['errors']);
		self::assertNull($refused['post']);
		self::assertFalse((new Permissions(self::MEMBERS, 7))->isSuspended($this->forumId), 'others are not affected');
	}

	public function testModeratorsCannotBeSuspended(): void
	{
		$forum = self::reload('forum', $this->forumId);
		self::handler('moderator')->setModerators($this->forumId, [self::MODERATOR, 9]);
		self::assertFalse($this->moderator()->suspend($forum, 9, 3, 'No')['ok']);
		self::assertSame(0, self::rows('suspension'));
	}

	public function testReportingNeedsReadAccess(): void
	{
		$hidden = (int) self::forum((int) self::category('Hidden')->id(), 'Hidden')->id();
		$topic = self::topic($hidden);
		$post = self::reload('post', (int) $topic->getVar('topic_first_post_id', 'e'));
		self::assertFalse($this->member()->report($post, 'Spam')['ok']);
	}
}

/**
 * Remembers who would have been told instead of sending mail
 */
final class ApprovalNotifier extends mod_imforum_Notifier
{
	/** @var list<array{0: bool, 1: int}> */
	public array $calls = [];

	public function newPost(\mod_imforum_Post $post, \mod_imforum_Topic $topic, \mod_imforum_Forum $forum, bool $newTopic): void
	{
		$this->calls[] = [$newTopic, (int) $post->id()];
	}
}
