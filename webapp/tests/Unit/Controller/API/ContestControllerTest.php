<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\DataFixtures\Test\DemoPreStartContestFixture;
use App\Entity\Contest;

class ContestControllerTest extends BaseTestCase
{
    protected ?string $apiEndpoint = 'contests';

    protected array $expectedObjects = [
        'demo' => [
            'formal_name'                => 'Demo contest',
            'penalty_time'               => 20,
            // 'start_time'                 => '2021-01-01T11:00:00+00:00',
            // 'end_time'                   => '2024-01-01T16:00:00+00:00',
            'duration'                   => '5:00:00.000',
            'scoreboard_freeze_duration' => '1:00:00.000',
            'id'                         => 'demo',
            'name'                       => 'Demo contest',
            'shortname'                  => 'demo',
            'banner'                     => null,
        ],
    ];

    protected array $expectedAbsent = ['4242', 'nonexistent'];

    protected ?string $objectClassForExternalId = Contest::class;

    /**
     * Test that a contest that is activated but not yet started is visible in the list action for a team user.
     */
    public function testListShowsActivatedButNotStartedContest(): void
    {
        $this->loadFixture(DemoPreStartContestFixture::class);

        $url = $this->helperGetEndpointURL($this->apiEndpoint);
        // Use 'demo' user which has team role
        $objects = $this->verifyApiJsonResponse('GET', $url, 200, 'demo');

        self::assertIsArray($objects);
        self::assertNotEmpty($objects, 'Contest list should not be empty');

        // Find the demo contest in the response
        $foundContest = null;
        foreach ($objects as $contest) {
            if ($contest['shortname'] === 'demo') {
                $foundContest = $contest;
                break;
            }
        }

        self::assertNotNull($foundContest, 'Demo contest should be visible after activation even before start');
        self::assertSame('Demo contest', $foundContest['formal_name']);
    }

    /**
     * Test that a contest that is activated but not yet started is visible in the single action for a team user.
     */
    public function testSingleShowsActivatedButNotStartedContest(): void
    {
        $this->loadFixture(DemoPreStartContestFixture::class);

        $contestId = $this->resolveEntityId(Contest::class, '1');
        $url = $this->helperGetEndpointURL($this->apiEndpoint, $contestId);
        // Use 'demo' user which has team role
        $contest = $this->verifyApiJsonResponse('GET', $url, 200, 'demo');

        self::assertIsArray($contest);
        self::assertSame('Demo contest', $contest['formal_name']);
        self::assertSame('demo', $contest['shortname']);
    }
}
