<?php

declare(strict_types=1);

namespace Imforum\Tests\Integration;

final class ForumTreeTest extends IntegrationTestCase
{
	public function testSelectListShowsTheTree(): void
	{
		$catId = (int) self::category()->id();
		$a = self::forum($catId, 'A');
		$b = self::forum($catId, 'B', (int) $a->id());
		$c = self::forum($catId, 'C');

		self::assertSame(
			[(int) $a->id() => 'A', (int) $b->id() => '-- B', (int) $c->id() => 'C'],
			self::handler('forum')->getForumSelectList()
		);
	}

	public function testAForumCannotBecomeItsOwnAncestor(): void
	{
		$catId = (int) self::category()->id();
		$a = self::forum($catId, 'A');
		$b = self::forum($catId, 'B', (int) $a->id());

		$a->setVar('parent_forum', (int) $b->id());
		self::assertFalse(self::handler('forum')->insert($a, true));

		$a->setVar('parent_forum', (int) $a->id());
		self::assertFalse(self::handler('forum')->insert($a, true));

		self::assertSame(0, (int) self::reload('forum', (int) $a->id())->getVar('parent_forum'));
	}

	public function testAllowedExtensionsAreNormalised(): void
	{
		$forum = self::forum((int) self::category()->id());
		$forum->setVar('attach_ext', ' ZIP| png ||');

		self::assertSame(['zip', 'png'], $forum->getAllowedExtensions());
	}
}
