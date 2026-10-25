<?php
/**
 * A post in a topic
 *
 * Merges bb_posts and bb_posts_text. The text sits in the same row; with
 * 43,778 posts on the largest site a separate table buys nothing.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_Post extends icms_ipf_Object {

	public function __construct(mod_imforum_PostHandler $handler) {
		parent::__construct($handler);

		$this->quickInitVar('post_id', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('topic_id', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('forum_id', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('pid', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('uid', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('poster_name', XOBJ_DTYPE_TXTBOX);
		// IPv4 or IPv6 as text; iForum stored IPv4 as an integer
		$this->quickInitVar('poster_ip', XOBJ_DTYPE_TXTBOX);
		$this->quickInitVar('post_time', XOBJ_DTYPE_LTIME, false, '', '', 0);
		$this->quickInitVar('subject', XOBJ_DTYPE_TXTBOX, true);
		$this->quickInitVar('post_text', XOBJ_DTYPE_TXTAREA, true);
		// JSON list of edits; not a PHP array var, so nothing is ever unserialised
		$this->quickInitVar('post_edit', XOBJ_DTYPE_OTHER);
		$this->quickInitVar('icon', XOBJ_DTYPE_TXTBOX);
		$this->quickInitVar('attachsig', XOBJ_DTYPE_INT, false, '', '', 1);
		$this->quickInitVar('approved', XOBJ_DTYPE_INT, false, '', '', 1);
		$this->quickInitVar('post_karma', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('require_reply', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->initCommonVar('dohtml', false, 0);
		$this->initCommonVar('dosmiley');
		$this->initCommonVar('doxcode');
		$this->initCommonVar('doimage');
		$this->initCommonVar('dobr');

		$this->hideFieldFromForm(['topic_id', 'forum_id', 'pid', 'uid', 'poster_ip', 'post_time', 'post_edit', 'approved']);

		$this->setControl('post_text', ['name' => 'textarea', 'form_editor' => 'default']);
		foreach (['attachsig', 'require_reply'] as $key) {
			$this->setControl($key, ['name' => 'yesno']);
		}
	}

	/**
	 * Earlier edits, oldest first
	 *
	 * @return list<array{uid: int, name: string, time: int}>
	 */
	public function getEditHistory(): array {
		$edits = json_decode((string) $this->getVar('post_edit', 'n'), true);
		return is_array($edits) ? array_values($edits) : [];
	}

	public function addEdit(int $uid, string $name, ?int $time = null): void {
		$edits = $this->getEditHistory();
		$edits[] = ['uid' => $uid, 'name' => $name, 'time' => $time ?? time()];
		$this->setVar('post_edit', json_encode($edits));
	}
}
