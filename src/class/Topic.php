<?php
/**
 * A topic: a thread of posts in one forum
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_Topic extends icms_ipf_seo_Object {

	public function __construct(mod_imforum_TopicHandler $handler) {
		parent::__construct($handler);

		$this->quickInitVar('topic_id', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('forum_id', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('topic_title', XOBJ_DTYPE_TXTBOX, true);
		$this->quickInitVar('topic_subject', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('topic_poster', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('poster_name', XOBJ_DTYPE_TXTBOX);
		$this->quickInitVar('topic_time', XOBJ_DTYPE_LTIME, false, '', '', 0);
		$this->quickInitVar('topic_views', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('topic_status', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('topic_sticky', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('topic_digest', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('digest_time', XOBJ_DTYPE_LTIME, false, '', '', 0);
		$this->quickInitVar('approved', XOBJ_DTYPE_INT, false, '', '', 1);

		// Kept up to date by the handlers, never edited by hand
		$this->quickInitVar('topic_replies', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('topic_first_post_id', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('topic_last_post_id', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->hideFieldFromForm(['topic_views', 'topic_replies', 'topic_first_post_id', 'topic_last_post_id', 'digest_time']);

		$this->initiateSEO();

		$this->setControl('forum_id', ['itemHandler' => 'forum', 'method' => 'getForumSelectList', 'module' => IMFORUM_DIRNAME]);
		$this->setControl('topic_poster', ['name' => 'user']);
		foreach (['topic_status', 'topic_sticky', 'topic_digest', 'approved'] as $key) {
			$this->setControl($key, ['name' => 'yesno']);
		}
	}

	public function isLocked(): bool {
		return (int) $this->getVar('topic_status', 'e') === 1;
	}
}
