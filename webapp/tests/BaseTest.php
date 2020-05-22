<?php declare(strict_types=1);

namespace App\Tests;

use App\Entity\Configuration;
use App\Entity\Role;
use App\Entity\User;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
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
     * @inheritDoc
     */
    protected function setUp()
    {
        // Reset the kernel to make sure we have a clean slate
        self::ensureKernelShutdown();

        // Create a client to communicate with the application
        $this->client = self::createClient();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown()
    {
        // Clear all configuration options still stored in the database
        $em          = self::$container->get(EntityManagerInterface::class);
        $configItems = $em->getRepository(Configuration::class)->findAll();
        foreach ($configItems as $configItem) {
            $em->remove($configItem);
        }
        $em->flush();

        $config = self::$container->get(ConfigurationService::class);

        // Also call ConfigurationService::saveChanges with an empty array to
        // clear any cached configuration
        $eventLog = self::$container->get(EventLogService::class);
        $dj       = self::$container->get(DOMJudgeService::class);
        $config->saveChanges([], $eventLog, $dj);
    }

    /**
     * Helper method to test login for a user
     *
     * @param string $username
     * @param string $password
     * @param string $redirectPage
     * @param int    $responseCode
     *
     * @return KernelBrowser
     */
    protected function loginHelper(
        string $username,
        string $password,
        string $redirectPage,
        int $responseCode
    ) {
        $crawler = $this->client->request('GET', '/login');

        # load login page
        $response = $this->client->getResponse();
        $message  = var_export($response, true);
        $this->assertEquals(200, $response->getStatusCode(), $message);

        $csrf_token = $this->client->getContainer()->get('security.csrf.token_manager')->getToken('authenticate');

        # submit form
        $button = $crawler->selectButton('Sign in');
        $form   = $button->form(array(
            '_username' => $username,
            '_password' => $password,
            '_csrf_token' => $csrf_token,
        ));
        $this->client->followRedirects();
        $crawler  = $this->client->submit($form);
        $response = $this->client->getResponse();
        $this->client->followRedirects(false);

        # check redirected to $redirectPage
        $message = var_export($response, true);
        $this->assertEquals($responseCode, $response->getStatusCode(),
            $message);
        $this->assertEquals($redirectPage,
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
    protected function logIn()
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
     * Set up the dummy user with the roles given in static::$roles
     *
     * @return User
     */
    protected function setupUser()
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
     * @param string   $configKey
     * @param mixed    $configValue
     * @param callable $callback
     */
    protected function withChangedConfiguration(
        string $configKey,
        $configValue,
        callable $callback
    ) {
        $config   = self::$container->get(ConfigurationService::class);
        $eventLog = self::$container->get(EventLogService::class);
        $dj       = self::$container->get(DOMJudgeService::class);

        // Build up the data to set
        $dataToSet = [$configKey => $configValue];

        // Save the changes
        $config->saveChanges($dataToSet, $eventLog, $dj);

        // Call the callback
        call_user_func($callback);

        // Reset the changes by removing it from the database
        $em         = self::$container->get(EntityManagerInterface::class);
        $configItem = $em->getRepository(Configuration::class)->findOneBy(['name' => $configKey]);
        $em->remove($configItem);
        $em->flush();

        // Call saveChanges with an empty array to clear any pending config
        $config->saveChanges([], $eventLog, $dj);
    }
}
