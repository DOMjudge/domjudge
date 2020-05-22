<?php declare(strict_types=1);

namespace Tests;

class JuryClarificationsTest extends BaseTest
{
    protected static $roles = ['jury'];

    public function testJuryRedirectToLogin()
    {
        $this->client->request('GET', '/jury');

        $response = $this->client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(302, $response->getStatusCode(), $message);
        $this->assertEquals('http://localhost/login', $response->getTargetUrl(), $message);
    }

    public function testLogin()
    {
        // Make sure the suer has the correct permissions
        $this->setupUser();

        // test incorrect and correct password
        $this->loginHelper('dummy', 'foo', 'http://localhost/login', 200);
        $this->loginHelper('dummy', 'dummy', 'http://localhost/jury', 200);
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
