<?php declare(strict_types=1);

namespace Tests;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class JuryClarificationsTest extends WebTestCase
{
    private $client;

    protected function setUp()
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
    }

    public function testJuryRedirectToLogin()
    {
        $this->client->request('GET', '/jury');

        $response = $this->client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(302, $response->getStatusCode(), $message);
        $this->assertEquals('http://localhost/login', $response->getTargetUrl(), $message);
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

    public function testLogin()
    {
        # test incorrect and correct password
        $this->loginHelper('dummy', 'foo', 'http://localhost/login', 200);
        $this->loginHelper('dummy', 'dummy', 'http://localhost/jury', 200);
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

    public function testJuryIndexPage()
    {
        $this->logIn();
        $crawler = $this->client->request('GET', '/jury');

        $response = $this->client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(200, $response->getStatusCode(), $message);

        $this->assertEquals(1, $crawler->filter('html:contains("DOMjudge Jury interface")')->count());
    }

    public function testClarificationRequestIndex()
    {
        $this->logIn();
        $crawler = $this->client->request('GET', '/jury');

        $response = $this->client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(200, $response->getStatusCode(), $message);

        $link = $crawler->selectLink('Clarifications')->link();
        $message = var_export($link, true);
        $this->assertEquals('http://localhost/jury/clarifications', $link->getUri(), $message);

        $crawler = $this->client->click($link);

        $h3s = $crawler->filter('h3')->extract(array('_text'));
        $this->assertEquals('New requests:', $h3s[0]);
        $this->assertEquals('Old requests:', $h3s[1]);
        $this->assertEquals('General clarifications:', $h3s[2]);

        $this->assertEquals(1, $crawler->filter('html:contains("Can you tell me how")')->count());
        $this->assertEquals(1, $crawler->filter('html:contains("21:47")')->count());
    }

    public function testClarificationRequestView()
    {
        $this->logIn();
        $crawler = $this->client->request('GET', '/jury/clarifications/1');

        $response = $this->client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(200, $response->getStatusCode(), $message);

        $pres = $crawler->filter('pre')->extract(array('_text'));
        $this->assertEquals('Can you tell me how to solve this problem?', $pres[0]);
        $this->assertEquals("> Can you tell me how to solve this problem?\r\n\r\nNo, read the problem statement.", $pres[1]);

        $link = $crawler->selectLink('Example teamname (t2)')->link();
        $message = var_export($link, true);
        $this->assertEquals('http://localhost/jury/teams/2', $link->getUri(), $message);
    }

    public function testClarificationRequestComposeForm()
    {
        $this->logIn();
        $crawler = $this->client->request('GET', '/jury/clarifications');

        $link = $crawler->selectLink('Send clarification')->link();
        $message = var_export($link, true);
        $this->assertEquals('http://localhost/jury/clarifications/send', $link->getUri(), $message);

        $crawler = $this->client->click($link);

        $h1s = $crawler->filter('h1')->extract(array('_text'));
        $this->assertEquals('Send Clarification', $h1s[0]);

        $options = $crawler->filter('option')->extract(array('_text'));
        $this->assertEquals('ALL', $options[1]);
        $this->assertEquals('DOMjudge (t1)', $options[2]);
        $this->assertEquals('Example teamname (t2)', $options[3]);

        $labels = $crawler->filter('label')->extract(array('_text'));
        $this->assertEquals('Send to:', $labels[0]);
        $this->assertEquals('Subject:', $labels[1]);
        $this->assertEquals('Message:', $labels[2]);
    }
}
