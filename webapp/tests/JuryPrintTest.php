<?php declare(strict_types=1);

namespace Tests;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class JuryPrintTest extends WebTestCase
{
    private $client;

    protected function setUp()
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
    }

    private function loginHelper($username, $password, $redirectPage, $responseCode)
    {
        $crawler = $this->client->request('GET', '/login');

        # load login page
        $response = $this->client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(200, $response->getStatusCode(), $message);

        $csrf_token = $this->client->getContainer()->get('security.csrf.token_manager')->getToken('authenticate');

        # submit form
        $button = $crawler->selectButton('Sign in');
        $form = $button->form(array(
            '_username' => $username,
            '_password' => $password,
            '_csrf_token' => $csrf_token,
        ));
        $this->client->followRedirects();
        $crawler = $this->client->submit($form);
        $response = $this->client->getResponse();
        $this->client->followRedirects(false);

        # check redirected to $redirectPage
        $message = var_export($response, true);
        $this->assertEquals($responseCode, $response->getStatusCode(), $message);
        $this->assertEquals($redirectPage, $this->client->getRequest()->getUri(), $message);

        return $this->client;
    }

    // This just injects a user object into the session so symfony will think we're logged in
    // It gets around the problem for now of trying to navigate to two legacy pages in a single
    // test(login index + anything else)
    private function logIn()
    {
        $session = $this->client->getContainer()->get('session');

        $firewallName = 'main';
        $firewallContext = 'main';

        $em = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->getRepository(User::class)->findOneBy(['username' => 'dummy']);
        $token = new UsernamePasswordToken($user, null, $firewallName, array('ROLE_JURY'));
        $session->set('_security_'.$firewallContext, serialize($token));
        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $this->client->getCookieJar()->set($cookie);
    }

    public function testPrintingDisabledJuryIndexPage()
    {
        $this->logIn();
        $crawler = $this->client->request('GET', '/jury');

        $response = $this->client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(200, $response->getStatusCode(), $message);
        $this->assertEquals(0, $crawler->filter('a:contains("Print")')->count());
    }

    public function testPrintingDisabledAccessDenied()
    {
        $this->logIn();
        $crawler = $this->client->request('GET', '/jury/print');

        $response = $this->client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(403, $response->getStatusCode(), $message);

    }

    /* In travis printing is currently not enabled, so tests are very
     * limited now. When more things moved to Symfony, we should vary
     * the configuration setting so we can also test the real thing.
     */
}
