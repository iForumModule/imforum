<?php
/**
 * Autotask: send the digest of new topics and posts
 *
 * The core includes this file from inside a task object; it only calls the mailer.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once __DIR__ . '/common.php';

(new mod_imforum_DigestMailer())->run();
