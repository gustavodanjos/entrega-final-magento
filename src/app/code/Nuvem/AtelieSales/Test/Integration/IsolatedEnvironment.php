<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Integration;

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\ObjectManagerInterface;

class IsolatedScenarioEnvironmentFactory extends \Magento\Framework\App\EnvironmentFactory
{
    public function createEnvironment(): \Magento\Framework\App\EnvironmentInterface
    {
        return new \Magento\Framework\App\ObjectManager\Environment\Developer($this);
    }
}

function createIsolatedEnvironment(string $database): ObjectManagerInterface
{
    if (preg_match('/^f2[34]_validation_[a-z0-9_]+$/D', $database) !== 1) {
        throw new \InvalidArgumentException('Use an isolated f23_validation_* or f24_validation_* database.');
    }
    $config = require BP . '/app/etc/env.php';
    $config['db']['connection']['default']['dbname'] = $database;
    $testDirectory = '/tmp/' . $database;
    $config['cache'] = ['frontend' => [
        'default' => ['backend' => 'Magento\\Framework\\Cache\\Backend\\File', 'backend_options' => ['cache_dir' => $testDirectory . '/cache']],
        'page_cache' => ['backend' => 'Magento\\Framework\\Cache\\Backend\\File', 'backend_options' => ['cache_dir' => $testDirectory . '/page_cache']],
    ]];
    putenv('MAGENTO_DC__OVERRIDE=' . json_encode($config, JSON_THROW_ON_ERROR));
    $parameters = [Bootstrap::INIT_PARAM_FILESYSTEM_DIR_PATHS => [
        DirectoryList::VAR_DIR => ['path' => $testDirectory],
        DirectoryList::CACHE => ['path' => $testDirectory . '/cache'],
        DirectoryList::GENERATED_CODE => ['path' => $testDirectory . '/generated/code'],
        DirectoryList::GENERATED_METADATA => ['path' => $testDirectory . '/generated/metadata'],
    ]];
    // Compiled metadata uses BP directly, ignoring the isolated metadata directory.
    $factory = new class(
        Bootstrap::createFilesystemDirectoryList(BP, $parameters),
        new \Magento\Framework\Filesystem\DriverPool(),
        new \Magento\Framework\Config\File\ConfigFilePool()
    ) extends \Magento\Framework\App\ObjectManagerFactory {
        protected $envFactoryClassName = IsolatedScenarioEnvironmentFactory::class;
    };
    return Bootstrap::create(BP, $parameters, $factory)->getObjectManager();
}
