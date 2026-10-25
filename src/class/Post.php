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

	/** The text is withheld until the karma service has allowed this reader to see it */
	private bool $unlocked = false;

	private string $lock = '';

	/**
	 * Does the author ask for karma or a reply before others can read it?
	 */
	public function isProtected(): bool {
		return (int) $this->getVar('post_karma', 'e') > 0 || (int) $this->getVar('require_reply', 'e') === 1;
	}

	public function unlock(): void {
		$this->unlocked = true;
		$this->lock = '';
	}

	/**
	 * Remember why the reader may not see the text, for the notice that replaces it
	 */
	public function setLock(string $lock): void {
		$this->unlocked = false;
		$this->lock = $lock;
	}

	/**
	 * What the templates need
	 *
	 * Every text value is ready for output. A protected post carries no text
	 * until mod_imforum_KarmaService has unlocked it, so a template that
	 * forgets the check cannot leak the post.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray() {
		$locked = $this->isProtected() && !$this->unlocked;
		return [
			'post_id' => (int) $this->id(),
			'topic_id' => (int) $this->getVar('topic_id', 'e'),
			'forum_id' => (int) $this->getVar('forum_id', 'e'),
			'pid' => (int) $this->getVar('pid', 'e'),
			'uid' => (int) $this->getVar('uid', 'e'),
			'poster_name' => $this->getVar('poster_name'),
			'post_time' => (int) $this->getVar('post_time', 'e'),
			'subject' => $this->getVar('subject'),
			'icon' => $this->getVar('icon'),
			'attachsig' => (int) $this->getVar('attachsig', 'e'),
			'approved' => (int) $this->getVar('approved', 'e'),
			'locked' => $locked,
			'lock' => $locked ? $this->lock : '',
			'text' => $locked ? '' : $this->getFormattedText(),
			'edits' => $this->getEditHistory(),
		];
	}

	/**
	 * The text as shown: HTML through the purifier, otherwise the text filter with the post's own switches
	 */
	private function getFormattedText(): string {
		$text = (string) $this->getVar('post_text', 'n');
		if ((int) $this->getVar('dohtml', 'e') === 1) {
			return (string) icms_core_DataFilter::checkVar($text, 'html', 'output');
		}
		return (string) icms_core_DataFilter::filterTextareaDisplay(
			$text,
			(int) $this->getVar('dosmiley', 'e'),
			(int) $this->getVar('doxcode', 'e'),
			(int) $this->getVar('doimage', 'e'),
			(int) $this->getVar('dobr', 'e')
		);
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
