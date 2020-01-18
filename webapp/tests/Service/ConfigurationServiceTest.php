<?php declare(strict_types=1);

namespace Tests\Service;

use App\Entity\Configuration;
use App\Service\ConfigurationService;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Yaml\Yaml;

class ScoreboardServiceTest extends KernelTestCase
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

    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        self::bootKernel();

        $this->em               = $this->createMock(EntityManagerInterface::class);
        $this->configRepository = $this->createMock(ObjectRepository::class);
        $this->em->expects($this->any())
            ->method('getRepository')
            ->with(Configuration::class)
            ->willReturn($this->configRepository);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->config = new ConfigurationService(
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
    protected function tearDown()
    {
        $this->em     = null;
        $this->logger = null;
        $this->config = null;
    }

    /**
     * @dataProvider provideConfigDefaults
     *
     * @param string $categoryName
     * @param string $itemName
     *
     * @throws Exception
     */
    public function testConfigDefaults(string $categoryName, string $itemName)
    {
        $foundItem = $this->findItem($categoryName, $itemName);

        $this->configRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $defaultValue = $foundItem['default_value'];
        $this->assertSame($defaultValue, $this->config->get($itemName));
    }

    /**
     * @dataProvider provideConfigDefaults
     *
     * @param string $categoryName
     * @param string $itemName
     *
     * @throws Exception
     */
    public function testConfigDefaultsAll(
        string $categoryName,
        string $itemName
    ) {
        $foundItem = $this->findItem($categoryName, $itemName);

        $this->configRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $defaultValue = $foundItem['default_value'];
        $all          = $this->config->all();
        $this->assertSame($defaultValue, $all[$itemName]);
    }

    /**
     * @return Generator
     */
    public function provideConfigDefaults()
    {
        yield ['Scoring', 'compile_penalty'];
        yield ['Scoring', 'results_prio'];
        yield ['Clarification', 'clar_categories'];
        yield ['Display', 'show_compile'];
        yield ['Display', 'time_format'];
    }

    /**
     * @dataProvider provideInvalidItem
     * @expectedException Exception
     * @expectedExceptionMessageRegExp /^Configuration variable '.*' not found\.$/
     *
     * @param string $itemName
     * @param bool   $publicOnly
     */
    public function testInvalidItem(string $itemName, bool $publicOnly)
    {
        $this->configRepository->expects($this->never())
            ->method('findAll');

        $this->config->get($itemName, $publicOnly);
    }

    public function provideInvalidItem()
    {
        yield ['does_not_exist', false]; // This item does not exist
        yield ['does_not_exist', true];
        yield ['results_prio', true]; // This item exists but is non-public
    }

    /**
     * @dataProvider provideConfigFromDatabase
     *
     * @param string $itemName
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
    ) {
        $config = new Configuration();
        $config
            ->setName($itemName)
            ->setValue($dbValue);
        $this->configRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$config]);

        if ($expectedValue === null) {
            $expectedValue = $dbValue;
        }

        $this->assertSame($expectedValue, $this->config->get($itemName));
        // Also make sure it doesn't change other items by testing a different one
        $openIdAuthItem = $this->findItem('Authentication', 'openid_provider');
        $this->assertSame(
            $openIdAuthItem['default_value'],
            $this->config->get('openid_provider')
        );
    }

    /**
     * @dataProvider provideConfigFromDatabase
     *
     * @param string $itemName
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
    ) {
        $config = new Configuration();
        $config
            ->setName($itemName)
            ->setValue($dbValue);
        $this->configRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$config]);

        if ($expectedValue === null) {
            $expectedValue = $dbValue;
        }

        $all = $this->config->all();
        $this->assertSame($expectedValue, $all[$itemName]);
        // Also make sure it doesn't change other items by testing a different one
        $openIdAuthItem = $this->findItem('Authentication', 'openid_provider');
        $this->assertSame(
            $openIdAuthItem['default_value'], $all['openid_provider']
        );
    }

    /**
     * @return Generator
     */
    public function provideConfigFromDatabase()
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
     * @param string $itemName
     *
     * @throws Exception
     */
    public function testAllHidesNonPublic(string $itemName)
    {
        $this->configRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $all = $this->config->all(true);
        $this->assertArrayNotHasKey($itemName, $all);
    }

    /**
     * @return Generator
     */
    public function provideAllHidesNonPublic()
    {
        yield ['verification_required'];
        yield ['script_timelimit'];
        yield ['default_run'];
    }

    /**
     * @throws Exception
     */
    public function testUnknownConfigsNonPublic()
    {
        $unknownItems = [
            (new Configuration())
                ->setName('unknown1')
                ->setValue('foobar'),
            (new Configuration())
                ->setName('unknown2')
                ->setValue('barfoo'),
        ];
        $this->configRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($unknownItems);
        $this->logger->expects($this->exactly(2))
            ->method('warning')
            ->withConsecutive(
                ['Configuration value %s not defined', ['unknown1']],
                ['Configuration value %s not defined', ['unknown2']]
            );

        $all = $this->config->all();
        $this->assertArrayNotHasKey('unknown1', $all);
        $this->assertArrayNotHasKey('unknown2', $all);
    }

    /**
     * @throws Exception
     */
    public function testUnknownConfigsPublic()
    {
        $unknownItems = [
            (new Configuration())
                ->setName('unknown1')
                ->setValue('foobar'),
            (new Configuration())
                ->setName('unknown2')
                ->setValue('barfoo'),
        ];
        $this->configRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($unknownItems);
        $this->logger->expects($this->never())
            ->method('warning');

        $all = $this->config->all(true);
        $this->assertArrayNotHasKey('unknown1', $all);
        $this->assertArrayNotHasKey('unknown2', $all);
    }

    /**
     * Find a config item specification
     *
     * @param string $categoryName
     * @param string $itemName
     *
     * @return array|null
     */
    protected function findItem(string $categoryName, string $itemName)
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

        $this->assertNotNull(
            $foundItem, 'Config item not found in db-config.yaml'
        );

        return $foundItem;
    }
}
