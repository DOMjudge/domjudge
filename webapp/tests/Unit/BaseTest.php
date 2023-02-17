<?php declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Role;
use App\Entity\User;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Link;
use Symfony\Component\PropertyAccess\PropertyAccess;
use ZipArchive;

/**
 * This abstract class can be used to have default functionality to test cases.
 *
 * @package App\Tests
 */
abstract class BaseTest extends WebTestCase
{
    protected KernelBrowser $client;

    /** @var string[] */
    protected array        $roles           = [];
    protected ?ORMExecutor $fixtureExecutor = null;
    /** @var string[] */
    protected static array $fixtures = [];
    protected static array $dataSources = [DOMJudgeService::DATA_SOURCE_LOCAL,
                                           DOMJudgeService::DATA_SOURCE_CONFIGURATION_EXTERNAL,
                                           DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL];

    protected function setUp(): void
    {
        // Reset the kernel to make sure we have a clean slate.
        self::ensureKernelShutdown();

        // Create a client to communicate with the application.
        $this->client = self::createClient();

        // Log in if we have any roles.
        if (!empty($this->roles)) {
            $this->logIn();
        }

        if (!empty(static::$fixtures)) {
            $this->loadFixtures(static::$fixtures);
        }
    }

    /**
     * Load the given fixtures.
     */
    protected function loadFixtures(array $fixtures): void
    {
        if ($this->fixtureExecutor === null) {
            $this->fixtureExecutor = new ORMExecutor(static::getContainer()->get(EntityManagerInterface::class));
        }

        $loader = new Loader();
        foreach ($fixtures as $fixture) {
            if (!is_subclass_of($fixture, FixtureInterface::class)) {
                throw new Exception(sprintf('%s is not a fixture', $fixture));
            }
            $loader->addFixture(new $fixture());
        }

        $this->fixtureExecutor->execute($loader->getFixtures(), true);
    }

    /**
     * Load the given fixture.
     */
    protected function loadFixture(string $fixture): void
    {
        $this->loadFixtures([$fixture]);
    }

    /**
     * Resolve any references in the given ID.
     */
    protected function resolveReference($id)
    {
        // If the object ID contains a :, it is a reference to a fixture item, so get it.
        if (is_string($id) && strpos($id, ':') !== false) {
            $referenceObject = $this->fixtureExecutor->getReferenceRepository()->getReference($id);
            $metadata = static::getContainer()->get(EntityManagerInterface::class)->getClassMetadata(get_class($referenceObject));
            $propertyAccessor = PropertyAccess::createPropertyAccessor();
            return $propertyAccessor->getValue($referenceObject, $metadata->getSingleIdentifierColumnName());
        }

        return $id;
    }

    protected function loginHelper(
        string $username,
        string $password,
        string $redirectPage,
        int $responseCode
    ): KernelBrowser {
        $crawler = $this->client->request('GET', '/login');

        // Load login page.
        $response = $this->client->getResponse();
        $message = var_export($response, true);
        self::assertEquals(200, $response->getStatusCode(), $message);

        // Submit form.
        $button = $crawler->selectButton('Sign in');
        $form = $button->form(array(
            '_username'   => $username,
            '_password'   => $password,
        ));
        $this->client->followRedirects();
        $this->client->submit($form);
        $response = $this->client->getResponse();
        $this->client->followRedirects(false);

        // Check redirect to $redirectPage.
        $message = var_export($response, true);
        self::assertEquals($responseCode, $response->getStatusCode(),
            $message);
        self::assertEquals($redirectPage,
            $this->client->getRequest()->getUri(), $message);

        return $this->client;
    }

    /**
     * Log in a user with the roles defined in $roles.
     *
     * Note that this will change the roles of the user in the database, so if
     * you assume specific roles on a user, make sure to set them using setupUser().
     *
     * @see https://symfony.com/doc/current/testing/http_authentication.html#creating-the-authentication-token
     */
    protected function logIn(): void
    {
        $this->client->loginUser($this->setupUser());
    }

    /**
     * Log out a user.
     *
     * This is needed when you set $roles but have a test that should be used
     * while being logged out.
     */
    protected function logOut(): void
    {
        if ($this->client->getContainer()->has('session.factory')) {
            $session = $this->client->getContainer()->get('session.factory')->createSession();
        } else {
            $session = $this->client->getContainer()->get('session');
        }
        $this->client->getCookieJar()->expire($session->getName());
    }

    /**
     * Set up the demo user with the roles given in $roles.
     */
    protected function setupUser(): User
    {
        $em = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        /** @var User $user */
        $user = $em->getRepository(User::class)->findOneBy(['username' => 'demo']);
        if ($user === null) {
            throw new Exception("No user 'demo' found, are you using the correct database?");
        }
        // Clear all user roles, so we can set them specifically to what we want.
        foreach ($user->getUserRoles() as $role) {
            $user->removeUserRole($role);
        }
        // Now add the roles.
        foreach ($this->roles as $role) {
            $user->addUserRole($em->getRepository(Role::class)->findOneBy(['dj_role' => $role]));
        }
        $em->flush();

        return $user;
    }

    /**
     * Run the given callback while temporarily changing the given configuration setting.
     *
     * @param mixed $configValue
     */
    protected function withChangedConfiguration(
        string $configKey,
        $configValue,
        callable $callback
    ): void {
        $config = self::getContainer()->get(ConfigurationService::class);
        $eventLog = self::getContainer()->get(EventLogService::class);
        $dj = self::getContainer()->get(DOMJudgeService::class);

        // Build up the data to set.
        $dataToSet = [$configKey => $configValue];

        // Save the changes.
        $config->saveChanges($dataToSet, $eventLog, $dj);

        // Call the callback.
        $callback();
    }

    protected function verifyPageResponse(
        string $method,
        string $uri,
        int $status,
        ?string $responseUrl = null,
        bool $ajax = false
    ): void {
        if ($ajax) {
            $this->client->xmlHttpRequest($method, $uri);
        } else {
            $this->client->request($method, $uri);
        }
        $response = $this->client->getResponse();
        $message = var_export($response, true);
        self::assertEquals($status, $response->getStatusCode(), $message . "\nURI = $uri");
        if ($responseUrl !== null) {
            self::assertEquals($responseUrl, $response->getTargetUrl(), $message);
        }
    }

    protected function getCurrentCrawler(): Crawler
    {
        return $this->client->getCrawler();
    }

    protected function verifyLinkToURL(string $linkName, string $url): Link
    {
        $link = $this->getCurrentCrawler()->selectLink($linkName)->link();
        $message = var_export($link, true);
        self::assertEquals($url, $link->getUri(), $message);

        return $link;
    }

    protected function verifyRedirectToURL(string $url): void
    {
        $crawler = $this->client->followRedirect();
        self::assertEquals($url, $crawler->getUri());
    }

    /**
     * Whether the data source is local.
     */
    protected function dataSourceIsLocal(): bool
    {
        $config = self::getContainer()->get(ConfigurationService::class);
        $dataSource = $config->get('data_source');
        return $dataSource === DOMJudgeService::DATA_SOURCE_LOCAL;
    }

    /**
     * Get the contest ID of the demo contest based on the data source setting.
     *
     * @return string
     */
    protected function getDemoContestId(): string
    {
        if ($this->dataSourceIsLocal()) {
            return (string)$this->demoContest->getCid();
        }

        return $this->demoContest->getExternalid();
    }

    /**
     * Resolve the entity ID for the given class if not running in local mode.
     */
    protected function resolveEntityId(string $class, ?string $id): ?string
    {
        if ($id !== null && !$this->dataSourceIsLocal()) {
            $entity = static::getContainer()->get(EntityManagerInterface::class)->getRepository($class)->find($id);
            // If we can't find the entity, assume we use an invalid one.
            if ($entity === null) {
                return $id;
            }
            return $entity->getExternalid();
        }

        return $id;
    }

    /**
     * Given a zipfile in string format, unzip it and return contents as
     * a key-value array.
     */
    protected function unzipString(string $content): array
    {
        $zip = new ZipArchive();
        $tempFilename = tempnam(static::getContainer()->get(DOMJudgeService::class)->getDomjudgeTmpDir(), "domjudge-test-");
        file_put_contents($tempFilename, $content);

        $zip->open($tempFilename);
        $return = [];
        for ($i = 0; $i < $zip->count(); ++$i) {
            $return[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }
        $zip->close();

        unlink($tempFilename);
        return $return;
    }

    protected function removeTestContainer(): void
    {
        self::ensureKernelShutdown();
        $container = __DIR__ . '/../../var/cache/test/App_KernelTestDebugContainer.php';
        if (file_exists($container)) {
            unlink($container);
        }
        self::bootKernel();
    }

    protected function getDatasourceLoops(): array
    {
        $dataSources = [];
        if (array_key_exists('CRAWL_DATASOURCES', getenv())) {
            $dataSources = explode(',', getenv('CRAWL_DATASOURCES'));
        } elseif (!array_key_exists('CRAWL_ALL', getenv())) {
            $dataSources = array_slice(self::$dataSources, 0, 1);
        }
        return ['dataSources' => $dataSources];
    }

    protected function setupDataSource(int $dataSource): void
    {
        $config   = self::getContainer()->get(ConfigurationService::class);
        $eventLog = self::getContainer()->get(EventLogService::class);
        $dj       = self::getContainer()->get(DOMJudgeService::class);
        $config->saveChanges(['data_source'=>$dataSource], $eventLog, $dj);
    }
}
