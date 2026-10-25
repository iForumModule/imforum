<?php
/**
 * Common start of every admin script
 *
 * cp_header.php loads the core in admin mode, which refuses users who may not
 * administer this module.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

include_once dirname(__DIR__, 3) . '/include/cp_header.php';
include_once dirname(__DIR__) . '/include/common.php';
include_once dirname(__DIR__) . '/include/admin.php';
