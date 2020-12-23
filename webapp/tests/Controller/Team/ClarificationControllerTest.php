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
        $this->verifyPageResponse('GET', '/team', 200);

        $link = $this->verifyLink('request clarification', 'http://localhost/team/clarifications/add');
        $this->client->click($link);

        $this->client->submitForm('Send', [
            'team_clarification[subject]' => '2-3',
            'team_clarification[message]' => "I don't understand this problem",
        ]);

        $this->verifyRedirect('http://localhost/team');

        // Now check if we actually have this clarification
        $this->assertSelectorExists('html:contains("problem boolfind")');
        $this->assertSelectorExists('html:contains("I don\'t understand this problem")');
    }
}
