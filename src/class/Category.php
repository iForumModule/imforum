<?php
/**
 * A category groups forums on the index page
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_Category extends icms_ipf_seo_Object {

	public function __construct(mod_imforum_CategoryHandler $handler) {
		parent::__construct($handler);

		$this->quickInitVar('cat_id', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('cat_title', XOBJ_DTYPE_TXTBOX, true);
		$this->quickInitVar('cat_description', XOBJ_DTYPE_TXTAREA);
		$this->quickInitVar('cat_image', XOBJ_DTYPE_IMAGE);
		$this->quickInitVar('cat_url', XOBJ_DTYPE_TXTBOX);
		$this->initCommonVar('weight');
		$this->initiateSEO();

		$this->setControl('cat_description', 'dhtmltextarea');
		$this->setControl('cat_image', ['name' => 'image']);
	}

	/**
	 * Title linked to the admin edit form, for the admin category list
	 */
	public function getAdminLink(): string {
		return '<a href="' . IMFORUM_URL . 'admin/category.php?op=mod&amp;cat_id=' . (int) $this->id() . '">'
			. $this->getVar('cat_title') . '</a>';
	}
}
