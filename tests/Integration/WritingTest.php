<?php

declare(strict_types=1);

namespace Imforum\Tests\Integration;

use mod_imforum_Notifier;
use mod_imforum_PermissionService as Permissions;
use mod_imforum_PostService as Service;
use mod_imforum_Settings as Settings;
use mod_imforum_Uploader;

/**
 * Starting topics, replying, editing and deleting, with the rules around them
 */
final class WritingTest extends IntegrationTestCase
{
	private const GUESTS = [3];
	private const MEMBERS = [2];

	private int $forumId;

	protected function setUp(): void
	{
		parent::setUp();
		Settings::override(['flood_seconds' => 0, 'edit_timelimit' => 30, 'subject_prefix' => 'Question, Idea']);
		$category = self::category();
		$this->forumId = (int) self::forum((int) $category->id())->id();
		self::grantCategory((int) $category->id(), [2, 3]);
		self::grant($this->forumId, ['forum_access', 'forum_view'], [2, 3]);
		self::grant($this->forumId, ['forum_post', 'forum_reply', 'forum_edit', 'forum_delete', 'forum_noapprove'], self::MEMBERS);
	}

	protected function tearDown(): void
	{
		Settings::override(null);
	}

	private function forumObject(): \mod_imforum_Forum
	{
		return self::reload('forum', $this->forumId);
	}

	private function member(int $uid = 5): Permissions
	{
		return new Permissions(self::MEMBERS, $uid);
	}

	private function service(?Permissions $permissions = null, ?RecordingNotifier $notifier = null): Service
	{
		return new Service($permissions ?? $this->member(), $notifier ?? new RecordingNotifier());
	}

	/** @return array<string, mixed> */
	private static function input(string $subject = 'A subject', string $text = 'Some text'): array
	{
		return ['subject' => $subject, 'post_text' => $text, 'dosmiley' => 1, 'doxcode' => 1, 'dobr' => 1];
	}

	public function testPermissionsForWritingFollowTheGroups(): void
	{
		$forum = $this->forumObject();
		$topic = self::topic($this->forumId);

		self::assertTrue($this->member()->canStartTopic($forum));
		self::assertTrue($this->member()->canReply($forum, $topic));
		self::assertFalse((new Permissions(self::GUESTS))->canStartTopic($forum));
		self::assertFalse((new Permissions(self::GUESTS))->canReply($forum, $topic));
		self::assertFalse($this->member()->canAttach($forum), 'no forum_attach given');
		self::assertTrue($this->member()->skipsApproval($forum));

		self::grant($this->forumId, ['forum_post'], [3]);
		self::assertTrue((new Permissions(self::GUESTS))->canStartTopic($forum), 'guests can be given the right');
	}

	public function testLockedTopicsTakeRepliesFromModeratorsOnly(): void
	{
		$forum = $this->forumObject();
		$topic = self::topic($this->forumId);
		\Imforum\Tests\Integration\Database::exec('UPDATE ' . self::handler('topic')->table . ' SET topic_status = 1');
		$locked = self::reload('topic', (int) $topic->id());

		self::assertFalse($this->member()->canReply($forum, $locked));
		self::handler('moderator')->setModerators($this->forumId, [5]);
		self::assertTrue((new Permissions(self::MEMBERS, 5))->canReply($forum, $locked));
	}

	public function testSuspendedMembersCannotWrite(): void
	{
		$forum = $this->forumObject();
		$topic = self::topic($this->forumId);
		self::make('suspension', ['uid' => 5, 'forum_id' => 0, 'start_time' => time() - 10, 'end_time' => time() + 3600]);

		self::assertFalse($this->member(5)->canStartTopic($forum));
		self::assertFalse($this->member(5)->canReply($forum, $topic));
		self::assertTrue($this->member(6)->canReply($forum, $topic), 'other members are fine');
		self::assertTrue($this->member(5)->canReadForum($forum), 'a suspended member can still read');
	}

	public function testStartingATopicCreatesTopicAndFirstPost(): void
	{
		$notifier = new RecordingNotifier();
		$result = $this->service(null, $notifier)->createTopic($this->forumObject(), self::input('First topic', 'Hello world'));

		self::assertSame([], $result['errors']);
		self::assertFalse($result['pending']);
		$topic = self::reload('topic', (int) $result['topic']->id());
		$post = self::reload('post', (int) $result['post']->id());
		self::assertSame('First topic', $topic->getVar('topic_title', 'n'));
		self::assertSame(5, (int) $topic->getVar('topic_poster', 'e'));
		self::assertSame((int) $post->id(), (int) $topic->getVar('topic_first_post_id', 'e'));
		self::assertSame(0, (int) $topic->getVar('topic_replies', 'e'));
		self::assertSame('Hello world', $post->getVar('post_text', 'n'));
		self::assertSame(5, (int) $post->getVar('uid', 'e'));
		self::assertSame(1, (int) self::reload('forum', $this->forumId)->getVar('forum_topics', 'e'));
		self::assertSame([[true, (int) $post->id()]], $notifier->calls);
	}

	public function testReplyCountsAndAnswersAPost(): void
	{
		$topic = self::topic($this->forumId);
		$first = (int) $topic->getVar('topic_first_post_id', 'e');
		$notifier = new RecordingNotifier();
		$result = $this->service(null, $notifier)->reply($this->forumObject(), $topic, self::input('Re: Hello') + ['pid' => $first]);

		self::assertSame([], $result['errors']);
		self::assertSame($first, (int) $result['post']->getVar('pid', 'e'));
		self::assertSame(1, (int) self::reload('topic', (int) $topic->id())->getVar('topic_replies', 'e'));
		self::assertSame([[false, (int) $result['post']->id()]], $notifier->calls);

		// a parent from another topic is ignored, the reply goes under the first post
		$other = self::topic($this->forumId, 'Other');
		$stray = (int) $other->getVar('topic_first_post_id', 'e');
		$again = $this->service()->reply($this->forumObject(), self::reload('topic', (int) $topic->id()), self::input() + ['pid' => $stray]);
		self::assertSame($first, (int) $again['post']->getVar('pid', 'e'));
	}

	public function testPostsWaitForApprovalWithoutTheRight(): void
	{
		self::grant($this->forumId, [], self::MEMBERS);
		\Imforum\Tests\Integration\Database::exec('DELETE FROM ' . \icms::$xoopsDB->prefix('group_permission') . " WHERE gperm_name = 'forum_noapprove'");
		$notifier = new RecordingNotifier();
		$result = $this->service(null, $notifier)->createTopic($this->forumObject(), self::input('Needs approval'));

		self::assertSame([], $result['errors']);
		self::assertTrue($result['pending']);
		self::assertSame(0, (int) self::reload('topic', (int) $result['topic']->id())->getVar('approved', 'e'));
		self::assertSame(0, (int) self::reload('post', (int) $result['post']->id())->getVar('approved', 'e'));
		self::assertSame(0, (int) self::reload('forum', $this->forumId)->getVar('forum_topics', 'e'), 'not counted yet');
		self::assertSame([], $notifier->calls, 'nobody is told about a post nobody can read');
	}

	public function testWhatTheFormMayNotSetIsIgnored(): void
	{
		$input = self::input() + ['dohtml' => 1, 'attachsig' => 1, 'post_karma' => 999999, 'require_reply' => 1, 'uid' => 99, 'approved' => 0, 'forum_id' => 77, 'topic_id' => 77];
		$post = $this->service()->createTopic($this->forumObject(), $input)['post'];

		self::assertSame(0, (int) $post->getVar('dohtml', 'e'), 'the forum does not allow HTML');
		self::assertSame(1, (int) $post->getVar('attachsig', 'e'), 'signatures are allowed by default');
		self::assertSame(Service::MAX_KARMA, (int) $post->getVar('post_karma', 'e'));
		self::assertSame(5, (int) $post->getVar('uid', 'e'));
		self::assertSame(1, (int) $post->getVar('approved', 'e'));
		self::assertSame($this->forumId, (int) $post->getVar('forum_id', 'e'));
	}

	public function testTheTextIsStoredAsTypedAndMadeSafeWhenShown(): void
	{
		$text = "a < b </textarea><script>alert(1)</script> [b]bold[/b]";
		$post = $this->service()->createTopic($this->forumObject(), self::input('Raw', $text))['post'];

		$stored = self::reload('post', (int) $post->id());
		self::assertSame($text, $stored->getVar('post_text', 'n'), 'no filter at save: the migrated posts and the new ones look alike');
		$shown = $stored->toArray()['text'];
		self::assertStringNotContainsString('<script>', $shown);
		self::assertStringContainsString('<strong>bold</strong>', $shown, 'BBCode is converted when shown');
		self::assertStringContainsString('&lt;/textarea&gt;', $shown);
	}

	public function testHtmlAndSignatureFollowTheForum(): void
	{
		\Imforum\Tests\Integration\Database::exec('UPDATE ' . self::handler('forum')->table . ' SET allow_html = 1, allow_sig = 0');
		$post = $this->service()->createTopic($this->forumObject(), self::input() + ['dohtml' => 1, 'attachsig' => 1])['post'];

		self::assertSame(1, (int) $post->getVar('dohtml', 'e'));
		self::assertSame(0, (int) $post->getVar('attachsig', 'e'));
	}

	public function testGuestsPostWithANameAndNothingElse(): void
	{
		self::grant($this->forumId, ['forum_post'], [3]);
		$guest = new Permissions(self::GUESTS);
		$input = self::input() + ['poster_name' => '  <b>Visitor</b>  ', 'post_karma' => 5, 'require_reply' => 1, 'attachsig' => 1];
		Database::exec('DELETE FROM ' . \icms::$xoopsDB->prefix('group_permission') . " WHERE gperm_name = 'forum_noapprove'");
		$result = $this->service($guest)->createTopic($this->forumObject(), $input);

		self::assertSame([], $result['errors']);
		self::assertTrue($result['pending'], 'guests wait for approval');
		$post = self::reload('post', (int) $result['post']->id());
		self::assertSame('Visitor', $post->getVar('poster_name', 'n'));
		self::assertSame(0, (int) $post->getVar('uid', 'e'));
		self::assertSame(0, (int) $post->getVar('post_karma', 'e'));
		self::assertSame(0, (int) $post->getVar('require_reply', 'e'));
		self::assertSame(0, (int) $post->getVar('attachsig', 'e'));
	}

	public function testSubjectAndTextAreRequired(): void
	{
		$service = $this->service();
		$forum = $this->forumObject();

		self::assertNotSame([], $service->createTopic($forum, self::input('', 'text'))['errors']);
		self::assertNotSame([], $service->createTopic($forum, self::input('subject', "  \n "))['errors']);
		self::assertNotSame([], $service->createTopic($forum, self::input(str_repeat('x', Service::MAX_SUBJECT + 1)))['errors']);
		self::assertSame(0, self::rows('topic'), 'a refused post leaves nothing behind');
		self::assertSame([], $service->createTopic($forum, self::input("  A  \n  title ", 'text'))['errors']);
		self::assertSame('A title', $service->createTopic($forum, self::input("  A  \n  title ", 'text'))['topic']->getVar('topic_title', 'n'));
	}

	public function testPrefixNeedsAForumThatAllowsIt(): void
	{
		$forum = $this->forumObject();
		$without = $this->service()->createTopic($forum, self::input() + ['prefix' => 2])['topic'];
		self::assertSame(0, (int) $without->getVar('topic_subject', 'e'));

		Database::exec('UPDATE ' . self::handler('forum')->table . ' SET allow_subject_prefix = 1');
		$forum = $this->forumObject();
		$idea = $this->service()->createTopic($forum, self::input() + ['prefix' => 2])['topic'];
		$unknown = $this->service()->createTopic($forum, self::input() + ['prefix' => 9])['topic'];
		self::assertSame(2, (int) $idea->getVar('topic_subject', 'e'));
		self::assertSame(0, (int) $unknown->getVar('topic_subject', 'e'));
		self::assertSame([1 => 'Question', 2 => 'Idea'], Service::getPrefixes());
		self::assertSame('Idea', \mod_imforum_View::prefix(2));
		self::assertSame('', \mod_imforum_View::prefix(0));
	}

	public function testFloodProtectionWaits(): void
	{
		Settings::override(['flood_seconds' => 60, 'edit_timelimit' => 30]);
		$forum = $this->forumObject();
		$service = $this->service();

		self::assertSame([], $service->createTopic($forum, self::input())['errors']);
		self::assertNotSame([], $service->createTopic($forum, self::input())['errors'], 'too soon');
		self::assertSame([], $this->service($this->member(6))->createTopic($forum, self::input())['errors'], 'another member');

		self::handler('moderator')->setModerators($this->forumId, [5]);
		self::assertSame([], $this->service(new Permissions(self::MEMBERS, 5))->createTopic($forum, self::input())['errors'], 'moderators are exempt');
	}

	public function testMembersEditTheirOwnPostsForAWhile(): void
	{
		$forum = $this->forumObject();
		$topic = self::topic($this->forumId, 'Hello', 5);
		$post = self::reload('post', (int) $topic->getVar('topic_first_post_id', 'e'));
		$service = $this->service();

		$result = $service->edit($forum, $topic, $post, self::input('Changed title', 'Changed text') + ['prefix' => 0]);
		self::assertSame([], $result['errors']);
		$stored = self::reload('post', (int) $post->id());
		self::assertSame('Changed text', $stored->getVar('post_text', 'n'));
		self::assertSame([5], array_column($stored->getEditHistory(), 'uid'));
		self::assertSame('Changed title', self::reload('topic', (int) $topic->id())->getVar('topic_title', 'n'), 'the first post names the topic');

		// somebody else, and too late
		self::assertSame([_NOPERM], $this->service($this->member(6))->edit($forum, $topic, $stored, self::input())['errors']);
		Database::exec('UPDATE ' . self::handler('post')->table . ' SET post_time = ' . (time() - 3600));
		self::assertSame([_NOPERM], $service->edit($forum, $topic, self::reload('post', (int) $post->id()), self::input())['errors']);

		// moderators and unlimited time
		self::handler('moderator')->setModerators($this->forumId, [7]);
		$moderator = $this->service(new Permissions(self::MEMBERS, 7));
		self::assertSame([], $moderator->edit($forum, $topic, self::reload('post', (int) $post->id()), self::input('By moderator'))['errors']);
		self::assertSame([5, 7], array_column(self::reload('post', (int) $post->id())->getEditHistory(), 'uid'));
		Settings::override(['flood_seconds' => 0, 'edit_timelimit' => 0]);
		self::assertSame([], $service->edit($forum, $topic, self::reload('post', (int) $post->id()), self::input('Much later'))['errors']);
	}

	public function testEditingKeepsApprovalAndOwner(): void
	{
		$forum = $this->forumObject();
		$topic = self::topic($this->forumId, 'Hello', 5);
		$post = self::reload('post', (int) $topic->getVar('topic_first_post_id', 'e'));
		$this->service()->edit($forum, $topic, $post, self::input() + ['uid' => 9, 'approved' => 0, 'topic_id' => 99]);

		$stored = self::reload('post', (int) $post->id());
		self::assertSame(5, (int) $stored->getVar('uid', 'e'));
		self::assertSame(1, (int) $stored->getVar('approved', 'e'));
		self::assertSame((int) $topic->id(), (int) $stored->getVar('topic_id', 'e'));
	}

	public function testMembersDeleteOnlyTheirLastPostOfATopic(): void
	{
		$forum = $this->forumObject();
		$topic = self::topic($this->forumId, 'Hello', 5);
		$reply = self::post((int) $topic->id(), 'Re', 5);
		$topic = self::reload('topic', (int) $topic->id());
		$first = self::reload('post', (int) $topic->getVar('topic_first_post_id', 'e'));
		$service = $this->service();

		self::assertSame([_NOPERM], $service->delete($forum, $topic, $first)['errors'], 'a reply would lose what it answered');
		self::assertSame([_NOPERM], $this->service($this->member(6))->delete($forum, $topic, $reply)['errors'], 'not theirs');

		$gone = $service->delete($forum, $topic, $reply);
		self::assertTrue($gone['ok']);
		self::assertFalse($gone['topic_gone']);
		self::assertSame(0, (int) self::reload('topic', (int) $topic->id())->getVar('topic_replies', 'e'));

		$topic = self::reload('topic', (int) $topic->id());
		$last = $service->delete($forum, $topic, self::reload('post', (int) $first->id()));
		self::assertTrue($last['ok']);
		self::assertTrue($last['topic_gone'], 'the only post takes the topic with it');
		self::assertSame(0, self::rows('topic'));
	}

	public function testMembersWithoutTheDeleteRightKeepTheirPosts(): void
	{
		Database::exec('DELETE FROM ' . \icms::$xoopsDB->prefix('group_permission') . " WHERE gperm_name = 'forum_delete'");
		$topic = self::topic($this->forumId, 'Hello', 5);
		$post = self::reload('post', (int) $topic->getVar('topic_first_post_id', 'e'));

		self::assertSame([_NOPERM], $this->service()->delete($this->forumObject(), $topic, $post)['errors']);
		self::handler('moderator')->setModerators($this->forumId, [5]);
		self::assertTrue($this->service(new Permissions(self::MEMBERS, 5))->delete($this->forumObject(), $topic, $post)['ok']);
	}

	public function testAttachmentsAreRefusedByExtensionAndSize(): void
	{
		Database::exec('UPDATE ' . self::handler('forum')->table . " SET attach_ext = 'zip|png|php|html', attach_maxkb = 10");
		$uploader = new mod_imforum_Uploader($this->forumObject());

		self::assertNull($uploader->check('photo.PNG', 2048));
		self::assertNull($uploader->check('archive.zip', 10 * 1024));
		self::assertNotNull($uploader->check('archive.zip', 10 * 1024 + 1), 'too big');
		self::assertNotNull($uploader->check('empty.zip', 0), 'empty');
		self::assertNotNull($uploader->check('notes.txt', 100), 'not in the list');
		self::assertNotNull($uploader->check('noextension', 100));
		self::assertNotNull($uploader->check('shell.php', 100), 'never, even when the list names it');
		self::assertNotNull($uploader->check('page.html', 100), 'never');
		self::assertNotNull($uploader->check('shell.php.zip.php', 100));
		self::assertSame(['zip', 'png'], $uploader->getAcceptedExtensions());
		self::assertSame('zip', mod_imforum_Uploader::extensionOf('a.b.ZIP'));
		self::assertStringNotContainsString('<script>', (string) $uploader->check('<script>.txt', 5));
	}

	public function testAttachmentsAreFoundPerPost(): void
	{
		$topic = self::topic($this->forumId);
		$first = (int) $topic->getVar('topic_first_post_id', 'e');
		$second = (int) self::post((int) $topic->id())->id();
		foreach ([[$first, 'a.zip', 1], [$first, 'b.zip', 1], [$second, 'c.zip', 1], [$second, 'hidden.zip', 0]] as [$postId, $name, $online]) {
			self::make('attachment', ['post_id' => $postId, 'name_saved' => bin2hex(random_bytes(4)), 'name_display' => $name, 'online' => $online]);
		}
		$handler = self::handler('attachment');

		$byPost = $handler->getByPosts([$first, $second, 999]);
		self::assertSame(['a.zip', 'b.zip'], array_map(static fn ($a) => $a->getVar('name_display', 'n'), $byPost[$first]));
		self::assertSame(['c.zip'], array_map(static fn ($a) => $a->getVar('name_display', 'n'), $byPost[$second]));
		self::assertSame([], $handler->getByPosts([]));
		self::assertSame(2, $handler->countForPost($second));

		$id = (int) $byPost[$first][0]->id();
		$handler->incrementDownloads($id);
		$handler->incrementDownloads($id);
		self::assertSame(2, (int) self::reload('attachment', $id)->getVar('downloads', 'e'));
	}

	public function testRemovingAnAttachmentDeletesItsFile(): void
	{
		\imforum_create_upload_folder();
		$topic = self::topic($this->forumId, 'Hello', 5);
		$post = self::reload('post', (int) $topic->getVar('topic_first_post_id', 'e'));
		$saved = bin2hex(random_bytes(8));
		file_put_contents(IMFORUM_UPLOAD_PATH . $saved, 'x');
		$attachment = self::make('attachment', ['post_id' => (int) $post->id(), 'name_saved' => $saved, 'name_display' => 'x.zip']);
		$stranger = self::make('attachment', ['post_id' => 9999, 'name_saved' => 'other', 'name_display' => 'other.zip']);

		$this->service()->handleAttachments($this->forumObject(), $post, [(int) $attachment->id(), (int) $stranger->id()]);

		self::assertFileDoesNotExist(IMFORUM_UPLOAD_PATH . $saved);
		self::assertSame(1, self::rows('attachment'), 'an attachment of another post stays');
		self::assertFileExists(IMFORUM_UPLOAD_PATH . '.htaccess');
	}

	public function testOnlyReadersOfAForumAreNotified(): void
	{
		$forum = $this->forumObject();
		$groups = [10 => [2], 11 => [3], 12 => [4], 13 => [1, 2]];
		$readers = (new mod_imforum_Notifier())->filterReaders([10, 11, 12, 13, 10], $forum, static fn (int $uid): array => $groups[$uid]);
		self::assertSame([10, 11, 13], $readers, 'group 4 has no access, the webmaster always has, nobody twice');

		self::assertSame([], (new mod_imforum_Notifier())->filterReaders([], $forum, static fn (int $uid): array => []));
	}
}

/**
 * Remembers who would have been told instead of sending mail
 */
final class RecordingNotifier extends mod_imforum_Notifier
{
	/** @var list<array{0: bool, 1: int}> */
	public array $calls = [];

	public function newPost(\mod_imforum_Post $post, \mod_imforum_Topic $topic, \mod_imforum_Forum $forum, bool $newTopic): void
	{
		$this->calls[] = [$newTopic, (int) $post->id()];
	}
}
