<?php
declare(strict_types=1);

require dirname(__DIR__, 6) . '/app/bootstrap.php';
require __DIR__ . '/IsolatedEnvironment.php';

$database = getenv('F25_TEST_DATABASE');
if (!is_string($database) || preg_match('/^f25_validation_[a-z0-9_]+$/D', $database) !== 1) {
    throw new RuntimeException('Set F25_TEST_DATABASE to an isolated f25_validation_* database.');
}
$_SERVER['SCRIPT_FILENAME'] = BP . '/pub/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$bootstrap = \Nuvem\AtelieSales\Test\Integration\createIsolatedBootstrap($database);
$bootstrap->run($bootstrap->createApplication(\Magento\Framework\App\Http::class));
