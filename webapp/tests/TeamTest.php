<?php declare(strict_types=1);

namespace Tests;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class TeamTest extends WebTestCase
{
    public function testTeamRedirectToLogin()
    {
        $client = self::createClient();
        $client->request('GET', '/team');

        $response = $client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(302, $response->getStatusCode(), $message);
        $this->assertEquals('http://localhost/login', $response->getTargetUrl(), $message);
    }

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

    public function testLogin()
    {
        # test incorrect and correct password
        $this->loginHelper('dummy', 'foo', 'http://localhost/login', 200);
        $this->loginHelper('dummy', 'dummy', 'http://localhost/jury', 200);
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
        $token = new UsernamePasswordToken($user, null, $firewallName, array('ROLE_TEAM'));
        $session->set('_security_'.$firewallContext, serialize($token));
        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $client->getCookieJar()->set($cookie);
    }

    public function testTeamOverviewPage()
    {
        $client = self::createClient();
        $this->logIn($client);
        $crawler = $client->request('GET', '/team');

        $response = $client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(200, $response->getStatusCode(), $message);

        $this->assertEquals(1, $crawler->filter('html:contains("Example teamname")')->count());

        $h3s = $crawler->filter('h3')->extract(array('_text'));
        $this->assertEquals('Submissions', $h3s[0]);
        $this->assertEquals('Clarifications', $h3s[1]);
        $this->assertEquals('Clarification Requests', $h3s[2]);
    }

    public function testClarificationRequest()
    {
        $client = self::createClient();
        $this->logIn($client);
        $crawler = $client->request('GET', '/team');

        $response = $client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(200, $response->getStatusCode(), $message);

        $link = $crawler->selectLink('request clarification')->link();
        $message = var_export($link, true);
        $this->assertEquals('http://localhost/team/clarifications/add', $link->getUri(), $message);

        # Note that we would like to click the link here but we cannot do
        # that since we have too much global state, e.g. define IS_JURY
        # constants.
    }
}
