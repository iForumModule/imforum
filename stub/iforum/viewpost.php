<?php
/**
 * Old iForum address: permanent redirect to the same page of imforum
 *
 * @license		GNU GPL v2, see LICENSE
 * @package		iforum
 */

include_once dirname(__DIR__, 2) . '/mainfile.php';
if (!is_dir(ICMS_ROOT_PATH . '/modules/imforum')) {
	header('Location: ' . ICMS_URL . '/', true, 302);
	exit;
}
include_once ICMS_ROOT_PATH . '/modules/imforum/include/common.php';
if (!class_exists('mod_imforum_LegacyUrl')) {
	include_once ICMS_ROOT_PATH . '/modules/imforum/class/LegacyUrl.php';
}
mod_imforum_LegacyUrl::redirect('viewpost');
