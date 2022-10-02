<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Team;

use App\Tests\Unit\BaseTest;

class ClarificationControllerTest extends BaseTest
{
    protected array $roles = ['team'];

    public function testClarificationRequest(): void
    {
        $this->verifyPageResponse('GET', '/team', 200);

        $link = $this->verifyLinkToURL('request clarification', 'http://localhost/team/clarifications/add');
        $this->client->click($link);

        $this->client->submitForm('Send', [
            'team_clarification[subject]' => '1-3',
            'team_clarification[message]' => "I don't understand this problem",
        ]);

        $this->verifyRedirectToURL('http://localhost/team');

        // Now check if we actually have this clarification.
        self::assertSelectorExists('html:contains("problem C")');
        self::assertSelectorExists('html:contains("I don\'t understand this problem")');
    }
}
