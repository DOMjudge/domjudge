<?php declare(strict_types=1);

namespace App\Tests;

use App\Entity\Configuration;
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
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Link;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Class BaseTest
 *
 * This abstract class can be used to have default functionality to test cases
 *
 * @package App\Tests
 */
abstract class BaseTest extends WebTestCase
{
    /** @var KernelBrowser */
    protected $client;

    /** @var string[] */
    protected static $roles = [];

    /**
     * What fixtures to load
     * @var string[]
     */
    protected static $fixtures = [];

    protected function setUp(): void
    {
        // Reset the kernel to make sure we have a clean slate
        self::ensureKernelShutdown();

        // Create a client to communicate with the application
        $this->client = self::createClient();

        // Log in if we have any roles
        if (!empty(static::$roles)) {
            $this->logIn();
        }

        if (!empty(static::$fixtures)) {
            $loader = new Loader();
            foreach (static::$fixtures as $fixture) {
                if (!is_subclass_of($fixture, FixtureInterface::class)) {
                    throw new Exception(sprintf('%s is not a fixture', $fixture));
                }
                $loader->addFixture(new $fixture());
            }

            $executor = new ORMExecutor(static::$container->get(EntityManagerInterface::class));
            $executor->execute($loader->getFixtures(), true);
        }
    }

    protected function loginHelper(
        string $username,
        string $password,
        string $redirectPage,
        int $responseCode
    ) : KernelBrowser
    {
        $crawler = $this->client->request('GET', '/login');

        # load login page
        $response = $this->client->getResponse();
        $message  = var_export($response, true);
        self::assertEquals(200, $response->getStatusCode(), $message);

        $csrf_token = $this->client->getContainer()->get('security.csrf.token_manager')->getToken('authenticate');

        # submit form
        $button = $crawler->selectButton('Sign in');
        $form   = $button->form(array(
            '_username' => $username,
            '_password' => $password,
            '_csrf_token' => $csrf_token,
        ));
        $this->client->followRedirects();
        $this->client->submit($form);
        $response = $this->client->getResponse();
        $this->client->followRedirects(false);

        # check redirected to $redirectPage
        $message = var_export($response, true);
        self::assertEquals($responseCode, $response->getStatusCode(),
                           $message);
        self::assertEquals($redirectPage,
                           $this->client->getRequest()->getUri(), $message);

        return $this->client;
    }

    /**
     * Log in a user with the roles defined in static::$roles.
     *
     * Note that this will change the roles of the user in the database, so if
     * you assume specific roles on a user, make sure to set them using setupUser().
     *
     * @see https://symfony.com/doc/current/testing/http_authentication.html#creating-the-authentication-token
     */
    protected function logIn(): void
    {
        $session = $this->client->getContainer()->get('session');

        $firewallName    = 'main';
        $firewallContext = 'main';

        $user  = $this->setupUser();
        $token = new UsernamePasswordToken($user, null, $firewallName,
            $user->getRoles());
        $session->set('_security_' . $firewallContext, serialize($token));
        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $this->client->getCookieJar()->set($cookie);
    }

    /**
     * Log out a user
     *
     * This is needed when you set $roles but have a test that should be used
     * while being logged out
     */
    protected function logOut(): void
    {
        $session = $this->client->getContainer()->get('session');
        $this->client->getCookieJar()->expire($session->getName());
    }

    /**
     * Set up the dummy user with the roles given in static::$roles
     */
    protected function setupUser() : User
    {
        $em = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        /** @var User $user */
        $user = $em->getRepository(User::class)->findOneBy(['username' => 'dummy']);
        // Clear all user roles, so we can set them specifically to what we want
        foreach ($user->getUserRoles() as $role) {
            $user->removeUserRole($role);
        }
        // Now add the roles
        foreach (static::$roles as $role) {
            $user->addUserRole($em->getRepository(Role::class)->findOneBy(['dj_role' => $role]));
        }
        $em->flush();

        return $user;
    }

    /**
     * Run the given callback while temporarily changing the given configuration setting
     *
     * @param mixed    $configValue
     */
    protected function withChangedConfiguration(
        string $configKey,
        $configValue,
        callable $callback
    ) : void
    {
        $config   = self::$container->get(ConfigurationService::class);
        $eventLog = self::$container->get(EventLogService::class);
        $dj       = self::$container->get(DOMJudgeService::class);

        // Build up the data to set
        $dataToSet = [$configKey => $configValue];

        // Save the changes
        $config->saveChanges($dataToSet, $eventLog, $dj);

        // Call the callback
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
        self::assertEquals($status, $response->getStatusCode(), $message);
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
}
