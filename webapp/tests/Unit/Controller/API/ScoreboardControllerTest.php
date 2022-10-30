<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\DataFixtures\Test\ExtendDemoPracticeSessionTimeFixture;
use App\DataFixtures\Test\RemoveTeamFromDemoUserFixture;
use App\DataFixtures\Test\SampleEventsFixture;
use App\Entity\Contest;
use Generator;

class ScoreboardControllerTest extends BaseTest
{
    protected static array $fixtures = [SampleEventsFixture::class];

    /**
     * Test that the given user has the correct access to the scoreboard for the given contest.
     *
     * @dataProvider provideScoreboardAccess
     */
    public function testScoreboardAccess(?string $user, int $contestId, bool $removeTeamFromDemoUser, bool $expectedAllowedAccess): void
    {
        if ($removeTeamFromDemoUser) {
            $this->loadFixture(RemoveTeamFromDemoUserFixture::class);
        }
        $contestId = $this->resolveEntityId(Contest::class, (string)$contestId);
        $url = "/contests/$contestId/scoreboard";
        $scoreboard = $this->verifyApiJsonResponse('GET', $url, $expectedAllowedAccess ? 200 : 404, $user);
        self::assertNotEmpty($scoreboard);
    }

    public function provideScoreboardAccess(): Generator
    {
        // Contest 1 is a public contest, everyone should have access.
        yield [null, 1, false, true];
        yield ['demo', 1, false, true];
        yield ['demo', 1, true, true];
        yield ['admin', 1, false, true];

        // TODO: Re-add this later.
        // Contest 1 is not public, but the team demo belongs to was granted access explicitly.
        // yield [null, 1, false, false];
        // yield ['demo', 1, false, true];
        // yield ['demo', 1, true, false]; // If we remove the team from the demo user, it should not be able to access the contest anymore.
        // yield ['admin', 1, false, true];
    }
}
