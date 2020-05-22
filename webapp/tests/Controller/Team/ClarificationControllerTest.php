<?php declare(strict_types=1);

namespace App\Tests\Controller\Team;

use App\Controller\Team\ClarificationController;
use App\Tests\BaseTest;

class ClarificationControllerTest extends BaseTest
{
    protected static $roles = ['team'];

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

        $this->client->click($link);

        $this->client->submitForm('Send', [
            'team_clarification[subject]' => '2-3',
            'team_clarification[message]' => "I don't understand this problem",
        ]);

        $crawler = $this->client->followRedirect();

        $this->assertEquals('http://localhost/team', $crawler->getUri());

        // Now check if we actually have this clarification
        $this->assertSelectorExists('html:contains("problem boolfind")');
        $this->assertSelectorExists('html:contains("I don\'t understand this problem")');
    }
}
