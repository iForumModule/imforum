<?php
/**
 * Forum index
 *
 * Placeholder until phase 3 lists the categories and forums.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

include_once dirname(__DIR__, 2) . '/mainfile.php';
include_once __DIR__ . '/include/common.php';

$xoopsOption['template_main'] = 'imforum_index.html';
include_once ICMS_ROOT_PATH . '/header.php';
/** header.php creates the theme template engine as a global */
global $icmsTpl;

$icmsTpl->assign('imforum_module_url', IMFORUM_URL);

include_once ICMS_ROOT_PATH . '/footer.php';
