<?php declare(strict_types=1);

namespace App\Service;

use App\Config\Loader\YamlConfigLoader;
use App\Entity\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileResource;

/**
 * Class ConfigurationService
 *
 * @package App\Service
 */
class ConfigurationService
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ConfigCacheFactoryInterface
     */
    protected $configCache;

    /**
     * @var bool
     */
    protected $debug;

    /**
     * @var string
     */
    protected $cacheDir;

    /**
     * @var string
     */
    protected $etcDir;

    /**
     * @var array
     */
    protected $dbConfigCache = [];

    /**
     * ConfigurationService constructor.
     *
     * @param EntityManagerInterface      $em
     * @param LoggerInterface             $logger
     * @param ConfigCacheFactoryInterface $configCache
     * @param bool                        $debug
     * @param string                      $cacheDir
     * @param string                      $etcDir
     */
    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger,
        ConfigCacheFactoryInterface $configCache,
        bool $debug,
        string $cacheDir,
        string $etcDir
    ) {
        $this->em          = $em;
        $this->logger      = $logger;
        $this->configCache = $configCache;
        $this->debug       = $debug;
        $this->cacheDir    = $cacheDir;
        $this->etcDir      = $etcDir;
    }

    /**
     * Get the value for the given configuration name
     *
     * @param string $name         The config name to get the value of
     * @param bool   $onlyIfPublic Only return the value if the config is
     *                             public
     *
     * @return mixed The configuration value
     * @throws Exception If the config can't be found and not default is
     *                   supplied
     */
    public function get(string $name, bool $onlyIfPublic = false)
    {
        $spec    = $this->getConfigSpecification()[$name] ?? null;
        $dbValue = $this->getDbValues()[$name] ?? null;

        if (isset($spec)) {
            if ($onlyIfPublic && !$spec['public']) {
                // If we require public values and the spec is not public,
                // set the value from the DB to null to not have a value
                $dbValue = null;
            }
        }

        if (isset($dbValue)) {
            return $dbValue;
        } elseif (!array_key_exists('default_value', $spec)) {
            throw new Exception("Configuration variable '$name' not found.");
        } else {
            return $spec['default_value'];
        }
    }

    /**
     * Get all the configuration values, indexed by name
     *
     * @param bool $onlyIfPublic
     *
     * @return array
     * @throws Exception
     */
    public function all(bool $onlyIfPublic = false): array
    {
        $specs  = $this->getConfigSpecification();
        $result = [];
        foreach ($specs as $name => $spec) {
            if (!$onlyIfPublic || $spec['public']) {
                $result[$name] = $spec['default_value'];
            }
        }

        foreach ($this->getDbValues() as $name => $value) {
            if (!isset($result[$name])) {
                $this->logger->warning(
                    'Configuration value %s not defined', [$name]
                );
                continue;
            }
            if (!$onlyIfPublic ||
                (isset($specs[$name]) && $specs[$name]['public'])) {
                $result[$name] = $value;
            }
        }

        return $result;
    }

    /**
     * Get all configuration specifications
     *
     * @throws Exception
     */
    public function getConfigSpecification(): array
    {
        // We use Symfony resource caching so we can load the config on every
        // request without having a performance impact.
        // See https://symfony.com/doc/4.3/components/config/caching.html for
        // more information.
        $cacheFile = $this->cacheDir . '/djDbConfig.php';
        $this->configCache->cache($cacheFile,
            function (ConfigCacheInterface $cache) {
                $yamlDbConfigFile = $this->etcDir . '/db-config.yaml';
                $fileLocator      = new FileLocator($this->etcDir);
                $loader           = new YamlConfigLoader($fileLocator);
                $yamlConfig       = $loader->load($yamlDbConfigFile);

                // We first modify the data such that it contains the category as a field,
                //since requesting data is faster in that case
                $config = [];
                foreach ($yamlConfig as $category) {
                    foreach ($category['items'] as $item) {
                        $config[$item['name']] = $item + ['category' => $category['category']];
                    }
                }

                $code          = var_export($config, true);
                $specification = <<<EOF
<?php

return {$code};
EOF;

                $cache->write($specification,
                    [new FileResource($yamlDbConfigFile)]);
            });

        $specification = require $cacheFile;
        return $specification;
    }

    /**
     * Get the configuration values from the database
     *
     * @return array
     */
    protected function getDbValues(): array
    {
        if (empty($this->dbConfigCache)) {
            $configs = $this->em->getRepository(Configuration::class)->findAll();
            foreach ($configs as $config) {
                $this->dbConfigCache[$config->getName()] = $config->getValue();
            }
        }

        return $this->dbConfigCache;
    }
}
