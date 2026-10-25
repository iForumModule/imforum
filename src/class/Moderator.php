<?php
/**
 * Links a user to a forum they moderate
 *
 * Replaces the serialised list in bb_forums.forum_moderator.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_Moderator extends icms_ipf_Object {

	public function __construct(mod_imforum_ModeratorHandler $handler) {
		parent::__construct($handler);

		$this->quickInitVar('moderator_id', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('forum_id', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('uid', XOBJ_DTYPE_INT, true);

		$this->setControl('forum_id', ['itemHandler' => 'forum', 'method' => 'getForumSelectList', 'module' => IMFORUM_DIRNAME]);
		$this->setControl('uid', ['name' => 'user']);
	}
}
