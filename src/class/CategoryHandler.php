<?php
/**
 * Handler for categories
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_CategoryHandler extends icms_ipf_Handler {

	public function __construct(icms_db_legacy_Database $db) {
		parent::__construct($db, 'category', 'cat_id', 'cat_title', 'cat_description', IMFORUM_DIRNAME);
		$this->addPermission('category_access', _CO_IMFORUM_PERM_CATEGORY_ACCESS, _CO_IMFORUM_PERM_CATEGORY_ACCESS_DSC);
	}

	/**
	 * Options for a category select box, ordered as on the index page
	 *
	 * @return array<int, string>
	 */
	public function getCategorySelectList(): array {
		$criteria = new icms_db_criteria_Compo();
		$criteria->setSort('weight');
		$criteria->setOrder('ASC');
		return $this->getList($criteria);
	}

	/**
	 * Refuse to delete a category that still holds forums
	 *
	 * Deleting forums takes their topics and posts with them, so that must be
	 * an explicit choice per forum rather than a side effect.
	 */
	protected function beforeDelete(mod_imforum_Category $category): bool {
		$forums = icms_getModuleHandler('forum', IMFORUM_DIRNAME)
			->getCount(new icms_db_criteria_Item('cat_id', $category->id()));
		if ($forums > 0) {
			$category->setErrors(_CO_IMFORUM_CATEGORY_NOT_EMPTY);
			return false;
		}
		return true;
	}
}
