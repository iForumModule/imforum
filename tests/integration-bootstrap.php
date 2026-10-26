<?php
/**
 * Integration test bootstrap: the real ImpressCMS core against a real database
 *
 * Needs a core checkout (ICMS_CORE_PATH, the htdocs folder) and an empty MySQL
 * or MariaDB database (IMFORUM_TEST_DB_HOST, _NAME, _USER, _PASS). The tables
 * are built by the core's own updater from the object definitions, exactly as
 * on install, and dropped again before every run.
 *
 * IMFORUM_TEST_DB_ENGINE=MyISAM builds them with MyISAM, as on servers without
 * InnoDB.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$core = rtrim((string) getenv('ICMS_CORE_PATH'), '/');
if ($core === '' || !is_file($core . '/libraries/icms.php')) {
	fwrite(STDERR, "ICMS_CORE_PATH must point to the htdocs folder of an ImpressCMS checkout.\n");
	exit(1);
}

// A site root with the core linked in and the module copied in: PHP resolves
// symlinks in __DIR__, and the module takes its name from its directory.
$sandbox = sys_get_temp_dir() . '/imforum-integration-' . getmypid();
$root = $sandbox . '/htdocs';
mkdir($root . '/modules', 0755, true);
mkdir($root . '/uploads', 0755, true);
foreach (scandir($core) ?: [] as $entry) {
	if (!in_array($entry, ['.', '..', 'modules', 'uploads'], true)) {
		symlink("$core/$entry", "$root/$entry");
	}
}
foreach (scandir($core . '/modules') ?: [] as $entry) {
	if (!in_array($entry, ['.', '..', 'imforum'], true)) {
		symlink("$core/modules/$entry", "$root/modules/$entry");
	}
}
$copy = static function (string $from, string $to) use (&$copy): void {
	@mkdir($to, 0755, true);
	foreach (scandir($from) ?: [] as $entry) {
		if ($entry !== '.' && $entry !== '..') {
			is_dir("$from/$entry") ? $copy("$from/$entry", "$to/$entry") : copy("$from/$entry", "$to/$entry");
		}
	}
};
$copy(dirname(__DIR__) . '/src', $root . '/modules/imforum');

define('ICMS_ROOT_PATH', $root);
define('ICMS_TRUST_PATH', $sandbox . '/trust');
define('ICMS_URL', 'http://localhost');
// The core defines the group constants as strings
define('ICMS_GROUP_ADMIN', '1');
define('ICMS_GROUP_USERS', '2');
define('ICMS_GROUP_ANONYMOUS', '3');
mkdir(ICMS_TRUST_PATH . '/cache/htmlpurifier', 0755, true);
require_once ICMS_ROOT_PATH . '/include/constants.php';

define('XOOPS_DB_TYPE', 'pdo.mysql');
define('XOOPS_DB_HOST', getenv('IMFORUM_TEST_DB_HOST') ?: '127.0.0.1');
define('XOOPS_DB_NAME', getenv('IMFORUM_TEST_DB_NAME') ?: 'icms_test');
define('XOOPS_DB_USER', getenv('IMFORUM_TEST_DB_USER') ?: 'icms');
define('XOOPS_DB_PASS', getenv('IMFORUM_TEST_DB_PASS') ?: 'icms');
define('XOOPS_DB_PREFIX', 'imft');
define('XOOPS_DB_CHARSET', 'utf8mb4');
define('XOOPS_DB_PCONNECT', 0);
if (getenv('IMFORUM_TEST_DB_PORT')) {
	define('ICMS_DB_PORT', getenv('IMFORUM_TEST_DB_PORT'));
}

require_once ICMS_ROOT_PATH . '/libraries/icms.php';
require_once ICMS_ROOT_PATH . '/libraries/icms/Autoloader.php';
icms_Autoloader::setup();
require_once ICMS_ROOT_PATH . '/include/functions.php';

class_exists('icms_config_item_Handler'); // defines the ICMS_CONF_* constants
require_once __DIR__ . '/Integration/CoreConfig.php';
icms::$config = new \Imforum\Tests\Integration\CoreConfig();
icms::$preload = icms_preload_Handler::getInstance();
icms::$logger = icms_core_Logger::instance();
icms::$xoopsDB = icms_db_Factory::instance();

foreach (['global', 'core', 'databaseupdater'] as $file) {
	icms_loadLanguageFile('core', $file);
}
icms_loadLanguageFile('system', 'common');

require_once __DIR__ . '/Integration/Database.php';
\Imforum\Tests\Integration\Database::install();

register_shutdown_function(static function () use ($sandbox): void {
	$remove = static function (string $path) use (&$remove): void {
		if (is_link($path) || is_file($path)) {
			unlink($path);
		} elseif (is_dir($path)) {
			foreach (scandir($path) ?: [] as $entry) {
				if ($entry !== '.' && $entry !== '..') {
					$remove($path . '/' . $entry);
				}
			}
			rmdir($path);
		}
	};
	$remove($sandbox);
});
