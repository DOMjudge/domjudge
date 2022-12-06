<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\DataFixtures\Test\SampleSubmissionsThreeTriesCorrectFixture;
use App\Entity\Contest;
use App\Service\ScoreboardService;
use Doctrine\ORM\EntityManagerInterface;

class AwardsControllerTest extends BaseTest
{
    protected ?string $apiEndpoint = 'awards';

    protected static string $skipMessageIDs = "Filtering on IDs not implemented in this endpoint.";

    // We add a submission since we are not handing out any awards without it
    protected static array $fixtures = [SampleSubmissionsThreeTriesCorrectFixture::class];

    /**
     * In the default test setup there are no judgings yet and one team.
     * This means that there's only the winner/gold award.
     */
    protected array $expectedObjects = [
        'winner' => ["id" => "winner", "citation" => "Contest winner", "team_ids" => [2]],
    ];

    protected array $expectedAbsent = ['bronze-medal', 'first-to-solve'];

    protected function setUp(): void
    {
        parent::setUp();

        // We need to refresh the scoreboard cache since we added a submission
        /** @var EntityManagerInterface $manager */
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var Contest $contest */
        $contest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        /** @var ScoreboardService $scoreboardService */
        $scoreboardService = static::getContainer()->get(ScoreboardService::class);
        $scoreboardService->refreshCache($contest);
    }

    public function testAccessForBothAnonymousAndAuthenticated(): void
    {
        $url = $this->helperGetEndpointURL($this->apiEndpoint);
        $this->verifyApiJsonResponse('GET', $url, 200);
        foreach (['admin','demo'] as $user) {
            $this->verifyApiJsonResponse('GET', $url, 200, $user);
        }
    }

    public function testListWithIds(): void
    {
        static::markTestSkipped(static::$skipMessageIDs);
    }

    public function testListWithIdsNotArray(): void
    {
        static::markTestSkipped(static::$skipMessageIDs);
    }

    public function testListWithAbsentIds(): void
    {
        static::markTestSkipped(static::$skipMessageIDs);
    }
}
