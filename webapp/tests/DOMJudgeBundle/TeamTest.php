<?php

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TeamTest extends WebTestCase
{
    public function testTeamRedirectToLogin()
    {
        $client = self::createClient();
        $client->request('GET', '/team/');

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
        $this->loginHelper('dummy', 'dummy', 'http://localhost/', 302);
    }

    public function testTeamOverviewPage()
    {
        $client = $this->loginHelper('dummy', 'dummy', 'http://localhost/', 302);
        global $DB; $DB=null; // Need to reset the DB connection, since the global variable is kept between requests...
        $crawler = $client->request('GET', '/team/');

        $response = $client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(200, $response->getStatusCode(), $message);

        $this->assertEquals(1, $crawler->filter('html:contains("Utrecht University")')->count());

        $h3s = $crawler->filter('h3')->extract(array('_text'));
        $this->assertEquals('Submissions', $h3s[0]);
        $this->assertEquals('Clarifications', $h3s[1]);
        $this->assertEquals('Clarification Requests', $h3s[2]);
    }

    public function testClarificationRequest()
    {
        $client = $this->loginHelper('dummy', 'dummy', 'http://localhost/', 302);
        global $DB; $DB=null; // Need to reset the DB connection, since the global variable is kept between requests...
        $crawler = $client->request('GET', '/team/');

        $response = $client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(200, $response->getStatusCode(), $message);

        $link = $crawler->selectLink('request clarification')->link();
        $message = var_export($link, true);
        $this->assertEquals('http://localhost/team/clarification.php', $link->getUri(), $message);

        # Note that we would like to click the link here but we cannot do
        # that since we have too much global state, e.g. define IS_JURY
        # constants.
    }
}
