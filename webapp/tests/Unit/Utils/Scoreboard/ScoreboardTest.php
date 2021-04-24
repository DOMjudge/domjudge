<?php declare(strict_types=1);

namespace App\Tests\Unit\Utils\Scoreboard;

use App\Entity\Team;
use App\Utils\Scoreboard\Scoreboard;
use App\Utils\Scoreboard\TeamScore;
use PHPUnit\Framework\TestCase;

class ScoreboardTest extends TestCase
{
    /**
     * Test that the scoreboard tie breaker works with two teams without
     * any scores
     */
    public function testScoreTiebreakerEmptyTeams() : void
    {
        $teamA = new Team();
        $teamB = new Team();
        $scoreA = new TeamScore($teamA);
        $scoreB = new TeamScore($teamB);

        // Always test in both directions for symmetry
        $tie = Scoreboard::scoreTieBreaker($scoreA, $scoreB);
        self::assertEquals(0, $tie);
        $tie = Scoreboard::scoreTieBreaker($scoreB, $scoreA);
        self::assertEquals(0, $tie);
    }

    /**
     * Test that the scoreboard tie breaker works with two teams with equal scores
     */
    public function testScoreTiebreakerEqualTeams() : void
    {
        $teamA = new Team();
        $teamB = new Team();
        $scoreA = new TeamScore($teamA);
        foreach([6, 367, 2, 100] as $time) {
            $scoreA->solveTimes[] = $time;
        }
        $scoreB = new TeamScore($teamB);
        foreach([100, 6, 2, 367] as $time) {
            $scoreB->solveTimes[] = $time;
        }

        $tie = Scoreboard::scoreTieBreaker($scoreA, $scoreB);
        self::assertEquals(0, $tie);
        $tie = Scoreboard::scoreTieBreaker($scoreB, $scoreA);
        self::assertEquals(0, $tie);
    }

    /**
     * Test that the scoreboard tie breaker works if only one team has scores
     */
    public function testScoreTiebreakerOneTeamEmpty() : void
    {
        $teamA = new Team();
        $teamB = new Team();
        $scoreA = new TeamScore($teamA);
        foreach([6, 367, 2, 100] as $time) {
            $scoreA->solveTimes[] = $time;
        }
        $scoreB = new TeamScore($teamB);

        $tie = Scoreboard::scoreTieBreaker($scoreA, $scoreB);
        self::assertEquals(1, $tie);
        $tie = Scoreboard::scoreTieBreaker($scoreB, $scoreA);
        self::assertEquals(-1, $tie);
    }


    /**
     * Test that the scoreboard tie breaker works if both teams have the same
     * highest score
     */
    public function testScoreTiebreakerHighestEqual() : void
    {
        $teamA = new Team();
        $teamB = new Team();
        $scoreA = new TeamScore($teamA);
        foreach([6, 367, 2, 100] as $time) {
            $scoreA->solveTimes[] = $time;
        }
        $scoreB = new TeamScore($teamB);
        foreach([23, 150, 367] as $time) {
            $scoreB->solveTimes[] = $time;
        }

        $tie = Scoreboard::scoreTieBreaker($scoreA, $scoreB);
        self::assertEquals(0, $tie);
        $tie = Scoreboard::scoreTieBreaker($scoreB, $scoreA);
        self::assertEquals(0, $tie);
    }

    /**
     * Test that the scoreboard tie breaker works if scores are different
     */
    public function testScoreTiebreakerUnequal() : void
    {
        $teamA = new Team();
        $teamB = new Team();
        $scoreA = new TeamScore($teamA);
        foreach([6, 367, 2, 100] as $time) {
            $scoreA->solveTimes[] = $time;
        }
        $scoreB = new TeamScore($teamB);
        foreach([23, 150, 2] as $time) {
            $scoreB->solveTimes[] = $time;
        }

        $tie = Scoreboard::scoreTieBreaker($scoreA, $scoreB);
        self::assertEquals(1, $tie);
        $tie = Scoreboard::scoreTieBreaker($scoreB, $scoreA);
        self::assertEquals(-1, $tie);
    }
}
