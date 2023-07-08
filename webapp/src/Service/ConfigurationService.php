<?php declare(strict_types=1);

namespace App\Service;

use App\Config\Loader\YamlConfigLoader;
use App\Entity\Configuration;
use App\Entity\Executable;
use App\Entity\Judging;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Autoconfigure(public: true)]
class ConfigurationService
{
    protected ?array $dbConfigCache = null;

    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly LoggerInterface $logger,
        #[Autowire(service: 'config_cache_factory')]
        protected readonly ConfigCacheFactoryInterface $configCache,
        #[Autowire('%kernel.debug%')]
        protected readonly bool $debug,
        #[Autowire('%kernel.cache_dir%')]
        protected readonly string $cacheDir,
        #[Autowire('%domjudge.etcdir%')]
        protected readonly string $etcDir
    ) {}

    /**
     * Get the value for the given configuration name
     *
     * @param string $name         The config name to get the value of
     * @param bool   $onlyIfPublic Only return the value if the config is
     *                             public
     *
     * @return mixed The configuration value
     * @throws InvalidArgumentException If the config can't be found and not default is
     *                                  supplied
     */
    public function get(string $name, bool $onlyIfPublic = false)
    {
        $spec = $this->getConfigSpecification()[$name] ?? null;

        if (!isset($spec) || ($onlyIfPublic && !$spec['public'])) {
            throw new InvalidArgumentException("Configuration variable '$name' not found.");
        }

        $value = $this->getDbValues()[$name] ?? $spec['default_value'];

        if (isset($spec['enum_class'])) {
            if (!class_exists($spec['enum_class'])) {
                throw new InvalidArgumentException("Enum class '$spec[enum_class]' not found.");
            }

            return call_user_func($spec['enum_class'] . '::from', $value);
        }

        return $value;
    }

    /**
     * Get all the configuration values, indexed by name.
     *
     * @throws InvalidArgumentException
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
     * Get all configuration specifications.
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
                // since requesting data is faster in that case.
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

        return require $cacheFile;
    }

    /**
     * Save the changes from the given request.
     *
     * @throws NonUniqueResultException
     */
    public function saveChanges(
        array $dataToSet,
        EventLogService $eventLog,
        DOMJudgeService $dj
    ): void {
        $specs = $this->getConfigSpecification();
        foreach ($specs as &$spec) {
            $spec = $this->addOptions($spec);
        }
        unset($spec);

        /** @var Configuration[] $options */
        $options = $this->em->createQueryBuilder()
            ->from(Configuration::class, 'c', 'c.name')
            ->select('c')
            ->getQuery()
            ->getResult();

        $logUnverifiedJudgings = false;
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
                $oldValue && !$val) {
                // If toggled off, we have to send events for all judgings
                // that are complete, but not verified yet. Scoreboard
                // cache refresh should take care of the rest. See #645.
                // We log unverified judgings after saving all configuration
                // since it will invalidate Doctrine entities.
                $logUnverifiedJudgings = true;
            }
            switch ($spec['type']) {
                case 'bool':
                    $optionToSet->setValue((bool)$val);
                    break;

                case 'int':
                    $optionToSet->setValue((int)$val);
                    break;

                case 'string':
                case 'enum':
                    $optionToSet->setValue($val);
                    break;

                case 'array_val':
                    $result = [];
                    foreach ($val as $data) {
                        if (!empty($data)) {
                            $result[] = $data;
                        }
                    }
                    $optionToSet->setValue($result);
                    break;

                case 'array_keyval':
                    $result = [];
                    foreach ($val as $key => $data) {
                        if (!empty($data)) {
                            $result[$key] = $data;
                        }
                    }
                    $optionToSet->setValue($result);
                    break;

                default:
                    $this->logger->warning(
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

        $this->em->flush();

        if ($logUnverifiedJudgings) {
            $this->logUnverifiedJudgings($eventLog);
        }

        $this->dbConfigCache = null;
    }

    /**
     * @throws NonUniqueResultException
     */
    private function logUnverifiedJudgings(EventLogService $eventLog): void
    {
        /** @var Judging[] $judgings */
        $judgings = $this->em->getRepository(Judging::class)->findBy(
            [ 'verified' => 0, 'valid' => 1]
        );

        $judgings_per_contest = [];
        foreach ($judgings as $judging) {
            $judgings_per_contest[$judging->getContest()->getCid()][] = $judging->getJudgingid();
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
     * Get the configuration values from the database.
     */
    protected function getDbValues(): array
    {
        if ($this->dbConfigCache === null) {
            $this->dbConfigCache = [];
            $configs = $this->em->getRepository(Configuration::class)->findAll();
            foreach ($configs as $config) {
                $this->dbConfigCache[$config->getName()] = $config->getValue();
            }
        }

        return $this->dbConfigCache;
    }

    /**
     * Find list of options for configuration parameters that specify a known executable.
     *
     * @param string $type Any of "compare", "compile", "run"
     */
    private function findExecutableOptions(string $type): array
    {
        $executables = $this->em->getRepository(Executable::class)->findBy(['type'=>$type]);
        $options = [];
        foreach ($executables as $executable) {
            $options[$executable->getExecid()] = $executable->getDescription();
        }
        return $options;
    }

    /**
     * Add options to some items.
     *
     * This method is used to add predefined options that need to be loaded
     * from the database to certain items.
     */
    public function addOptions(array $item): array
    {
        switch ($item['name']) {
            case 'default_compare':
                $item['options'] = $this->findExecutableOptions('compare');
                break;
            case 'default_run':
                $item['options'] = $this->findExecutableOptions('run');
                break;
            case 'default_full_debug':
                $item['options'] = $this->findExecutableOptions('debug');
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

        if ($item['type'] === 'enum') {
            $enumClass = $item['enum_class'];
            /** @var \BackedEnum[] $cases */
            $cases = call_user_func($enumClass . '::cases');
            foreach ($cases as $case) {
                if (method_exists($case, 'getConfigDescription')) {
                    $item['options'][$case->value] = $case->getConfigDescription();
                } else {
                    $item['options'][$case->value] = $case->name;
                }
            }
        }
        return $item;
    }
}
