<?php
/**
 * A forum holds topics and can sit below another forum
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_Forum extends icms_ipf_seo_Object {

	public function __construct(mod_imforum_ForumHandler $handler) {
		parent::__construct($handler);

		$this->quickInitVar('forum_id', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('cat_id', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('parent_forum', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('forum_name', XOBJ_DTYPE_TXTBOX, true);
		$this->quickInitVar('forum_desc', XOBJ_DTYPE_TXTAREA);
		$this->quickInitVar('forum_active', XOBJ_DTYPE_INT, false, '', '', 1);
		$this->initCommonVar('weight');
		$this->quickInitVar('allow_html', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('allow_sig', XOBJ_DTYPE_INT, false, '', '', 1);
		$this->quickInitVar('allow_subject_prefix', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('hot_threshold', XOBJ_DTYPE_INT, false, '', '', 10);
		$this->quickInitVar('attach_maxkb', XOBJ_DTYPE_INT, false, '', '', 1000);
		$this->quickInitVar('attach_ext', XOBJ_DTYPE_TXTBOX, false, '', '', 'zip|gif|jpg|jpeg|png|pdf');

		// Kept up to date by the handlers, never edited by hand
		$this->quickInitVar('forum_topics', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('forum_posts', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->quickInitVar('forum_last_post_id', XOBJ_DTYPE_INT, false, '', '', 0);
		$this->hideFieldFromForm(['forum_topics', 'forum_posts', 'forum_last_post_id']);

		$this->initiateSEO();

		// Not a column: the admin form edits the moderator rows through it, and
		// ForumHandler::afterSave() writes them when it holds a list of user ids
		$this->initNonPersistableVar('moderators', XOBJ_DTYPE_OTHER, false, '', false, null, true);
		$this->setControl('moderators', ['name' => 'user_multi']);

		$this->setControl('cat_id', ['itemHandler' => 'category', 'method' => 'getCategorySelectList', 'module' => IMFORUM_DIRNAME]);
		$this->setControl('parent_forum', ['itemHandler' => 'forum', 'method' => 'getParentSelectList', 'module' => IMFORUM_DIRNAME]);
		$this->setControl('forum_desc', 'dhtmltextarea');
		foreach (['forum_active', 'allow_html', 'allow_sig', 'allow_subject_prefix'] as $key) {
			$this->setControl($key, ['name' => 'yesno']);
		}
	}

	/**
	 * Name linked to the admin edit form, for the admin forum list
	 */
	public function getAdminLink(): string {
		return '<a href="' . IMFORUM_URL . 'admin/forum.php?op=mod&amp;forum_id=' . (int) $this->id() . '">'
			. $this->getVar('forum_name') . '</a>';
	}

	/**
	 * Category title, already escaped by icms_ipf_Handler::getList()
	 */
	public function getCategoryTitle(): string {
		$titles = icms_getModuleHandler('category', IMFORUM_DIRNAME)->getCategorySelectList();
		return (string) ($titles[(int) $this->getVar('cat_id', 'e')] ?? '');
	}

	/**
	 * File extensions that may be attached in this forum, lower case
	 *
	 * @return list<string>
	 */
	public function getAllowedExtensions(): array {
		$extensions = array_map('trim', explode('|', strtolower((string) $this->getVar('attach_ext', 'e'))));
		return array_values(array_filter($extensions, static fn (string $ext): bool => $ext !== ''));
	}
}
