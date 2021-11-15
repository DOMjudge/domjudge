<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Configuration;
use App\Entity\Executable;
use App\Service\ConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Exception;
use Generator;
use PHPUnit\Framework\MockObject\Builder\InvocationMocker;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use ReflectionException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Yaml\Yaml;

class ConfigurationServiceTest extends KernelTestCase
{
    /**
     * @var EntityManagerInterface|MockObject
     */
    private $em;

    /**
     * @var ObjectRepository|MockObject
     */
    private $configRepository;

    /**
     * @var InvocationMocker
     */
    private $emGetRepositoryExpects;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var ConfigurationService
     */
    private $config;

    /**
     * @var array
     */
    private $dbConfig;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em                     = $this->createMock(EntityManagerInterface::class);
        $this->configRepository       = $this->createMock(ObjectRepository::class);
        $this->emGetRepositoryExpects = $this->em->expects(self::any())
            ->method('getRepository')
            ->with(Configuration::class)
            ->willReturn($this->configRepository);
        $this->logger                 = $this->createMock(LoggerInterface::class);
        $this->config                 = new ConfigurationService(
            $this->em, $this->logger,
            self::$container->get('config_cache_factory'),
            self::$container->getParameter('kernel.debug'),
            self::$container->getParameter('kernel.cache_dir'),
            self::$container->getParameter('domjudge.etcdir')
        );

        $this->dbConfig = Yaml::parseFile(
            self::$container->getParameter('domjudge.etcdir') . '/db-config.yaml'
        );
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->em     = null;
        $this->logger = null;
        $this->config = null;
    }

    /**
     * @dataProvider provideConfigDefaults
     *
     * @throws Exception
     */
    public function testConfigDefaults(string $categoryName, string $itemName) : void
    {
        $foundItem = $this->findItem($categoryName, $itemName);

        $this->configRepository->expects(self::once())
            ->method('findAll')
            ->willReturn([]);

        $defaultValue = $foundItem['default_value'];
        self::assertSame($defaultValue, $this->config->get($itemName));
    }

    /**
     * @dataProvider provideConfigDefaults
     *
     * @throws Exception
     */
    public function testConfigDefaultsAll(
        string $categoryName,
        string $itemName
    ) : void
    {
        $foundItem = $this->findItem($categoryName, $itemName);

        $this->configRepository->expects(self::once())
            ->method('findAll')
            ->willReturn([]);

        $defaultValue = $foundItem['default_value'];
        $all          = $this->config->all();
        self::assertSame($defaultValue, $all[$itemName]);
    }

    public function provideConfigDefaults() : Generator
    {
        yield ['Scoring', 'compile_penalty'];
        yield ['Scoring', 'results_prio'];
        yield ['Clarifications', 'clar_categories'];
        yield ['Display', 'show_compile'];
        yield ['Display', 'time_format'];
    }

    /**
     * @dataProvider provideInvalidItem
     */
    public function testInvalidItem(string $itemName, bool $publicOnly) : void
    {
        $this->expectExceptionMessageRegExp("/^Configuration variable '.*' not found\.$/");
        $this->configRepository->expects(self::never())
            ->method('findAll');

        $this->config->get($itemName, $publicOnly);
    }

    public function provideInvalidItem() : Generator
    {
        yield ['does_not_exist', false]; // This item does not exist
        yield ['does_not_exist', true];
        yield ['results_prio', true]; // This item exists but is non-public
    }

    /**
     * @dataProvider provideConfigFromDatabase
     *
     * @param mixed  $dbValue
     *
     * @param null   $expectedValue
     *
     * @throws Exception
     */
    public function testConfigFromDatabase(
        string $itemName,
        $dbValue,
        $expectedValue = null
    ) : void
    {
        $config = new Configuration();
        $config
            ->setName($itemName)
            ->setValue($dbValue);
        $this->configRepository->expects(self::once())
            ->method('findAll')
            ->willReturn([$config]);

        if ($expectedValue === null) {
            $expectedValue = $dbValue;
        }

        self::assertSame($expectedValue, $this->config->get($itemName));
        // Also make sure it doesn't change other items by testing a different one
        $defaultCompareItem = $this->findItem('Judging', 'default_compare');
        self::assertSame(
            $defaultCompareItem['default_value'],
            $this->config->get('default_compare')
        );
    }

    /**
     * @dataProvider provideConfigFromDatabase
     *
     * @param mixed  $dbValue
     *
     * @param null   $expectedValue
     *
     * @throws Exception
     */
    public function testConfigFromDatabaseAll(
        string $itemName,
        $dbValue,
        $expectedValue = null
    ) : void
    {
        $config = new Configuration();
        $config
            ->setName($itemName)
            ->setValue($dbValue);
        $this->configRepository->expects(self::once())
            ->method('findAll')
            ->willReturn([$config]);

        if ($expectedValue === null) {
            $expectedValue = $dbValue;
        }

        $all = $this->config->all();
        self::assertSame($expectedValue, $all[$itemName]);
        // Also make sure it doesn't change other items by testing a different one
        $defaultCompareItem = $this->findItem('Judging', 'default_compare');
        self::assertSame(
            $defaultCompareItem['default_value'], $all['default_compare']
        );
    }

    public function provideConfigFromDatabase() : Generator
    {
        yield ['compile_penalty', true, 1];
        yield ['results_prio', ['no-output' => 37, 'correct' => 1]];
        yield ['clar_categories', ['Category 1', 'Category 2']];
        yield ['show_compile', 1];
        yield ['time_format', '%H:%M:%s'];
    }

    /**
     * @dataProvider provideAllHidesNonPublic
     *
     * @throws Exception
     */
    public function testAllHidesNonPublic(string $itemName) : void
    {
        $this->configRepository->expects(self::once())
            ->method('findAll')
            ->willReturn([]);

        $all = $this->config->all(true);
        self::assertArrayNotHasKey($itemName, $all);
    }

    public function provideAllHidesNonPublic() : Generator
    {
        yield ['verification_required'];
        yield ['script_timelimit'];
        yield ['default_run'];
    }

    /**
     * @throws Exception
     */
    public function testUnknownConfigsNonPublic() : void
    {
        $unknownItems = [
            (new Configuration())
                ->setName('unknown1')
                ->setValue('foobar'),
            (new Configuration())
                ->setName('unknown2')
                ->setValue('barfoo'),
        ];
        $this->configRepository->expects(self::once())
            ->method('findAll')
            ->willReturn($unknownItems);
        $this->logger->expects(self::exactly(2))
            ->method('warning')
            ->withConsecutive(
                ['Configuration value %s not defined', ['unknown1']],
                ['Configuration value %s not defined', ['unknown2']]
            );

        $all = $this->config->all();
        self::assertArrayNotHasKey('unknown1', $all);
        self::assertArrayNotHasKey('unknown2', $all);
    }

    /**
     * @throws Exception
     */
    public function testUnknownConfigsPublic() : void
    {
        $unknownItems = [
            (new Configuration())
                ->setName('unknown1')
                ->setValue('foobar'),
            (new Configuration())
                ->setName('unknown2')
                ->setValue('barfoo'),
        ];
        $this->configRepository->expects(self::once())
            ->method('findAll')
            ->willReturn($unknownItems);
        $this->logger->expects(self::never())
            ->method('warning');

        $all = $this->config->all(true);
        self::assertArrayNotHasKey('unknown1', $all);
        self::assertArrayNotHasKey('unknown2', $all);
    }

    /**
     * @dataProvider provideAddOptionsExecutables
     *
     * @param string $item
     * @param array $expected
     * @throws ReflectionException
     */
    public function testAddOptionsExecutables(string $item, array $expected) : void
    {
        if($item === 'default_compare') {
            $executables = [
                (new Executable())
                    ->setExecid('exec1')
                    ->setType('compare')
                    ->setDescription('Descr 1'),
                (new Executable())
                    ->setExecid('exec3')
                    ->setType('compare')
                    ->setDescription('Descr 3'),
            ];
        } elseif($item === 'default_run') {
            $executables = [
                (new Executable())
                    ->setExecid('exec2')
                    ->setType('run')
                    ->setDescription('Descr 2'),
                (new Executable())
                    ->setExecid('exec5')
                    ->setType('run')
                    ->setDescription('Descr 5'),
            ];
        } else {
            throw new Exception("Item value should be default_{compare,run}.");
        }

        $execRepository = $this->createMock(ObjectRepository::class);

        $this->emGetRepositoryExpects->getMatcher()->setParametersMatcher(null);
        $this->emGetRepositoryExpects
            ->with(Executable::class)
            ->willReturn($execRepository);

        $execRepository->expects(self::once())
            ->method('findBy')
            ->willReturn($executables);

        $spec = $this->config->getConfigSpecification()[$item];
        self::assertArrayNotHasKey('options', $spec);
        $spec = $this->config->addOptions($spec);

        self::assertSame($expected, $spec['options']);
    }

    public function provideAddOptionsExecutables() : Generator
    {
        yield ['default_compare', [
            'exec1' => 'Descr 1',
            'exec3' => 'Descr 3',
        ] ];
        yield ['default_run', [
            'exec2' => 'Descr 2',
            'exec5' => 'Descr 5',
        ] ];
    }

    /**
     * @dataProvider provideAddOptionsResults
     *
     * @param string $item
     *
     * @throws Exception
     */
    public function testAddOptionsResults(string $item) : void
    {
        $verdictOptions = ['' => ''];
        $verdictsConfig      = self::$container->getParameter('domjudge.etcdir') . '/verdicts.php';
        $verdicts            = include $verdictsConfig;
        foreach (array_keys($verdicts) as $verdict) {
            $verdictOptions[$verdict] = $verdict;
        }

        $spec = $this->config->getConfigSpecification()[$item];
        self::assertArrayNotHasKey('options', $spec);
        $spec = $this->config->addOptions($spec);

        self::assertSame($verdictOptions, $spec['key_options']);
        if ($item === 'results_remap') {
            self::assertSame($verdictOptions, $spec['value_options']);
        } else {
            self::assertArrayNotHasKey('value_options', $spec);
        }
    }

    public function provideAddOptionsResults() : Generator
    {
        yield ['results_prio'];
        yield ['results_remap'];
    }

    /**
     * Find a config item specification
     */
    protected function findItem(string $categoryName, string $itemName) : ?array
    {
        $foundItem = null;
        foreach ($this->dbConfig as $category) {
            if ($category['category'] === $categoryName) {
                foreach ($category['items'] as $item) {
                    if ($item['name'] === $itemName) {
                        $foundItem = $item;
                        break 2;
                    }
                }
            }
        }

        self::assertNotNull(
            $foundItem, 'Config item not found in db-config.yaml.'
        );

        return $foundItem;
    }
}
