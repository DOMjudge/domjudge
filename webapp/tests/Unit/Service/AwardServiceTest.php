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
        // The contest will have 2 gold, 2 silver and 2 bronze medals, awarded only to category A and C
        $this->contest = (new Contest())
            ->setMedalsEnabled(true)
            ->setGoldMedals(2)
            ->setSilverMedals(1)
            ->setBronzeMedals(1);
        $categoryA = (new TeamCategory())
            ->setName('Category A')
            ->setExternalid('cat_A');
        $categoryB = (new TeamCategory())
            ->setName('Category B')
            ->setExternalid('cat_B');
        $categoryC = (new TeamCategory())
            ->setName('Category C')
            ->setExternalid('cat_C');
        $this->contest
            ->addMedalCategory($categoryA)
            ->addMedalCategory($categoryC);
        $reflectedProblem = new ReflectionClass(TeamCategory::class);
        $categoryIdProperty = $reflectedProblem->getProperty('categoryid');
        $categoryIdProperty->setAccessible(true);
        $categoryIdProperty->setValue($categoryA, 1);
        $categoryIdProperty->setValue($categoryB, 2);
        $categoryIdProperty->setValue($categoryC, 3);
        $categories = [$categoryA, $categoryB];
        // Create 9 teams, each belonging to a different category
        $teams = [];
        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'] as $teamLetter) {
            $category = $categoryC;
            if (in_array($teamLetter, ['A', 'B', 'C'])) {
                $category = $categoryA;
            }
            if (in_array($teamLetter, ['D', 'E', 'F'])) {
                $category = $categoryB;
            }
            $team = (new Team())
                ->setName('Team ' . $teamLetter)
                ->setExternalid('team_' . $teamLetter)
                ->setCategory($category)
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
        // C    | x  2  3  x
        // D    | x  x  x  12
        // E    | x  x  x  13
        // F    | x  x  x  14
        // G    | x  x  x  15
        // H    | x  x  x  x
        // I    | x  x  x  x
        //
        // This means A is the overall winner, will get a gold medal and is the winner
        // of category A. It is also first to solve problem A.
        // B is second, so it also gets a gold medal. It is also first to solve problem B and C
        // C scored the exact same as B, so it also gets the same medals
        // D is the first to solve problem D and is the winner of category B. But will not get any medal.
        // E and F will get no awards at all.
        // G is the winner of category C and will get a bronze medal.
        // The reason G doesn't get silver is that C would get silver if it was ranked differently,
        // but it is not.
        // H and I didn't solve anything, so it will not get any medals at all

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
                'B' => 2,
                'C' => 3,
            ],
            'D' => [
                'D' => 12,
            ],
            'E' => [
                'D' => 13,
            ],
            'F' => [
                'D' => 14,
            ],
            'G' => [
                'D' => 15,
            ],
        ];
        $scoreCache = [];
        foreach ($scores as $teamLabel => $scoresForTeam) {
            foreach ($scoresForTeam as $problemLabel => $minute) {
                $firstToSolve = in_array(
                    $teamLabel . $problemLabel,
                    ['AA', 'BB', 'BC', 'CB', 'CC', 'DD']
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
            'gold' => ['team_A', 'team_B', 'team_C'],
            'silver' => [],
            'bronze' => ['team_G'],
        ];
        foreach ($medals as $medal => $teams) {
            $medalAward = $this->getAward($medal . '-medal');
            if (empty($teams)) {
                static::assertNull($medalAward);
            } else {
                static::assertNotNull($medalAward);
                static::assertEquals(ucfirst($medal) . ' medal winner', $medalAward['citation']);
                static::assertEquals($teams, $medalAward['team_ids']);
            }
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
        static::assertEquals(['team_D'], $groupBWinner['team_ids']);

        $a = $this->getAwardService()->getAwards($this->contest, $this->scoreboard);
        $groupBWinner = $this->getAward('group-winner-cat_C');
        static::assertNotNull($groupBWinner);
        static::assertEquals('Winner(s) of group Category C', $groupBWinner['citation']);
        static::assertEquals(['team_G'], $groupBWinner['team_ids']);
    }

    public function testFirstToSolve(): void
    {
        $fts = [
            'A' => ['A'],
            'B' => ['B', 'C'],
            'C' => ['B', 'C'],
            'D' => ['D'],
        ];
        foreach ($fts as $problem => $teams) {
            $firstToSolve = $this->getAward('first-to-solve-problem_' . $problem);
            static::assertNotNull($firstToSolve);
            static::assertEquals('First to solve problem ' . $problem, $firstToSolve['citation']);
            $teamIds = array_map(static fn(string $team) => 'team_' . $team, $teams);
            static::assertEquals($teamIds, $firstToSolve['team_ids']);
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
        yield [1, 'gold-medal'];
        yield [2, 'gold-medal'];
        yield [3, null];
        yield [4, null];
        yield [5, null];
        yield [6, 'bronze-medal'];
        yield [7, null];
        yield [8, null];
    }
}
