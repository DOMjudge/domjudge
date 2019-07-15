<?php declare(strict_types=1);

namespace Tests;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class JuryPrintTest extends WebTestCase
{
    private function loginHelper($username, $password, $redirectPage, $responseCode)
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/login');

        # load login page
        $response = $client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(200, $response->getStatusCode(), $message);

        $csrf_token = $client->getContainer()->get('security.csrf.token_manager')->getToken('authenticate');

        # submit form
        $button = $crawler->selectButton('Sign in');
        $form = $button->form(array(
            '_username' => $username,
            '_password' => $password,
            '_csrf_token' => $csrf_token,
        ));
        $client->followRedirects();
        $crawler = $client->submit($form);
        $response = $client->getResponse();
        $client->followRedirects(false);

        # check redirected to $redirectPage
        $message = var_export($response, true);
        $this->assertEquals($responseCode, $response->getStatusCode(), $message);
        $this->assertEquals($redirectPage, $client->getRequest()->getUri(), $message);

        return $client;
    }

    // This just injects a user object into the session so symfony will think we're logged in
    // It gets around the problem for now of trying to navigate to two legacy pages in a single
    // test(login index + anything else)
    private function logIn($client)
    {
        $session = $client->getContainer()->get('session');

        $firewallName = 'main';
        $firewallContext = 'main';

        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->getRepository(User::class)->findOneBy(['username' => 'dummy']);
        $token = new UsernamePasswordToken($user, null, $firewallName, array('ROLE_JURY'));
        $session->set('_security_'.$firewallContext, serialize($token));
        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $client->getCookieJar()->set($cookie);
    }

    public function testPrintingDisabledJuryIndexPage()
    {
        $client = self::createClient();
        $this->logIn($client);
        $crawler = $client->request('GET', '/jury');

        $response = $client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(200, $response->getStatusCode(), $message);
        $this->assertEquals(0, $crawler->filter('a:contains("Print")')->count());
    }

    public function testPrintingDisabledAccessDenied()
    {
        $client = self::createClient();
        $this->logIn($client);
        $crawler = $client->request('GET', '/jury/print');

        $response = $client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(403, $response->getStatusCode(), $message);

    }

    /* In travis printing is currently not enabled, so tests are very
     * limited now. When more things moved to Symfony, we should vary
     * the configuration setting so we can also test the real thing.
     */
}
