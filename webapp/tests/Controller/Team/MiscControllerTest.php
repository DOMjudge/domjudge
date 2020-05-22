<?php declare(strict_types=1);

namespace App\Tests\Controller\Team;

use App\Tests\BaseTest;

class MiscControllerTest extends BaseTest
{
    protected static $roles = ['team'];

    /**
     * Test that if no user is logged in the user gets redirected to the login page
     */
    public function testTeamRedirectToLogin()
    {
        $this->client->request('GET', '/team');

        $response = $this->client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(302, $response->getStatusCode(), $message);
        $this->assertEquals('http://localhost/login', $response->getTargetUrl(), $message);
    }

    /**
     * Test the login process for teams
     */
    public function testLogin()
    {
        // Make sure the suer has the correct permissions
        $this->setupUser();

        // test incorrect and correct password
        $this->loginHelper('dummy', 'foo', 'http://localhost/login', 200);
        $this->loginHelper('dummy', 'dummy', 'http://localhost/team', 200);
    }

    /**
     * Test that the team overview page contains the correct data
     */
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

    /**
     * Test that it is possible to create a clorification as a team
     */
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

        // Note that we would like to click the link here but we cannot do
        // that since we have too much global state, e.g. define IS_JURY
        // constants.
    }
}
