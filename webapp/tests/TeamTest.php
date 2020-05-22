<?php declare(strict_types=1);

namespace Tests;

class TeamTest extends BaseTest
{
    protected static $roles = ['team'];

    public function testTeamRedirectToLogin()
    {
        $this->client->request('GET', '/team');

        $response = $this->client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(302, $response->getStatusCode(), $message);
        $this->assertEquals('http://localhost/login', $response->getTargetUrl(), $message);
    }

    public function testLogin()
    {
        # test incorrect and correct password
        $this->loginHelper('dummy', 'foo', 'http://localhost/login', 200);
        $this->loginHelper('dummy', 'dummy', 'http://localhost/jury', 200);
    }

    public function testTeamOverviewPage()
    {
        $this->logIn();
        $crawler = $this->client->request('GET', '/team');

        $response = $this->client->getResponse();
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
        $this->logIn();
        $crawler = $this->client->request('GET', '/team');

        $response = $this->client->getResponse();
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
