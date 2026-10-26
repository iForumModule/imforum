<?php

declare(strict_types=1);

namespace Imforum\Tests\Integration;

use mod_imforum_Db as Db;

/**
 * The tables of an old iForum installation, as the importer finds them
 *
 * Built from the bb_* schema of iForum (CBB 3.x) without the FULLTEXT keys,
 * which InnoDB on MySQL 5.5 does not have.
 */
final class OldIforum
{
	public const MODULE = 'iforum';

	private const TABLES = [
		'categories' => "cat_id smallint(3) unsigned NOT NULL auto_increment, cat_image varchar(50) NOT NULL default '', cat_title varchar(100) NOT NULL default '', cat_description text NOT NULL, cat_order smallint(3) unsigned NOT NULL default '0', cat_url varchar(255) NOT NULL default '', PRIMARY KEY (cat_id)",
		'forums' => "forum_id smallint(4) unsigned NOT NULL auto_increment, forum_name varchar(150) NOT NULL default '', forum_desc text, parent_forum smallint(4) unsigned NOT NULL default '0', forum_moderator varchar(255) NOT NULL default '', forum_topics int(8) unsigned NOT NULL default '0', forum_posts int(10) unsigned NOT NULL default '0', forum_last_post_id int(10) unsigned NOT NULL default '0', cat_id smallint(3) unsigned NOT NULL default '0', forum_type tinyint(1) unsigned NOT NULL default '0', allow_html tinyint(1) unsigned NOT NULL default '1', allow_sig tinyint(1) unsigned NOT NULL default '1', allow_subject_prefix tinyint(1) unsigned NOT NULL default '0', hot_threshold tinyint(3) unsigned NOT NULL default '10', forum_order smallint(4) unsigned NOT NULL default '0', attach_maxkb smallint(3) unsigned NOT NULL default '1000', attach_ext varchar(255) NOT NULL default '', allow_polls tinyint(1) unsigned NOT NULL default '0', PRIMARY KEY (forum_id)",
		'topics' => "topic_id int(8) unsigned NOT NULL auto_increment, topic_title varchar(255) NOT NULL default '', topic_poster int(10) unsigned NOT NULL default '0', topic_time int(10) unsigned NOT NULL default '0', topic_views int(10) unsigned NOT NULL default '0', topic_replies mediumint(8) unsigned NOT NULL default '0', topic_last_post_id int(8) unsigned NOT NULL default '0', forum_id smallint(4) unsigned NOT NULL default '0', topic_status tinyint(1) unsigned NOT NULL default '0', topic_subject smallint(3) unsigned NOT NULL default '0', topic_sticky tinyint(1) unsigned NOT NULL default '0', topic_digest tinyint(1) unsigned NOT NULL default '0', digest_time int(10) unsigned NOT NULL default '0', approved tinyint(2) NOT NULL default '1', poster_name varchar(255) NOT NULL default '', rating double(6,4) NOT NULL default '0.0000', votes int(11) unsigned NOT NULL default '0', topic_haspoll tinyint(1) unsigned NOT NULL default '0', poll_id mediumint(8) unsigned NOT NULL default '0', topic_tags varchar(255) NOT NULL default '', PRIMARY KEY (topic_id)",
		'posts' => "post_id int(10) unsigned NOT NULL auto_increment, pid int(10) unsigned NOT NULL default '0', topic_id int(8) unsigned NOT NULL default '0', forum_id smallint(4) unsigned NOT NULL default '0', post_time int(10) unsigned NOT NULL default '0', uid int(10) unsigned NOT NULL default '0', poster_name varchar(255) NOT NULL default '', poster_ip int(11) NOT NULL default '0', subject varchar(255) NOT NULL default '', dohtml tinyint(1) unsigned NOT NULL default '0', dosmiley tinyint(1) unsigned NOT NULL default '1', doxcode tinyint(1) unsigned NOT NULL default '1', dobr tinyint(1) unsigned NOT NULL default '1', doimage tinyint(1) unsigned NOT NULL default '1', icon varchar(25) NOT NULL default '', attachsig tinyint(1) unsigned NOT NULL default '0', approved smallint(2) NOT NULL default '1', post_karma int(10) unsigned NOT NULL default '0', attachment text, require_reply tinyint(1) unsigned NOT NULL default '0', PRIMARY KEY (post_id), KEY topic_id (topic_id)",
		'posts_text' => 'post_id int(10) unsigned NOT NULL default 0, post_text text, post_edit text, PRIMARY KEY (post_id)',
		'attachments' => "attach_id int(8) unsigned NOT NULL auto_increment, post_id int(10) unsigned NOT NULL default '0', name_saved varchar(255) NOT NULL default '', name_disp varchar(255) NOT NULL default '', mimetype varchar(255) NOT NULL default '', online tinyint(1) unsigned NOT NULL default '1', attach_time int(10) unsigned NOT NULL default '0', download int(10) unsigned NOT NULL default '0', PRIMARY KEY (attach_id)",
		'report' => "report_id int(8) unsigned NOT NULL auto_increment, post_id int(10) unsigned NOT NULL default '0', reporter_uid int(10) unsigned NOT NULL default '0', reporter_ip int(11) NOT NULL default '0', report_time int(10) unsigned NOT NULL default '0', report_text varchar(255) NOT NULL default '', report_result tinyint(1) unsigned NOT NULL default '0', report_memo varchar(255) NOT NULL default '', PRIMARY KEY (report_id)",
		'moderates' => "mod_id int(10) unsigned NOT NULL auto_increment, mod_start int(10) unsigned NOT NULL default '0', mod_end int(10) unsigned NOT NULL default '0', mod_desc varchar(255) NOT NULL default '', uid int(10) unsigned NOT NULL default '0', ip varchar(32) NOT NULL default '', forum_id smallint(4) unsigned NOT NULL default '0', PRIMARY KEY (mod_id)",
		'reads_forum' => "read_id int(10) unsigned NOT NULL auto_increment, uid int(10) unsigned NOT NULL default '0', read_time int(10) unsigned NOT NULL default '0', read_item smallint(4) unsigned NOT NULL default '0', post_id int(10) unsigned NOT NULL default '0', PRIMARY KEY (read_id)",
		'reads_topic' => "read_id int(10) unsigned NOT NULL auto_increment, uid int(10) unsigned NOT NULL default '0', read_time int(10) unsigned NOT NULL default '0', read_item int(8) unsigned NOT NULL default '0', post_id int(10) unsigned NOT NULL default '0', PRIMARY KEY (read_id)",
		'digest' => 'digest_id int(8) unsigned NOT NULL auto_increment, digest_time int(10) unsigned NOT NULL default 0, digest_content text, PRIMARY KEY (digest_id)',
		'archive' => 'topic_id int(8) unsigned NOT NULL default 0, post_id int(10) unsigned NOT NULL default 0, post_text text NOT NULL',
	];

	/**
	 * @param string $charset the character set of the text columns: old sites often still have latin1
	 */
	public static function create(string $charset = 'utf8mb4'): void
	{
		foreach (self::TABLES as $table => $columns) {
			Database::exec('CREATE TABLE `' . self::name($table) . "` ($columns) DEFAULT CHARSET=$charset");
		}
	}

	public static function name(string $table): string
	{
		return XOOPS_DB_PREFIX . '_bb_' . $table;
	}

	public static function drop(string $table): void
	{
		Database::exec('DROP TABLE `' . self::name($table) . '`');
	}

	/**
	 * Register the old module the way the core does, and return its id
	 */
	public static function register(): int
	{
		Database::exec(sprintf(
			"INSERT INTO %s (name, version, isactive, dirname, hasmain, hasadmin, dbversion, modname, ipf) VALUES ('iForum', '2.4', 1, '%s', 1, 1, 0, '%s', 0)",
			XOOPS_DB_PREFIX . '_modules',
			self::MODULE,
			self::MODULE
		));
		return Db::value('SELECT mid FROM ' . XOOPS_DB_PREFIX . '_modules WHERE dirname = ?', [self::MODULE]);
	}

	/**
	 * Insert rows into a bb_* table
	 *
	 * @param array<string, int|string|null> $row
	 */
	public static function insert(string $table, array $row): void
	{
		Db::execute(
			'INSERT INTO ' . self::name($table) . ' (' . implode(', ', array_keys($row)) . ') VALUES (' . Db::marks($row) . ')',
			array_values($row)
		);
	}

	/**
	 * A small installation that has a bit of everything
	 *
	 * @return array{mid: int, uploads: string, now: int}
	 */
	public static function fill(string $uploads): array
	{
		$now = time();
		$mid = self::register();
		$edits = base64_encode(serialize([['edit_user' => 'Ann', 'edit_time' => $now - 3600], ['edit_user' => 'Bert', 'edit_time' => $now - 60]]));
		$attachments = base64_encode(serialize([
			'1700000000' => ['name_saved' => 'a1b2c3.jpg', 'name_display' => 'holiday.jpg', 'mimetype' => 'image/jpeg', 'num_download' => 4],
			'1700000001' => ['name_saved' => 'gone.zip', 'name_display' => 'old.zip', 'mimetype' => 'application/zip', 'num_download' => 0],
			'1700000002' => ['name_saved' => '../../mainfile.php', 'name_display' => 'evil.php', 'mimetype' => 'text/plain', 'num_download' => 0],
		]));

		foreach ([[3, 'General', 'Chat here', 2], [7, 'Support', '', 1]] as [$id, $title, $description, $order]) {
			self::insert('categories', ['cat_id' => $id, 'cat_title' => $title, 'cat_description' => $description, 'cat_order' => $order]);
		}
		foreach ([
			[10, 'Lounge', 'Welcome', 0, serialize([5, 6, 6, 0]), 3, 0, 2, 'zip|jpg', 4, 8],
			[11, 'Archive room', '', 10, '', 3, 1, 0, '', 1, 9],
			[12, 'Help', 'Ask', 0, serialize([7]), 7, 0, 1, 'zip', 2, 5],
		] as [$id, $name, $description, $parent, $moderators, $cat, $type, $html, $ext, $order, $threshold]) {
			self::insert('forums', [
				'forum_id' => $id, 'forum_name' => $name, 'forum_desc' => $description, 'parent_forum' => $parent, 'forum_moderator' => $moderators, 'cat_id' => $cat,
				'forum_type' => $type, 'allow_html' => $html, 'attach_ext' => $ext, 'forum_order' => $order, 'hot_threshold' => $threshold,
				'forum_posts' => 99, 'forum_topics' => 99,
			]);
		}
		foreach ([
			[100, 'First topic', 5, $now - 5000, 40, 2, 1002, 10, 1, 1, 1, 1, 1, 'Ann'],
			[101, 'Second topic', 6, $now - 4000, 3, 0, 1003, 10, 0, 0, 0, 0, 1, ''],
			[102, 'Waiting topic', 0, $now - 3000, 0, 0, 1004, 12, 0, 0, 0, 0, 0, 'Guest'],
		] as [$id, $title, $poster, $time, $views, $replies, $last, $forum, $status, $subject, $sticky, $digest, $approved, $name]) {
			self::insert('topics', [
				'topic_id' => $id, 'topic_title' => $title, 'topic_poster' => $poster, 'topic_time' => $time, 'topic_views' => $views, 'topic_replies' => $replies,
				'topic_last_post_id' => $last, 'forum_id' => $forum, 'topic_status' => $status, 'topic_subject' => $subject, 'topic_sticky' => $sticky,
				'topic_digest' => $digest, 'digest_time' => $digest ? $now - 100 : 0, 'approved' => $approved, 'poster_name' => $name, 'rating' => 3.5, 'votes' => 2,
			]);
		}
		foreach ([
			// post, pid, topic, forum, time, uid, name, ip, subject, html, approved, attachment, text, edit
			[1000, 0, 100, 10, $now - 5000, 5, '', -1062731519, 'First topic', 1, 1, '', 'Hello [b]world[/b] <i>caf&eacute;</i>', null],
			[1001, 1000, 100, 10, $now - 4900, 0, 'Guest', 0, 'Re: First topic', 0, 1, '', "Line one\nLine two", $edits],
			[1002, 1001, 100, 10, $now - 4800, 6, '', 16909060, 'Re: First topic', 0, 1, $attachments, 'With a file', null],
			[1003, 0, 101, 10, $now - 4000, 6, '', 0, 'Second topic', 0, 1, '', 'Alone', null],
			[1004, 0, 102, 12, $now - 3000, 0, 'Guest', 0, 'Waiting topic', 0, 0, '', 'Needs approval', null],
		] as [$id, $pid, $topic, $forum, $time, $uid, $name, $ip, $subject, $html, $approved, $attachment, $text, $edit]) {
			self::insert('posts', [
				'post_id' => $id, 'pid' => $pid, 'topic_id' => $topic, 'forum_id' => $forum, 'post_time' => $time, 'uid' => $uid, 'poster_name' => $name, 'poster_ip' => $ip,
				'subject' => $subject, 'dohtml' => $html, 'approved' => $approved, 'attachment' => $attachment, 'post_karma' => $id === 1003 ? 50 : 0,
			]);
			self::insert('posts_text', ['post_id' => $id, 'post_text' => $text, 'post_edit' => $edit]);
		}
		// a post of a topic that is gone, and one without text row
		self::insert('posts', ['post_id' => 1005, 'topic_id' => 999, 'forum_id' => 10, 'post_time' => $now, 'subject' => 'Orphan']);

		self::insert('attachments', ['post_id' => 1003, 'name_saved' => 'reserved.txt', 'name_disp' => 'notes.txt', 'mimetype' => 'text/plain', 'attach_time' => $now, 'download' => 2]);
		self::insert('report', ['post_id' => 1001, 'reporter_uid' => 5, 'reporter_ip' => 16909060, 'report_time' => $now - 10, 'report_text' => 'Rude', 'report_result' => 1, 'report_memo' => 'Handled']);
		self::insert('moderates', ['mod_start' => $now - 100, 'mod_end' => $now + 86400, 'mod_desc' => 'Spam', 'uid' => 9, 'ip' => '', 'forum_id' => 10]);
		self::insert('moderates', ['mod_start' => $now - 900, 'mod_end' => $now - 800, 'mod_desc' => 'Over', 'uid' => 8, 'ip' => '', 'forum_id' => 10]);
		foreach ([[5, 10, 1002], [5, 10, 1003], [0, 10, 1003]] as [$uid, $item, $post]) {
			self::insert('reads_forum', ['uid' => $uid, 'read_time' => $now - $post, 'read_item' => $item, 'post_id' => $post]);
		}
		foreach ([[5, 100, 1001], [5, 100, 1002], [6, 101, 1003]] as [$uid, $item, $post]) {
			self::insert('reads_topic', ['uid' => $uid, 'read_time' => $now - $post, 'read_item' => $item, 'post_id' => $post]);
		}
		self::insert('digest', ['digest_time' => $now - 86400, 'digest_content' => 'Yesterday']);
		self::insert('archive', ['topic_id' => 55, 'post_id' => 555, 'post_text' => 'Archived text']);

		// preferences, permissions and subscriptions live in core tables
		$config = XOOPS_DB_PREFIX . '_config';
		foreach ([
			[$mid, 'dir_attachments', 'uploads/' . basename($uploads)], [$mid, 'topics_per_page', '25'], [$mid, 'rss_maxitems', '15'], [$mid, 'view_mode', '2'],
			[$mid, 'disc_show', '3'], [$mid, 'subject_prefix', 'NONE,Question,Tip'],
		] as [$modid, $name, $value]) {
			Db::execute("INSERT INTO $config (conf_modid, conf_name, conf_value) VALUES (?, ?, ?)", [$modid, $name, $value]);
		}
		$permission = XOOPS_DB_PREFIX . '_group_permission';
		foreach ([
			[1, 10, 'forum_access'], [2, 10, 'forum_access'], [3, 10, 'forum_access'], [2, 10, 'forum_view'], [2, 10, 'forum_post'],
			[2, 10, 'forum_addpoll'], [2, 99, 'forum_access'], [2, 3, 'category_access'], [2, 55, 'category_access'], [1, 12, 'forum_noapprove'],
		] as [$group, $item, $name]) {
			Db::execute("INSERT INTO $permission (gperm_groupid, gperm_itemid, gperm_modid, gperm_name) VALUES (?, ?, ?, ?)", [$group, $item, $mid, $name]);
		}
		$notification = XOOPS_DB_PREFIX . '_xoopsnotifications';
		foreach ([
			['thread', 100, 'new_post', 5], ['thread', 999, 'new_post', 5], ['forum', 10, 'new_thread', 5], ['global', 0, 'digest', 6],
			['global', 0, 'new_fullpost', 6], ['forum', 10, 'new_forum', 6],
		] as [$category, $item, $event, $uid]) {
			Db::execute("INSERT INTO $notification (not_modid, not_itemid, not_category, not_event, not_uid) VALUES (?, ?, ?, ?, ?)", [$mid, $item, $category, $event, $uid]);
		}

		// the files of iForum and its cached permission template
		@mkdir($uploads, 0755, true);
		file_put_contents($uploads . '/a1b2c3.jpg', 'abc');
		file_put_contents($uploads . '/reserved.txt', 'twelve bytes');
		file_put_contents(
			XOOPS_CACHE_PATH . '/' . self::MODULE . '_perm_template.php',
			"<?php\nreturn " . var_export([1 => ['forum_access' => 1, 'forum_view' => 1, 'forum_post' => 1], 2 => ['forum_access' => 1, 'forum_view' => 0, 'forum_addpoll' => 1], 0 => ['forum_access' => 1]], true) . ";\n"
		);
		return ['mid' => $mid, 'uploads' => $uploads, 'now' => $now];
	}
}
