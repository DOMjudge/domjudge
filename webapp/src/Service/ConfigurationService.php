<?php declare(strict_types=1);

namespace App\Service;

use App\Config\Loader\YamlConfigLoader;
use App\Entity\Configuration;
use App\Entity\Executable;
use App\Entity\Judging;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\HttpFoundation\Request;

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
        $spec = $this->getConfigSpecification()[$name] ?? null;

        if (!isset($spec) || ($onlyIfPublic && !$spec['public'])) {
            throw new Exception("Configuration variable '$name' not found.");
        }

        return $this->getDbValues()[$name] ?? $spec['default_value'];
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
            if ($spec['public'] || !$onlyIfPublic) {
                $result[$name] = $spec['default_value'];
            }
        }

        foreach ($this->getDbValues() as $name => $value) {
            // Don't potentially leak information to public logging:
            if (!isset($specs[$name]) && !$onlyIfPublic) {
                $this->logger->warning(
                    'Configuration value %s not defined', [$name]
                );
            }
            // $result[$name] exists iff it should be visible.
            if (isset($result[$name])) {
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
                // @codeCoverageIgnoreStart
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
                // @codeCoverageIgnoreEnd
            });

        $specification = require $cacheFile;
        return $specification;
    }

    /**
     * Save the changes from the given request
     *
     * @param array $dataToSet
     * @param EventLogService $eventLog
     * @param DOMJudgeService $dj
     *
     * @throws NonUniqueResultException
     */
    public function saveChanges(
        array $dataToSet,
        EventLogService $eventLog,
        DOMJudgeService $dj
    ) {
        $specs = $this->getConfigSpecification();
        foreach ($specs as &$spec) {
            $spec = $this->addOptions($spec);
        }
        unset($spec);

        /** @var Configuration[] $options */
        $options = $this->em->createQueryBuilder()
            ->from(Configuration::class, 'c',  'c.name')
            ->select('c')
            ->getQuery()
            ->getResult();

        $needsMerge = false;
        foreach ($specs as $specName => $spec) {
            $oldValue = $spec['default_value'];
            if (isset($options[$specName])) {
                $optionToSet = $options[$specName];
                $oldValue = $optionToSet->getValue();
                $optionIsNew = false;
            } else {
                $optionToSet = new Configuration();
                $optionToSet->setName($specName);
                $optionIsNew = true;
            }
            if (!array_key_exists($specName, $dataToSet)) {
                if ($spec['type'] == 'bool') {
                    // Special-case bool, since checkboxes don't return a
                    // value when unset.
                    $val = false;
                } elseif ($spec['type'] == 'array_val' && isset($spec['options'])) {
                    // Special-case array_val with options, since multiselects
                    // don't return a value when unset.
                    $val = [];
                } else {
                    continue;
                }
            } else {
                $val = $dataToSet[$specName];
            }
            if ($specName == 'verification_required' &&
                $oldValue && !$val ) {
                // If toggled off, we have to send events for all judgings
                // that are complete, but not verified yet. Scoreboard
                // cache refresh should take care of the rest. See #645.
                $this->logUnverifiedJudgings($eventLog);
                $needsMerge = true;
            }
            switch ( $spec['type'] ) {
                case 'bool':
                    $optionToSet->setValue((bool)$val);
                    break;

                case 'int':
                    $optionToSet->setValue((int)$val);
                    break;

                case 'string':
                    $optionToSet->setValue($val);
                    break;

                case 'array_val':
                    $result = array();
                    foreach ($val as $data) {
                        if (!empty($data)) {
                            $result[] = $data;
                        }
                    }
                    $optionToSet->setValue($result);
                    break;

                case 'array_keyval':
                    $result = array();
                    foreach ($val as $key => $data) {
                        if (!empty($data)) {
                            $result[$key] = $data;
                        }
                    }
                    $optionToSet->setValue($result);
                    break;

                default:
                    $this->logger->warn(
                        "configuration option '%s' has unknown type '%s'",
                        [ $specName, $spec['type'] ]
                    );
            }
            if ($optionToSet->getValue() != $oldValue) {
                $valJson = $dj->jsonEncode($optionToSet->getValue());
                $dj->auditlog('configuration', $specName, 'updated', $valJson);
                if ($optionIsNew) {
                    $this->em->persist($optionToSet);
                }
            }
        }

        if ( $needsMerge ) {
            foreach ($options as $option) $this->em->merge($option);
        }

        $this->em->flush();

        $this->dbConfigCache = [];
    }

    /**
     * @throws NonUniqueResultException
     */
    private function logUnverifiedJudgings(EventLogService $eventLog)
    {
        /** @var Judging[] $judgings */
        $judgings = $this->em->getRepository(Judging::class)->findBy(
            [ 'verified' => 0, 'valid' => 1]
        );

        $judgings_per_contest = [];
        foreach ($judgings as $judging) {
            $judgings_per_contest[$judging->getCid()][] = $judging->getJudgingid();
        }

        // Log to event table; normal cases are handled in:
        // * API/JudgehostController::addJudgingRunAction
        // * Jury/SubmissionController::verifyAction
        foreach ($judgings_per_contest as $cid => $judging_ids) {
            $eventLog->log('judging', $judging_ids, 'update', $cid);
        }

        $this->logger->info("created events for unverified judgings");
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

    /**
     * Add options to some items
     *
     * This method is used to add predefined options that need to be loaded
     * from the database to certain items.
     *
     * @param array $item
     *
     * @return array
     */
    public function addOptions(array $item): array
    {
        switch ($item['name']) {
            case 'default_compare':
            case 'default_run':
                $executables     = $this->em->getRepository(Executable::class)->findAll();
                $item['options'] = [];
                foreach ($executables as $executable) {
                    $item['options'][$executable->getExecid()] = $executable->getDescription();
                }
                break;
            case 'results_prio':
            case 'results_remap':
                $verdictsConfig      = $this->etcDir . '/verdicts.php';
                $verdicts            = include $verdictsConfig;
                $item['key_options'] = ['' => ''];
                foreach (array_keys($verdicts) as $verdict) {
                    $item['key_options'][$verdict] = $verdict;
                }
                if ($item['name'] === 'results_remap') {
                    $item['value_options'] = $item['key_options'];
                }
        }
        return $item;
    }
}
