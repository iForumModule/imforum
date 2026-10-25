<?php
/**
 * Test bootstrap: a minimal stand-in for the ImpressCMS runtime
 *
 * Unit tests load module files outside a running core. Only constants and
 * language strings are provided here; anything that needs the database belongs
 * in an integration test against a real installation.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

define('IMFORUM_TEST_SANDBOX', sys_get_temp_dir() . '/imforum-tests-' . getmypid());

define('ICMS_ROOT_PATH', IMFORUM_TEST_SANDBOX . '/htdocs');
define('ICMS_URL', 'http://localhost');
define('ICMS_UPLOAD_PATH', IMFORUM_TEST_SANDBOX . '/htdocs/uploads');
define('ICMS_UPLOAD_URL', 'http://localhost/uploads');

// The module directory must be called imforum, as in a real installation. The
// sources are copied rather than symlinked, because PHP resolves symlinks in
// __DIR__ and the module derives its name from its directory.
define('IMFORUM_TEST_MODULE_PATH', ICMS_ROOT_PATH . '/modules/imforum');
@mkdir(ICMS_UPLOAD_PATH, 0755, true);
$copy = static function (string $from, string $to) use (&$copy): void {
	@mkdir($to, 0755, true);
	foreach (scandir($from) ?: [] as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}
		is_dir("$from/$entry") ? $copy("$from/$entry", "$to/$entry") : copy("$from/$entry", "$to/$entry");
	}
};
$copy(dirname(__DIR__) . '/src', IMFORUM_TEST_MODULE_PATH);

foreach (['modinfo', 'main', 'admin', 'common'] as $file) {
	require_once IMFORUM_TEST_MODULE_PATH . '/language/english/' . $file . '.php';
}

register_shutdown_function(static function (): void {
	$remove = static function (string $path) use (&$remove): void {
		if (is_file($path)) {
			unlink($path);
			return;
		}
		if (is_dir($path)) {
			foreach (scandir($path) ?: [] as $entry) {
				if ($entry !== '.' && $entry !== '..') {
					$remove($path . '/' . $entry);
				}
			}
			rmdir($path);
		}
	};
	$remove(IMFORUM_TEST_SANDBOX);
});
