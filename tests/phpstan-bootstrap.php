<?php
/**
 * Constants that the core defines at runtime, so PHPStan can resolve them
 */

declare(strict_types=1);

$core = getenv('ICMS_CORE_PATH') ?: '';

define('ICMS_ROOT_PATH', $core);
define('ICMS_URL', 'http://localhost');
define('ICMS_UPLOAD_PATH', $core . '/uploads');
define('ICMS_UPLOAD_URL', 'http://localhost/uploads');
define('XOOPS_DB_PREFIX', 'icms');
define('ICMS_GROUP_ADMIN', 1);
define('ICMS_GROUP_USERS', 2);
define('ICMS_GROUP_ANONYMOUS', 3);

// set by the core's include/common.php at runtime
define('_GLOBAL_LEFT', 'left');
// from the core's language/<language>/global.php
define('_NOPERM', 'Sorry, you don\'t have the permission to access this area.');
// the _CO_ICMS_* strings of the IPF forms and tables
require_once $core . '/modules/system/language/english/common.php';

require_once dirname(__DIR__) . '/src/include/common.php';

foreach (['modinfo', 'main', 'admin', 'common'] as $file) {
	require_once dirname(__DIR__) . '/src/language/english/' . $file . '.php';
}
