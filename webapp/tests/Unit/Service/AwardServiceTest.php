<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Problem;
use App\Entity\ScoreCache;
use App\Entity\Team;
use App\Entity\TeamCategory;
use App\Service\AwardService;
use App\Service\EventLogService;
use App\Utils\Scoreboard\Scoreboard;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AwardServiceTest extends KernelTestCase
{
    protected Contest $contest;
    protected Scoreboard $scoreboard;

    protected function setUp(): void
    {
        // The contest will have 1 gold, 1 silver and 2 bronze medals
        $this->contest = (new Contest())
            ->setMedalsEnabled(true)
            ->setGoldMedals(1)
            ->setSilverMedals(1)
            ->setBronzeMedals(1);
        $categoryA = (new TeamCategory())
            ->setName('Category A')
            ->setExternalid('cat_A');
        $categoryB = (new TeamCategory())
            ->setName('Category B')
            ->setExternalid('cat_B');
        $this->contest
            ->addMedalCategory($categoryA)
            ->addMedalCategory($categoryB);
        $reflectedProblem = new ReflectionClass(TeamCategory::class);
        $teamIdProperty = $reflectedProblem->getProperty('categoryid');
        $teamIdProperty->setAccessible(true);
        $teamIdProperty->setValue($categoryA, 1);
        $teamIdProperty->setValue($categoryB, 2);
        $categories = [$categoryA, $categoryB];
        // Create 4 teams, each belonging to a category
        $teams = [];
        foreach (['A', 'B', 'C', 'D'] as $teamLetter) {
            $team = (new Team())
                ->setName('Team ' . $teamLetter)
                ->setExternalid('team_' . $teamLetter)
                ->setCategory(in_array($teamLetter, ['A', 'B']) ? $categoryA : $categoryB)
                ->setAffiliation(); // No affiliation needed
            $reflectedProblem = new ReflectionClass(Team::class);
            $teamIdProperty = $reflectedProblem->getProperty('teamid');
            $teamIdProperty->setAccessible(true);
            $teamIdProperty->setValue($team, count($teams));
            $teams[] = $team;
        }
        // Create 4 problems
        $problems = [];
        foreach (['A', 'B', 'C', 'D'] as $problemLabel) {
            $problem = (new ContestProblem())
                ->setProblem(
                    (new Problem())
                        ->setName('Problem ' . $problemLabel)
                        ->setExternalid('problem_' . $problemLabel)
                )
                ->setContest($this->contest)
                ->setShortname($problemLabel);
            $reflectedProblem = new ReflectionClass(Problem::class);
            $probIdProperty = $reflectedProblem->getProperty('probid');
            $probIdProperty->setAccessible(true);
            $probIdProperty->setValue($problem->getProblem(), count($problems));
            $problems[] = $problem;
        }

        // Now generate some scores. We will create the following solves:
        // (a numbers is the solve time or an x means not solved)
        //
        // Team | A  B  C  D
        // -----+-----------
        // A    | 1  5  10 20
        // B    | x  2  3  x
        // C    | x  x  x  4
        // D    | x  x  x  x
        //
        // THis means A is the overall winner, will get a gold medal and is the winner
        // of category A. It is also first to solve problem A.
        // B is second, so it gets a silver medal. It is also first to solve problem B and C
        // C is the first to solve problem D, gets a bronze medal and is winner of category B.
        // D didn't solve anything, so it will not get any medals at all

        $minute = 60;
        // Indexed first by team, then by problem
        $scores = [
            'A' => [
                'A' => 1,
                'B' => 5,
                'C' => 10,
                'D' => 20,
            ],
            'B' => [
                'B' => 2,
                'C' => 3,
            ],
            'C' => [
                'D' => 4,
            ],
        ];
        $scoreCache = [];
        foreach ($scores as $teamLabel => $scoresForTeam) {
            foreach ($scoresForTeam as $problemLabel => $minute) {
                $firstToSolve = in_array(
                    $teamLabel . $problemLabel,
                    ['AA', 'BB', 'BC', 'CD']
                );
                $scoreCache[] = (new ScoreCache())
                    ->setContest($this->contest)
                    ->setTeam($teams[ord($teamLabel) - ord('A')])
                    ->setProblem($problems[ord($problemLabel) - ord('A')]->getProblem())
                    ->setSubmissionsRestricted(1)
                    ->setSolvetimeRestricted(60 * $minute)
                    ->setIsCorrectRestricted(true)
                    ->setIsFirstToSolve($firstToSolve);
            }
        }

        $this->scoreboard = new Scoreboard(
            $this->contest,
            $teams,
            $categories,
            $problems,
            $scoreCache,
            $this->contest->getFreezeData(),
            true,
            20,
            false
        );
    }

    protected function getAwardService(): AwardService
    {
        // Always use external IDs so we also test that those are used in the correct spot
        $eventLogService = $this->createMock(EventLogService::class);
        $eventLogService->expects(self::any())
            ->method('apiIdFieldForEntity')
            ->willReturn('externalId');
        return new AwardService($eventLogService);
    }

    protected function getAward(string $label): ?array
    {
        return $this->getAwardService()->getAwards($this->contest, $this->scoreboard, $label);
    }

    public function testWinner(): void
    {
        $winner = $this->getAward('winner');
        static::assertNotNull($winner);
        static::assertEquals('Contest winner', $winner['citation']);
        static::assertEquals(['team_A'], $winner['team_ids']);
    }

    public function testMedals(): void
    {
        $medals = [
            'gold' => 'team_A',
            'silver' => 'team_B',
            'bronze' => 'team_C',
        ];
        foreach ($medals as $medal => $team) {
            $medalAward = $this->getAward($medal . '-medal');
            static::assertNotNull($medalAward);
            static::assertEquals(ucfirst($medal) . ' medal winner', $medalAward['citation']);
            static::assertEquals([$team], $medalAward['team_ids']);
        }
    }

    public function testGroupWinners(): void
    {
        $groupAWinner = $this->getAward('group-winner-cat_A');
        static::assertNotNull($groupAWinner);
        static::assertEquals('Winner(s) of group Category A', $groupAWinner['citation']);
        static::assertEquals(['team_A'], $groupAWinner['team_ids']);

        $a = $this->getAwardService()->getAwards($this->contest, $this->scoreboard);
        $groupBWinner = $this->getAward('group-winner-cat_B');
        static::assertNotNull($groupBWinner);
        static::assertEquals('Winner(s) of group Category B', $groupBWinner['citation']);
        static::assertEquals(['team_C'], $groupBWinner['team_ids']);
    }

    public function testFirstToSolve(): void
    {
        $fts = [
            'A' => 'A',
            'B' => 'B',
            'C' => 'B',
            'D' => 'C',
        ];
        foreach ($fts as $problem => $team) {
            $firstToSolve = $this->getAward('first-to-solve-problem_' . $problem);
            static::assertNotNull($firstToSolve);
            static::assertEquals('First to solve problem ' . $problem, $firstToSolve['citation']);
            static::assertEquals(['team_' . $team], $firstToSolve['team_ids']);
        }
    }

    /**
     * @dataProvider provideMedalType
     */
    public function testMedalType(int $teamIndex, ?string $expectedMedalType)
    {
        $awardsService = $this->getAwardService();
        $team = $this->scoreboard->getTeams()[$teamIndex];
        static::assertEquals($expectedMedalType, $awardsService->medalType($team, $this->contest, $this->scoreboard));
    }

    public function provideMedalType(): \Generator
    {
        yield [0, 'gold-medal'];
        yield [1, 'silver-medal'];
        yield [2, 'bronze-medal'];
        yield [3, null];
    }
}
