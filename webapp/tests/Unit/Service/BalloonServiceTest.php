<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DataFixtures\Test\BalloonNotificationsSettingsFixture;
use App\DataFixtures\Test\ContestTimeFixture;
use App\Entity\Contest;
use App\Entity\Team;
use App\Service\BalloonService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ScoreboardService;
use App\Tests\Unit\BaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class BalloonServiceTest extends BaseTestCase
{
    #[Test]
    #[DataProvider('balloonSettingsProvider')]
    public function testBalloonNotification(int $minBalloons, bool $considerPostFreezeSubmissions, array $expectedByTeamName): void
    {
        $this->logIn();

        $container = static::getContainer();
        $balloonService = $container->get(BalloonService::class);
        $entityManager = $container->get(EntityManagerInterface::class);
        $configurationService = $container->get(ConfigurationService::class);
        $eventLog = $container->get(EventLogService::class);
        $dj = $container->get(DOMJudgeService::class);

        $this->loadFixtures([ContestTimeFixture::class, BalloonNotificationsSettingsFixture::class]);

        $contest = $entityManager->getRepository(Contest::class)->findOneBy(['shortname' => 'beforeUnfreeze']);
        $this->assertNotNull($contest);

        // Map team names from the provider to their actual IDs from the database
        $expectedResults = [];
        foreach ($expectedByTeamName as $teamName => $balloons) {
            $team = $entityManager->getRepository(Team::class)->findOneBy(['name' => $teamName]);
            $this->assertNotNull($team, "Team $teamName not found in fixtures");
            $expectedResults[$team->getTeamid()] = $balloons;
        }

        // Apply configuration for this specific test case
        $configurationService->saveChanges([
            'minimum_number_of_balloons' => (string)$minBalloons,
            'any_balloon_postfreeze' => (string)$considerPostFreezeSubmissions,
        ], $eventLog, $dj);

        $results = $balloonService->collectBalloonTable($contest);
        $seenTeams = [];

        foreach ($results as ["data" => $data]) {
            $team = $data["team"];
            $teamId = $team->getTeamId();

            if (in_array($teamId, $seenTeams)) {
                continue;
            }
            $seenTeams[] = $teamId;

            $this->assertArrayHasKey($teamId, $expectedResults, "Unexpected team " . $team->getName() . " in results");

            $actualBalloons = array_keys($data["total"]);
            $this->assertEqualsCanonicalizing(
                $expectedResults[$teamId], $actualBalloons,
                "Mismatch for {$team->getName()} (Min: $minBalloons, PostFreeze: " . ($considerPostFreezeSubmissions ? 'true' : 'false') . ")"
            );

            unset($expectedResults[$teamId]);
        }

        $this->assertCount(0, $expectedResults, "Not all expected teams were found in the results table");
    }

    public static function balloonSettingsProvider(): array
    {
        $everyoneSolvedEverything = [
            'Balloon team 1' => ['BA', 'BB', 'BC', 'BD'],
            'Balloon team 2' => ['BA', 'BB', 'BC', 'BD'],
            'Balloon team 3' => ['BA', 'BB', 'BC', 'BD'],
            'Balloon team 4' => ['BA', 'BB', 'BC', 'BD'],
        ];

        return [
            'At most 0 balloons post-freeze but don\'t hand out post-freeze balloons' => [0, false, [
                'Balloon team 1' => ['BA', 'BB', 'BC'],
                'Balloon team 4' => ['BA', 'BB'],
            ]],

            'At most 1 balloons post-freeze but don\'t hand out post-freeze balloons' => [1, false, [
                'Balloon team 1' => ['BA', 'BB', 'BC'],
                'Balloon team 2' => ['BA'],
                'Balloon team 3' => ['BB'],
                'Balloon team 4' => ['BA', 'BB'],
            ]],

            'At most 2 balloons post-freeze but don\'t hand out post-freeze balloons' => [2, false, [
                'Balloon team 1' => ['BA', 'BB', 'BC'],
                'Balloon team 2' => ['BA', 'BB'],
                'Balloon team 3' => ['BB', 'BC'],
                'Balloon team 4' => ['BA', 'BB'],
            ]],

            'At most 3 balloons post-freeze but don\'t hand out post-freeze balloons' => [3, false, [
                'Balloon team 1' => ['BA', 'BB', 'BC'],
                'Balloon team 2' => ['BA', 'BB', 'BC'],
                'Balloon team 3' => ['BB', 'BC', 'BA'],
                'Balloon team 4' => ['BA', 'BB'],
            ]],

            'At most 4 balloons post-freeze but don\'t hand out post-freeze balloons' => [4, false, [
                'Balloon team 1' => ['BA', 'BB', 'BC'],
                'Balloon team 2' => ['BA', 'BB', 'BC'],
                'Balloon team 3' => ['BA', 'BB', 'BC'],
                'Balloon team 4' => ['BA', 'BB'],
            ]],

            'At most 5 balloons post-freeze but don\'t hand out post-freeze balloons' => [5, false, [
                'Balloon team 1' => ['BA', 'BB', 'BC'],
                'Balloon team 2' => ['BA', 'BB', 'BC'],
                'Balloon team 3' => ['BA', 'BB', 'BC'],
                'Balloon team 4' => ['BA', 'BB'],
            ]],

            // When post-freeze balloons enabled, everyone gets all balloons regardless of how many balloons will be handed out post-freeze.
            'At most 0, including post-freeze submissions' => [0, true, $everyoneSolvedEverything],
            'At most 1, including post-freeze submissions' => [1, true, $everyoneSolvedEverything],
            'At most 2, including post-freeze submissions' => [2, true, $everyoneSolvedEverything],
            'At most 3, including post-freeze submissions' => [3, true, $everyoneSolvedEverything],
            'At most 4, including post-freeze submissions' => [4, true, $everyoneSolvedEverything],
            'At most 5, including post-freeze submissions' => [5, true, $everyoneSolvedEverything],
        ];
    }
}
