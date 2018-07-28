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

    private function loginHelper($username, $password, $redirectPage)
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/login');

        # load login page
        $response = $client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(200, $response->getStatusCode(), $message);

        # submit form
        $button = $crawler->selectButton('Sign in');
        $form = $button->form(array(
        '_username' => $username,
        '_password' => $password,
    ));
        $crawler = $client->submit($form);

        # check redirect to /
        $response = $client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(302, $response->getStatusCode(), $message);
        $this->assertEquals($redirectPage, $response->getTargetUrl(), $message);

        return $client;
    }

    public function testLogin()
    {
        # test incorrect and correct password
        $this->loginHelper('dummy', 'foo', 'http://localhost/login');
        $this->loginHelper('dummy', 'dummy', 'http://localhost/');
    }

    public function testTeamOverviewPage()
    {
        $client = $this->loginHelper('dummy', 'dummy', 'http://localhost/');
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
        $client = $this->loginHelper('dummy', 'dummy', 'http://localhost/');
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
