<?php

declare(strict_types=1);

namespace Imforum\Tests\Integration;

use icms_ipf_Handler;
use icms_ipf_Object;
use PHPUnit\Framework\TestCase;

/**
 * Starts every test with empty imforum tables and offers small factories
 */
abstract class IntegrationTestCase extends TestCase
{
	protected function setUp(): void
	{
		Database::reset();
	}

	protected static function handler(string $item): icms_ipf_Handler
	{
		return icms_getModuleHandler($item, 'imforum');
	}

	/**
	 * Create and store an object, failing the test when the handler refuses
	 *
	 * @param array<string, mixed> $values
	 */
	protected static function make(string $item, array $values): icms_ipf_Object
	{
		$handler = self::handler($item);
		$object = $handler->create();
		foreach ($values as $key => $value) {
			$object->setVar($key, $value);
		}
		self::assertTrue($handler->insert($object, true), $item . ': ' . implode(', ', $object->getErrors()));
		return $object;
	}

	protected static function category(string $title = 'General'): icms_ipf_Object
	{
		return self::make('category', ['cat_title' => $title]);
	}

	protected static function forum(int $catId, string $name = 'Forum', int $parent = 0): icms_ipf_Object
	{
		return self::make('forum', ['cat_id' => $catId, 'forum_name' => $name, 'parent_forum' => $parent]);
	}

	/**
	 * A topic with its first post, as phase 4 will create them
	 */
	protected static function topic(int $forumId, string $title = 'Hello', int $uid = 1): icms_ipf_Object
	{
		$topic = self::make('topic', ['forum_id' => $forumId, 'topic_title' => $title, 'topic_poster' => $uid]);
		self::post((int) $topic->id(), $title, $uid);
		return self::reload('topic', (int) $topic->id());
	}

	protected static function post(int $topicId, string $subject = 'Re: Hello', int $uid = 1, int $pid = 0, int $approved = 1): icms_ipf_Object
	{
		return self::make('post', [
			'topic_id' => $topicId,
			'subject' => $subject,
			'post_text' => 'Text of ' . $subject,
			'uid' => $uid,
			'pid' => $pid,
			'approved' => $approved,
		]);
	}

	protected static function reload(string $item, int $id): icms_ipf_Object
	{
		$object = self::handler($item)->get($id);
		self::assertFalse($object->isNew(), "$item $id not found");
		return $object;
	}

	protected static function rows(string $item): int
	{
		return (int) self::handler($item)->getCount();
	}
}
