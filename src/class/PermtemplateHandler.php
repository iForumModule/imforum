<?php
/**
 * Handler for the permission template
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_PermtemplateHandler extends icms_ipf_Handler {

	public function __construct(icms_db_legacy_Database $db) {
		parent::__construct($db, 'permtemplate', 'template_id', 'perm_name', '', IMFORUM_DIRNAME);
	}

	/**
	 * The template as permission name => group ids
	 *
	 * @return array<string, list<int>>
	 */
	public function getTemplate(): array {
		$template = array_fill_keys(mod_imforum_ForumHandler::PERMISSIONS, []);
		foreach ($this->getObjects() as $row) {
			$name = (string) $row->getVar('perm_name', 'e');
			if (isset($template[$name])) {
				$template[$name][] = (int) $row->getVar('groupid', 'e');
			}
		}
		return $template;
	}

	/**
	 * Replace the whole template; unknown permission names are ignored
	 *
	 * @param array<string, list<int>> $template
	 */
	public function saveTemplate(array $template): bool {
		$ok = (bool) $this->db->queryF("DELETE FROM {$this->table}");
		foreach (array_intersect_key($template, array_flip(mod_imforum_ForumHandler::PERMISSIONS)) as $name => $groups) {
			foreach (array_unique(array_map('intval', $groups)) as $groupId) {
				$row = $this->create();
				$row->setVar('perm_name', $name);
				$row->setVar('groupid', $groupId);
				$ok = $this->insert($row, true) && $ok;
			}
		}
		return $ok;
	}
}
