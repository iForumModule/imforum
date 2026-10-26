<?php

declare(strict_types=1);

namespace Imforum\Tests\Integration;

use mod_imforum_Cleanup as Cleanup;
use mod_imforum_DigestMailer;
use mod_imforum_Notifier;
use mod_imforum_ReadmarkHandler as Marks;
use mod_imforum_ReadTracker as Tracker;
use mod_imforum_Settings as Settings;

/**
 * The autotasks: cleanup of read marks, visitors and suspensions, and the digest mail
 */
final class BackgroundTasksTest extends IntegrationTestCase
{
	private const DAY = 86400;

	protected function setUp(): void
	{
		parent::setUp();
		Settings::override(['read_expire_days' => 90, 'online_minutes' => 15]);
	}

	protected function tearDown(): void
	{
		Settings::override(null);
	}

	/**
	 * A topic whose posts were written at the given moments
	 *
	 * @param list<int> $times
	 */
	private static function topicAt(int $forumId, string $title, array $times, int $uid = 5): \mod_imforum_Topic
	{
		$topic = self::make('topic', ['forum_id' => $forumId, 'topic_title' => $title, 'topic_poster' => $uid, 'topic_time' => $times[0]]);
		foreach ($times as $i => $time) {
			self::make('post', ['topic_id' => (int) $topic->id(), 'subject' => $title . ' ' . $i, 'post_text' => 'Text', 'uid' => $uid, 'post_time' => $time]);
		}
		return self::reload('topic', (int) $topic->id());
	}

	private function forumId(): int
	{
		return (int) self::forum((int) self::category()->id())->id();
	}

	public function testOldReadMarksGoButReadTopicsStayRead(): void
	{
		$now = time();
		$forumId = $this->forumId();
		$old = self::topicAt($forumId, 'Old', [$now - 200 * self::DAY, $now - 199 * self::DAY]);
		$partly = self::topicAt($forumId, 'Partly', [$now - 200 * self::DAY, $now - 5 * self::DAY]);
		$recent = self::topicAt($forumId, 'Recent', [$now - 3 * self::DAY]);
		$marks = self::handler('readmark');
		$marks->markRead(Marks::TYPE_TOPIC, 5, (int) $old->id(), (int) $old->getVar('topic_last_post_id', 'e'), $now - 198 * self::DAY);
		$marks->markRead(Marks::TYPE_TOPIC, 5, (int) $partly->id(), (int) $partly->getVar('topic_first_post_id', 'e'), $now - 190 * self::DAY);
		$marks->markRead(Marks::TYPE_TOPIC, 5, (int) $recent->id(), (int) $recent->getVar('topic_last_post_id', 'e'), $now - 2 * self::DAY);

		$result = Cleanup::run($now);
		self::assertSame(2, $result['read_marks']);

		$progress = [];
		foreach ([$old, $partly, $recent] as $topic) {
			$topic = self::reload('topic', (int) $topic->id());
			$progress[(int) $topic->id()] = ['forum_id' => $forumId, 'last_post_id' => (int) $topic->getVar('topic_last_post_id', 'e')];
		}
		$unread = (new Tracker(5))->getUnreadTopics($progress);
		self::assertArrayNotHasKey((int) $old->id(), $unread, 'a topic read long ago stays read without its mark');
		self::assertArrayHasKey((int) $partly->id(), $unread, 'a reply since the mark keeps the topic unread');
		self::assertArrayNotHasKey((int) $recent->id(), $unread, 'a recent mark stays');
		self::assertSame(1, self::rows('readmark') - 1, 'the recent topic mark stays next to the forum mark');
	}

	public function testMarksOfDeletedTopicsAndCoveredMarksAreRemoved(): void
	{
		$now = time();
		$forumId = $this->forumId();
		$topic = self::topicAt($forumId, 'Covered', [$now - 3 * self::DAY]);
		$marks = self::handler('readmark');
		$marks->markRead(Marks::TYPE_TOPIC, 5, (int) $topic->id(), (int) $topic->getVar('topic_last_post_id', 'e'), $now - 3 * self::DAY);
		$marks->markRead(Marks::TYPE_FORUM, 5, $forumId, (int) $topic->getVar('topic_last_post_id', 'e'), $now - 2 * self::DAY);
		$marks->markRead(Marks::TYPE_TOPIC, 5, 9999, 3, $now - self::DAY);

		self::assertSame(2, Cleanup::run($now)['read_marks']);
		self::assertSame(1, self::rows('readmark'), 'only the forum mark is left');
	}

	public function testReadMarksNeverExpireWithZeroDays(): void
	{
		Settings::override(['read_expire_days' => 0, 'online_minutes' => 15]);
		$now = time();
		$topic = self::topicAt($this->forumId(), 'Old', [$now - 400 * self::DAY]);
		self::handler('readmark')->markRead(Marks::TYPE_TOPIC, 5, (int) $topic->id(), (int) $topic->getVar('topic_last_post_id', 'e'), $now - 399 * self::DAY);
		self::assertSame(0, Cleanup::run($now)['read_marks']);
		self::assertSame(1, self::rows('readmark'));
	}

	public function testVisitorsAndEndedSuspensionsAreCleanedUp(): void
	{
		$now = time();
		$online = self::handler('online');
		$online->track(5, 'five', '203.0.113.5', 0, 0, 15);
		self::make('online', ['uid' => 6, 'uname' => 'six', 'ip' => '203.0.113.6', 'updated' => $now - 3600]);
		self::make('suspension', ['uid' => 7, 'forum_id' => 0, 'start_time' => $now - 90 * self::DAY, 'end_time' => $now - 60 * self::DAY]);
		self::make('suspension', ['uid' => 8, 'forum_id' => 0, 'start_time' => $now - 40 * self::DAY, 'end_time' => $now - 10 * self::DAY]);
		self::make('suspension', ['uid' => 9, 'forum_id' => 0, 'start_time' => $now - self::DAY, 'end_time' => $now + 5 * self::DAY]);

		$result = Cleanup::run($now);
		self::assertSame(1, $result['suspensions'], 'only the one that ended more than 30 days ago');
		self::assertSame(2, self::rows('suspension'));
		self::assertSame(1, self::rows('online'), 'the visitor from an hour ago is gone');
	}

	// ---- the digest

	private function digestSetup(): array
	{
		$category = (int) self::category()->id();
		$open = (int) self::forum($category, 'Open')->id();
		$staff = (int) self::forum($category, 'Staff only')->id();
		self::grantCategory($category, [1, 2, 3, 4]);
		self::grant($open, ['forum_access', 'forum_view'], [2, 3]);
		self::grant($staff, ['forum_access', 'forum_view'], [4]);
		return [$open, $staff];
	}

	private function mailer(array $subscribers, array &$sent): mod_imforum_DigestMailer
	{
		$notifier = new class($subscribers) extends mod_imforum_Notifier {
			/** @param list<int> $subscribers */
			public function __construct(private array $subscribers)
			{
			}

			public function getGlobalSubscribers(string $event): array
			{
				return $event === 'digest' ? $this->subscribers : [];
			}
		};
		return new class($notifier, $sent) extends mod_imforum_DigestMailer {
			/** @param array<int, array{uids: list<int>, text: string}> $sent */
			public function __construct(mod_imforum_Notifier $notifier, private array &$sent)
			{
				parent::__construct($notifier);
			}

			protected function send(string $text, array $uids, int $since, int $until): void
			{
				$this->sent[] = ['uids' => $uids, 'text' => $text];
			}
		};
	}

	public function testEachRecipientGetsOnlyTheForumsTheyMayRead(): void
	{
		[$open, $staff] = $this->digestSetup();
		$now = time();
		self::topicAt($open, 'Public news', [$now - 3600, $now - 1800]);
		self::topicAt($staff, 'Secret plans', [$now - 3000]);
		$groups = [10 => [2], 11 => [4, 2], 12 => [1], 13 => [3]];

		$sent = [];
		$mailer = $this->mailer([10, 11, 12], $sent);
		$result = $mailer->run($now, static fn (int $uid): array => $groups[$uid]);

		self::assertSame(2, $result['topics']);
		self::assertSame(3, $result['recipients']);
		$byUid = [];
		foreach ($sent as $mail) {
			foreach ($mail['uids'] as $uid) {
				$byUid[$uid] = $mail['text'];
			}
		}
		self::assertStringContainsString('Public news', $byUid[10]);
		self::assertStringContainsString('new topic, 2 new posts', $byUid[10]);
		self::assertStringNotContainsString('Secret plans', $byUid[10], 'a member does not hear about forums they cannot read');
		self::assertStringContainsString('Secret plans', $byUid[11]);
		self::assertStringContainsString('Secret plans', $byUid[12]);
		self::assertStringContainsString('topic.php?topic=', $byUid[10]);
		self::assertStringNotContainsString('Text', $byUid[10], 'the text of a post is never in the mail');

		$digests = self::handler('digest')->getObjects();
		self::assertCount(1, $digests);
		self::assertStringContainsString('Secret plans', (string) reset($digests)->getVar('digest_content', 'n'), 'the stored digest is complete');
	}

	public function testTheDigestCoversTheTimeSinceTheLastOneOnly(): void
	{
		[$open] = $this->digestSetup();
		$now = time();
		self::topicAt($open, 'Yesterday', [$now - 2 * self::DAY]);
		self::topicAt($open, 'Today', [$now - 600]);
		$waiting = self::topicAt($open, 'Waiting', [$now - 500]);
		foreach (['topic' => (int) $waiting->id(), 'post' => (int) $waiting->getVar('topic_first_post_id', 'e')] as $item => $id) {
			$object = self::reload($item, $id);
			$object->setVar('approved', 0);
			self::assertTrue(self::handler($item)->insert($object, true));
		}

		$sent = [];
		$mailer = $this->mailer([10], $sent);
		$first = $mailer->run($now, static fn (): array => [2]);
		self::assertSame(1, $first['topics'], 'a first digest covers one day, without posts that wait for approval');
		self::assertStringContainsString('Today', $sent[0]['text']);
		self::assertStringNotContainsString('Yesterday', $sent[0]['text']);
		self::assertStringNotContainsString('Waiting', $sent[0]['text']);

		$again = $mailer->run($now + 1, static fn (): array => [2]);
		self::assertSame(0, $again['topics'], 'nothing new since the last digest');
		self::assertCount(1, $sent);
		self::assertSame(1, self::rows('digest'));

		self::topicAt($open, 'Later', [$now + 100]);
		$next = $mailer->run($now + 200, static fn (): array => [2]);
		self::assertSame(1, $next['topics']);
		self::assertStringContainsString('Later', $sent[1]['text']);
		self::assertStringNotContainsString('Today', $sent[1]['text']);
	}

	public function testNothingIsSentToMembersWithoutAnythingToRead(): void
	{
		[, $staff] = $this->digestSetup();
		$now = time();
		self::topicAt($staff, 'Secret plans', [$now - 100]);

		$sent = [];
		$result = $this->mailer([10], $sent)->run($now, static fn (): array => [2]);
		self::assertSame(1, $result['topics']);
		self::assertSame(0, $result['recipients']);
		self::assertSame([], $sent);
		self::assertSame(1, self::rows('digest'), 'the digest is kept for the administrators');
	}

	public function testALongDigestIsCutOff(): void
	{
		[$open] = $this->digestSetup();
		$now = time();
		for ($i = 0; $i < mod_imforum_DigestMailer::MAX_TOPICS + 3; $i++) {
			self::topicAt($open, "Topic $i", [$now - 100 - $i]);
		}
		$sent = [];
		$this->mailer([10], $sent)->run($now, static fn (): array => [2]);
		self::assertSame(mod_imforum_DigestMailer::MAX_TOPICS, substr_count($sent[0]['text'], 'topic.php?topic='));
		self::assertStringContainsString('And 3 more topics:', $sent[0]['text']);
	}

	public function testTheAutotaskFilesTheModuleDeclaresExist(): void
	{
		$version = (string) file_get_contents(dirname(__DIR__, 2) . '/src/icms_version.php');
		self::assertSame(2, preg_match_all("/'code' => '([^']+)'/", $version, $matches));
		self::assertCount(2, $matches[1]);
		foreach ($matches[1] as $file) {
			self::assertFileExists(dirname(__DIR__, 2) . '/src/' . $file);
		}
		self::assertStringContainsString("'name' => 'digest', 'category' => 'global'", $version);
		self::assertFileExists(dirname(__DIR__, 2) . '/src/language/english/mail_template/global_digest.tpl');
		self::assertFileExists(dirname(__DIR__, 2) . '/src/language/nederlands/mail_template/global_digest.tpl');
	}
}
