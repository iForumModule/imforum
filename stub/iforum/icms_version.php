<?php
/**
 * Stub for the old iForum module
 *
 * Installed in modules/iforum after the data has been imported into imforum. It
 * holds no data and no templates: its scripts only send the old addresses on to
 * imforum with a permanent redirect.
 *
 * @license		GNU GPL v2, see LICENSE
 * @package		iforum
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

$modversion = [
	'name' => 'iForum (redirects to imforum)',
	'version' => '1.0.0',
	'status' => 'Stub',
	'status_version' => '1.0.0 Stub',
	'description' => 'Sends the old iForum addresses on to imforum.',
	'author' => 'fiammybe',
	'license' => 'GNU GPL v2, see LICENSE',
	'official' => false,
	'dirname' => 'iforum',
	'hasMain' => 0,
	'hasAdmin' => 0,
	'hasSearch' => 0,
	'hasComments' => 0,
	'hasNotification' => 0,
	'sub' => [],
];
