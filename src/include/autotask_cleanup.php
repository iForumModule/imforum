<?php
/**
 * Autotask: clean up the visitors list, old read marks and ended suspensions
 *
 * The core includes this file from inside a task object; it only calls the cleanup.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once __DIR__ . '/common.php';

mod_imforum_Cleanup::run();
