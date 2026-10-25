<?php

declare(strict_types=1);

namespace Imforum\Tests\Integration;

use mod_imforum_PermissionService as Permissions;

/**
 * The checks and queries behind the pages for visitors
 */
final class ReadingTest extends IntegrationTestCase
{
	private const GUESTS = [3];
	private const MEMBERS = [2];

	public function testNothingIsVisibleWithoutPermissions(): void
	{
		$forum = self::forum((int) self::category()->id());

		$permissions = new Permissions(self::GUESTS);
		self::assertSame([], $permissions->getVisibleForums());
		self::assertSame([], $permissions->getReadableForumIds());
		self::assertFalse($permissions->canReadForum(self::reload('forum', (int) $forum->id())));
	}

	public function testAForumNeedsCategoryAccessForumAccessAndView(): void
	{
		$category = self::category();
		$forum = self::forum((int) $category->id());
		$forumId = (int) $forum->id();

		self::grant($forumId, ['forum_access', 'forum_view'], self::GUESTS);
		self::assertSame([], (new Permissions(self::GUESTS))->getReadableForumIds(), 'the category is not open');

		self::grantCategory((int) $category->id(), self::GUESTS);
		self::assertSame([$forumId], (new Permissions(self::GUESTS))->getReadableForumIds());

		self::grant($forumId, ['forum_view'], []);
		$guests = new Permissions(self::GUESTS);
		self::assertCount(1, $guests->getVisibleForums(), 'listed without view');
		self::assertSame([], $guests->getReadableForumIds(), 'but not readable');
	}

	public function testPermissionsFollowTheGroupsOfTheVisitor(): void
	{
		$category = self::category();
		$forumId = (int) self::forum((int) $category->id())->id();
		self::grantCategory((int) $category->id(), self::MEMBERS);
		self::grant($forumId, ['forum_access', 'forum_view'], self::MEMBERS);

		self::assertSame([], (new Permissions(self::GUESTS))->getReadableForumIds());
		self::assertSame([$forumId], (new Permissions(self::MEMBERS, 5))->getReadableForumIds());
		self::assertSame([$forumId], (new Permissions([3, 2], 5))->getReadableForumIds());
	}

	public function testWebmastersSeeEverythingAndSwitchedOffForumsHideFromOthers(): void
	{
		$category = self::category();
		$off = self::make('forum', ['cat_id' => (int) $category->id(), 'forum_name' => 'Off', 'forum_active' => 0]);
		$offId = (int) $off->id();
		self::grantCategory((int) $category->id(), self::GUESTS);
		self::grant($offId, ['forum_access', 'forum_view'], self::GUESTS);

		self::assertSame([], (new Permissions(self::GUESTS))->getReadableForumIds());
		self::assertSame([$offId], (new Permissions([1], 1, true))->getReadableForumIds(), 'webmaster');

		self::handler('moderator')->setModerators($offId, [7]);
		self::assertSame([$offId], (new Permissions(self::GUESTS, 7))->getReadableForumIds(), 'its moderator');
		self::assertSame([], (new Permissions(self::GUESTS, 8))->getReadableForumIds());
	}

	public function testTheCurrentUserIsAWebmasterThroughTheCoreGroupConstants(): void
	{
		$forumId = (int) self::forum((int) self::category()->id())->id();
		$user = new \icms_member_user_Object();
		$user->assignVar('uid', 1);
		$user->setGroups([(int) ICMS_GROUP_ADMIN, (int) ICMS_GROUP_USERS]);
		\icms::$user = $user;
		try {
			$permissions = Permissions::forCurrentUser();
			self::assertTrue($permissions->isWebmaster());
			self::assertSame([$forumId], $permissions->getReadableForumIds());
		} finally {
			\icms::$user = null;
		}
		self::assertFalse(Permissions::forCurrentUser()->isWebmaster());
	}

	public function testTopicsListStickyFirstThenByLastPost(): void
	{
		$forumId = (int) self::forum((int) self::category()->id())->id();
		$first = self::topic($forumId, 'First');
		$sticky = self::topic($forumId, 'Sticky');
		Database::exec('UPDATE ' . self::handler('topic')->table . ' SET topic_sticky = 1 WHERE topic_id = ' . (int) $sticky->id());
		$last = self::topic($forumId, 'Last');
		self::post((int) $first->id());

		$titles = array_map(
			static fn ($topic): string => (string) $topic->getVar('topic_title', 'n'),
			array_values(self::handler('topic')->getForumTopics($forumId, 0, 10))
		);
		self::assertSame(['Sticky', 'First', 'Last'], $titles);
		self::assertSame(3, self::handler('topic')->countForumTopics($forumId));
		self::assertCount(1, self::handler('topic')->getForumTopics($forumId, 2, 10));
		self::assertNotSame(0, (int) $last->id());
	}

	public function testRecentTopicsFilterByForumAndKind(): void
	{
		$cat = (int) self::category()->id();
		$open = (int) self::forum($cat, 'Open')->id();
		$closed = (int) self::forum($cat, 'Closed')->id();
		$a = self::topic($open, 'A');
		self::topic($closed, 'Hidden');
		$b = self::topic($open, 'B');
		Database::exec('UPDATE ' . self::handler('topic')->table . ' SET topic_digest = 1 WHERE topic_id = ' . (int) $a->id());

		$handler = self::handler('topic');
		self::assertSame([(int) $b->id(), (int) $a->id()], $handler->findRecent([$open], 0, 10)['ids']);
		self::assertSame(2, $handler->findRecent([$open], 0, 10)['total']);
		self::assertSame([(int) $a->id()], $handler->findRecent([$open], 0, 10, 'digest')['ids']);
		self::assertSame(['ids' => [], 'total' => 0], $handler->findRecent([], 0, 10));
		self::assertSame(['ids' => [], 'total' => 0], $handler->findRecent([$open], 0, 10, 'unread', 0), 'guests have nothing unread');
		self::assertSame([(int) $b->id()], $handler->findRecent([$open], 0, 1)['ids']);
	}

	public function testReadTrackerFollowsTopicAndForumMarks(): void
	{
		$cat = (int) self::category()->id();
		$forumId = (int) self::forum($cat)->id();
		$one = self::topic($forumId, 'One');
		$two = self::topic($forumId, 'Two');
		$tracker = new \mod_imforum_ReadTracker(9);
		$state = static fn ($topic): array => [(int) $topic->id() => [
			'forum_id' => (int) $topic->getVar('forum_id', 'e'),
			'last_post_id' => (int) $topic->getVar('topic_last_post_id', 'e'),
		]];
		$both = $state($one) + $state($two);

		self::assertSame([(int) $one->id() => true, (int) $two->id() => true], $tracker->getUnreadTopics($both));
		self::assertSame([$forumId => 2], $tracker->countUnreadByForum([$forumId]));

		$tracker->markTopicRead((int) $one->id(), (int) $one->getVar('topic_last_post_id', 'e'));
		self::assertSame([(int) $two->id() => true], $tracker->getUnreadTopics($both));
		self::assertSame([$forumId => 1], $tracker->countUnreadByForum([$forumId]));
		self::assertSame([(int) $two->id()], self::handler('topic')->findRecent([$forumId], 0, 10, 'unread', 9)['ids']);

		// a mark never moves back
		$tracker->markTopicRead((int) $one->id(), 1);
		self::assertSame([(int) $two->id() => true], $tracker->getUnreadTopics($both));

		// a reply makes the topic unread again
		$reply = self::post((int) $one->id());
		$one = self::reload('topic', (int) $one->id());
		self::assertArrayHasKey((int) $one->id(), $tracker->getUnreadTopics($state($one)));
		self::assertNotSame(0, (int) $reply->id());

		// "mark all as read" covers the forum and removes the topic marks
		$forum = self::reload('forum', $forumId);
		$tracker->markForumRead($forumId, (int) $forum->getVar('forum_last_post_id', 'e'));
		self::assertSame([], $tracker->getUnreadTopics($state($one) + $state($two)));
		self::assertSame([], $tracker->countUnreadByForum([$forumId]));
		self::assertSame(1, self::rows('readmark'), 'only the forum mark is left');

		// other members and guests are unaffected
		self::assertSame([$forumId => 2], (new \mod_imforum_ReadTracker(10))->countUnreadByForum([$forumId]));
		self::assertSame([], (new \mod_imforum_ReadTracker(0))->getUnreadTopics($both));
	}

	public function testThreadOrderPutsRepliesUnderTheirParents(): void
	{
		$forumId = (int) self::forum((int) self::category()->id())->id();
		$topic = self::topic($forumId, 'Root');
		$topicId = (int) $topic->id();
		$root = (int) $topic->getVar('topic_first_post_id', 'e');
		$a = (int) self::post($topicId, 'a', 1, $root)->id();
		$b = (int) self::post($topicId, 'b', 1, $root)->id();
		$a1 = (int) self::post($topicId, 'a1', 1, $a)->id();
		$unapproved = (int) self::post($topicId, 'pending', 1, $b, 0)->id();
		$orphan = (int) self::post($topicId, 'orphan', 1, $unapproved)->id();

		$order = self::handler('post')->getThreadOrder($topicId);
		self::assertSame([$root, $a, $a1, $b, $orphan], array_column($order, 'post_id'));
		self::assertSame([0, 1, 2, 1, 0], array_column($order, 'depth'), 'a reply to an unapproved post becomes a first level post');
	}

	public function testThreadOrderSurvivesLoopsAndSelfReplies(): void
	{
		$forumId = (int) self::forum((int) self::category()->id())->id();
		$topic = self::topic($forumId);
		$topicId = (int) $topic->id();
		$one = (int) self::post($topicId, 'one')->id();
		$two = (int) self::post($topicId, 'two')->id();
		$posts = self::handler('post');
		Database::exec("UPDATE {$posts->table} SET pid = $two WHERE post_id = $one");
		Database::exec("UPDATE {$posts->table} SET pid = $one WHERE post_id = $two");

		$ids = array_column($posts->getThreadOrder($topicId), 'post_id');
		self::assertCount(3, $ids);
		self::assertEqualsCanonicalizing([(int) $topic->getVar('topic_first_post_id', 'e'), $one, $two], $ids);
	}

	public function testPostPagesAndPositions(): void
	{
		$forumId = (int) self::forum((int) self::category()->id())->id();
		$topic = self::topic($forumId);
		$topicId = (int) $topic->id();
		$ids = [(int) $topic->getVar('topic_first_post_id', 'e')];
		for ($i = 0; $i < 4; $i++) {
			$ids[] = (int) self::post($topicId, "reply $i")->id();
		}
		self::post($topicId, 'pending', 1, 0, 0);
		$posts = self::handler('post');

		self::assertSame(5, $posts->countApproved($topicId));
		self::assertSame(array_slice($ids, 0, 2), array_keys($posts->getPage($topicId, 0, 2)));
		self::assertSame(array_slice($ids, 4, 1), array_keys($posts->getPage($topicId, 4, 2)));
		self::assertSame(3, $posts->getPosition($topicId, $ids[3]));
		self::assertSame([$ids[2], $ids[0]], array_keys($posts->getByIds([$ids[2], 99999, $ids[0]])), 'in the order asked, unknown ids left out');
		self::assertSame(['subject' => 'reply 1'], ['subject' => $posts->getSummaries([$ids[2]])[$ids[2]]['subject']]);
	}

	public function testMembersAreCountedOnApprovedPostsOnly(): void
	{
		$forumId = (int) self::forum((int) self::category()->id())->id();
		$topic = self::topic($forumId, 'Hello', 5);
		self::post((int) $topic->id(), 'again', 5);
		self::post((int) $topic->id(), 'other', 6);
		self::post((int) $topic->id(), 'pending', 6, 0, 0);
		self::post((int) $topic->id(), 'guest', 0);
		$posts = self::handler('post');

		self::assertSame([5 => 2, 6 => 1], $posts->countByUsers([5, 6, 7, 0]));
		self::assertSame([['uid' => 5, 'posts' => 2], ['uid' => 6, 'posts' => 1]], $posts->getTopPosters([$forumId], 10));
		self::assertSame([], $posts->getTopPosters([], 10));
		self::assertTrue($posts->hasPosted((int) $topic->id(), 6));
		self::assertFalse($posts->hasPosted((int) $topic->id(), 7));
	}

	public function testKarmaAndRequiredRepliesHideTheText(): void
	{
		$forumId = (int) self::forum((int) self::category()->id())->id();
		$topic = self::topic($forumId, 'Hello', 5);
		$topicId = (int) $topic->id();
		$karmaPost = self::make('post', ['topic_id' => $topicId, 'subject' => 'k', 'post_text' => 'secret karma', 'uid' => 5, 'post_karma' => 3]);
		$replyPost = self::make('post', ['topic_id' => $topicId, 'subject' => 'r', 'post_text' => 'secret reply', 'uid' => 5, 'require_reply' => 1]);
		$plain = self::reload('post', (int) $topic->getVar('topic_first_post_id', 'e'));

		// without the service the text never leaves the post
		self::assertTrue($karmaPost->toArray()['locked']);
		self::assertSame('', $karmaPost->toArray()['text']);
		self::assertFalse($plain->toArray()['locked']);

		// a guest has neither karma nor replies
		$guest = new \mod_imforum_KarmaService(0);
		self::assertSame('karma', $guest->apply($karmaPost, false));
		self::assertSame('karma', $karmaPost->toArray()['lock']);
		self::assertSame('reply', $guest->apply($replyPost, false));
		self::assertSame('', $replyPost->toArray()['text']);

		// the author and moderators read everything
		$author = new \mod_imforum_KarmaService(5);
		self::assertSame('', $author->apply($karmaPost, false));
		self::assertStringContainsString('secret karma', $karmaPost->toArray()['text']);
		self::assertSame('', (new \mod_imforum_KarmaService(0))->apply($replyPost, true));

		// karma counts approved posts: member 6 has two, which is not enough for three
		self::post($topicId, 'one', 6);
		self::post($topicId, 'two', 6);
		$member = new \mod_imforum_KarmaService(6);
		self::assertSame(2, $member->getKarma());
		$locked = self::reload('post', (int) $karmaPost->id());
		self::assertSame('karma', $member->apply($locked, false));
		self::post($topicId, 'three', 6);
		$member = new \mod_imforum_KarmaService(6);
		$unlocked = self::reload('post', (int) $karmaPost->id());
		self::assertSame('', $member->apply($unlocked, false));
		// member 6 has replied in the topic, so the reply post opens too
		$replyLocked = self::reload('post', (int) $replyPost->id());
		self::assertSame('', $member->apply($replyLocked, false));
		self::assertStringContainsString('secret reply', $replyLocked->toArray()['text']);
	}

	public function testPostTextIsFormattedWithItsOwnSwitches(): void
	{
		$forumId = (int) self::forum((int) self::category()->id())->id();
		$topic = self::topic($forumId);
		$post = self::make('post', [
			'topic_id' => (int) $topic->id(), 'subject' => 's', 'post_text' => "<b>bold</b> [b]coded[/b]\nline", 'dohtml' => 0, 'doxcode' => 1, 'dobr' => 1,
		]);
		$text = $post->toArray()['text'];
		self::assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $text, 'html is escaped');
		self::assertStringContainsString('<strong>coded</strong>', $text, 'code is converted');
		self::assertStringContainsString('<br', $text);

		$plain = self::make('post', ['topic_id' => (int) $topic->id(), 'subject' => 's', 'post_text' => '[b]x[/b]', 'doxcode' => 0]);
		self::assertStringContainsString('[b]x[/b]', $plain->toArray()['text']);
	}

	public function testOnlineVisitorsAreTrackedOncePerVisitor(): void
	{
		$online = self::handler('online');
		self::assertTrue($online->track(5, 'Ann', '10.0.0.1', 1, 0));
		self::assertTrue($online->track(5, 'Ann', '10.0.0.1', 2, 9));
		self::assertTrue($online->track(0, '', '10.0.0.2', 1, 0));
		self::assertTrue($online->track(0, '', '10.0.0.2', 1, 0));
		self::assertTrue($online->track(0, '', '10.0.0.3', 2, 0));
		self::assertFalse($online->track(0, '', 'not an address', 1, 0), 'a guest without an address cannot be told apart');

		self::assertSame(3, self::rows('online'));
		self::assertSame(['members' => [['uid' => 5, 'uname' => 'Ann']], 'guests' => 2], $online->getSnapshot());
		self::assertSame(['members' => [], 'guests' => 1], $online->getSnapshot(1));
		self::assertSame(['members' => [['uid' => 5, 'uname' => 'Ann']], 'guests' => 0], $online->getSnapshot(0, 9));
		self::assertSame([5 => true], $online->getOnlineMembers([5, 6]));

		// a visitor who stopped long ago is gone
		Database::exec('UPDATE ' . $online->table . ' SET updated = ' . (time() - 3600) . ' WHERE uid = 5');
		self::assertSame(['members' => [], 'guests' => 2], $online->getSnapshot());
		self::assertSame([], $online->getOnlineMembers([5]));
		$online->track(0, '', '10.0.0.9', 0, 0);
		self::assertSame(3, self::rows('online'), 'the stale row was purged');
	}

	public function testArchivedTopicsAreListedPerReadableForum(): void
	{
		$cat = (int) self::category()->id();
		$open = (int) self::forum($cat, 'Open')->id();
		$closed = (int) self::forum($cat, 'Closed')->id();
		$a = self::topic($open, 'A');
		$b = self::topic($closed, 'B');
		foreach ([[$a, 100], [$a, 200], [$b, 300]] as [$topic, $time]) {
			self::make('archive', ['topic_id' => (int) $topic->id(), 'post_id' => (int) $topic->getVar('topic_first_post_id', 'e'), 'post_text' => 'old', 'archived_time' => $time]);
		}
		$archive = self::handler('archive');

		$page = $archive->getTopicsPage([$open], 0, 10);
		self::assertSame(1, $page['total']);
		self::assertSame([['topic_id' => (int) $a->id(), 'posts' => 2, 'archived_time' => 200]], $page['rows']);
		self::assertSame(2, $archive->getTopicsPage([$open, $closed], 0, 10)['total']);
		self::assertSame(['rows' => [], 'total' => 0], $archive->getTopicsPage([], 0, 10));
		self::assertCount(2, $archive->getByTopic((int) $a->id()));
	}

	public function testBusyTopicsNeedRecentActivityAndReplies(): void
	{
		$forumId = (int) self::forum((int) self::category()->id())->id();
		$quiet = self::topic($forumId, 'Quiet');
		$busy = self::topic($forumId, 'Busy');
		self::post((int) $busy->id());
		self::post((int) $busy->id());
		$old = self::topic($forumId, 'Old');
		self::post((int) $old->id());
		$posts = self::handler('post');
		Database::exec("UPDATE {$posts->table} SET post_time = 1000 WHERE topic_id = " . (int) $old->id());

		self::assertSame([(int) $busy->id()], self::handler('topic')->findHot([$forumId], time() - 86400, 10));
		self::assertNotSame(0, (int) $quiet->id());
	}

	public function testSearchFindsOnlyWhatTheVisitorMayRead(): void
	{
		include_once ICMS_ROOT_PATH . '/modules/imforum/include/search.inc.php';
		$cat = self::category();
		$open = self::forum((int) $cat->id(), 'Open');
		$closed = self::forum((int) $cat->id(), 'Closed');
		self::grantCategory((int) $cat->id(), self::GUESTS);
		self::grant((int) $open->id(), ['forum_access', 'forum_view'], self::GUESTS);

		$topic = self::topic((int) $open->id(), 'Needle in subject');
		self::make('post', ['topic_id' => (int) $topic->id(), 'subject' => 'plain', 'post_text' => 'haystack with a needle inside', 'uid' => 4]);
		self::make('post', ['topic_id' => (int) $topic->id(), 'subject' => 'locked', 'post_text' => 'secret needle', 'require_reply' => 1]);
		self::make('post', ['topic_id' => (int) $topic->id(), 'subject' => 'pending', 'post_text' => 'needle', 'approved' => 0]);
		self::topic((int) $closed->id(), 'Needle elsewhere');
		self::make('post', ['topic_id' => (int) $topic->id(), 'subject' => '100% sure', 'post_text' => 'x']);

		$titles = array_column(imforum_search(['needle'], 'AND', 20, 0, 0), 'title');
		self::assertEqualsCanonicalizing(['Needle in subject', 'plain'], $titles);
		self::assertSame(['locked'], array_column(imforum_search(['locked'], 'AND', 20, 0, 0), 'title'));
		self::assertSame([], array_column(imforum_search(['secret'], 'AND', 20, 0, 0), 'title'), 'the text of a protected post is not searched');
		self::assertSame(['plain'], array_column(imforum_search(['needle'], 'AND', 20, 0, 4), 'title'));
		self::assertSame(['100% sure'], array_column(imforum_search(['100%'], 'AND', 20, 0, 0), 'title'));
		self::assertSame([], imforum_search(["' OR 1=1 -- "], 'AND', 20, 0, 0));
		self::assertCount(1, imforum_search(['needle', 'haystack'], 'AND', 20, 0, 0));
		self::assertCount(2, imforum_search(['haystack', 'subject'], 'OR', 20, 0, 0));
		self::assertCount(1, imforum_search(['needle'], 'AND', 1, 1, 0), 'limit and offset');
	}
}
