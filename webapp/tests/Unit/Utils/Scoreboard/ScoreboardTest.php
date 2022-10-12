<?php declare(strict_types=1);

namespace App\Tests\Unit\Utils\Scoreboard;

use App\DataFixtures\Test\ContestTimeFixture;
use App\Entity\Contest;
use App\Entity\Team;
use App\Tests\Unit\BaseTest as BaseBaseTest;
use App\Tests\Unit\Utils\FreezeDataTest;
use App\Utils\FreezeData;
use App\Utils\Scoreboard\Scoreboard;
use App\Utils\Scoreboard\TeamScore;
use Generator;

class ScoreboardTest extends BaseBaseTest
{
    /**
     * Test that the scoreboard tiebreaker works with two teams without any scores.
     */
    public function testScoreTiebreakerEmptyTeams(): void
    {
        $teamA = new Team();
        $teamB = new Team();
        $scoreA = new TeamScore($teamA);
        $scoreB = new TeamScore($teamB);

        // Always test in both directions for symmetry.
        $tie = Scoreboard::scoreTieBreaker($scoreA, $scoreB);
        self::assertEquals(0, $tie);
        $tie = Scoreboard::scoreTieBreaker($scoreB, $scoreA);
        self::assertEquals(0, $tie);
    }

    /**
     * Test that the scoreboard tiebreaker works with two teams with equal scores.
     */
    public function testScoreTiebreakerEqualTeams(): void
    {
        $teamA = new Team();
        $teamB = new Team();
        $scoreA = new TeamScore($teamA);
        foreach ([6, 367, 2, 100] as $time) {
            $scoreA->solveTimes[] = $time;
        }
        $scoreB = new TeamScore($teamB);
        foreach ([100, 6, 2, 367] as $time) {
            $scoreB->solveTimes[] = $time;
        }

        $tie = Scoreboard::scoreTieBreaker($scoreA, $scoreB);
        self::assertEquals(0, $tie);
        $tie = Scoreboard::scoreTieBreaker($scoreB, $scoreA);
        self::assertEquals(0, $tie);
    }

    /**
     * Test that the scoreboard tiebreaker works if only one team has scores.
     */
    public function testScoreTiebreakerOneTeamEmpty(): void
    {
        $teamA = new Team();
        $teamB = new Team();
        $scoreA = new TeamScore($teamA);
        foreach ([6, 367, 2, 100] as $time) {
            $scoreA->solveTimes[] = $time;
        }
        $scoreB = new TeamScore($teamB);

        $tie = Scoreboard::scoreTieBreaker($scoreA, $scoreB);
        self::assertEquals(1, $tie);
        $tie = Scoreboard::scoreTieBreaker($scoreB, $scoreA);
        self::assertEquals(-1, $tie);
    }


    /**
     * Test that the scoreboard tiebreaker works if both teams have the same highest score.
     */
    public function testScoreTiebreakerHighestEqual(): void
    {
        $teamA = new Team();
        $teamB = new Team();
        $scoreA = new TeamScore($teamA);
        foreach ([6, 367, 2, 100] as $time) {
            $scoreA->solveTimes[] = $time;
        }
        $scoreB = new TeamScore($teamB);
        foreach ([23, 150, 367] as $time) {
            $scoreB->solveTimes[] = $time;
        }

        $tie = Scoreboard::scoreTieBreaker($scoreA, $scoreB);
        self::assertEquals(0, $tie);
        $tie = Scoreboard::scoreTieBreaker($scoreB, $scoreA);
        self::assertEquals(0, $tie);
    }

    /**
     * Test that the scoreboard tiebreaker works if scores are different.
     */
    public function testScoreTiebreakerUnequal(): void
    {
        $teamA = new Team();
        $teamB = new Team();
        $scoreA = new TeamScore($teamA);
        foreach ([6, 367, 2, 100] as $time) {
            $scoreA->solveTimes[] = $time;
        }
        $scoreB = new TeamScore($teamB);
        foreach ([23, 150, 2] as $time) {
            $scoreB->solveTimes[] = $time;
        }

        $tie = Scoreboard::scoreTieBreaker($scoreA, $scoreB);
        self::assertEquals(1, $tie);
        $tie = Scoreboard::scoreTieBreaker($scoreB, $scoreA);
        self::assertEquals(-1, $tie);
    }

    /**
     * @dataProvider provideFreezeDataProgress
     */
    public function testScoreboardProgress(
        string $reference,
        int $progress,
        bool $_1,
        bool $_2,
        bool $_3,
        bool $_4,
        bool $_5,
        bool $_6
    ): void {
        $this->loadFixture(ContestTimeFixture::class);
        $em = self::getContainer()->get('doctrine')->getManager();
        $contest = $em->getRepository(Contest::class)->findOneBy(['name' => $reference]);
        $freezeData = new FreezeData($contest);
        $scoreBoard = new Scoreboard($contest, [], [], [], [], $freezeData, false, 0, true);
        self::assertEquals($scoreBoard->getProgress(), $progress);
    }

    public function provideFreezeDataProgress(): Generator
    {
        $class = new FreezeDataTest();
        return $class->provideContestProgress();
    }
}
