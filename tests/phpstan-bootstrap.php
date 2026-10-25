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
define('ICMS_GROUP_ADMIN', 1);
define('ICMS_GROUP_USERS', 2);
define('ICMS_GROUP_ANONYMOUS', 3);

require_once dirname(__DIR__) . '/src/include/common.php';

foreach (['modinfo', 'main', 'admin', 'common'] as $file) {
	require_once dirname(__DIR__) . '/src/language/english/' . $file . '.php';
}
