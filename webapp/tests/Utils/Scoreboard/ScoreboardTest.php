<?php declare(strict_types=1);

namespace App\Tests\Utils\Scoreboard;

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
    public function testScoreTiebreakerEmptyTeams()
    {
        $teamA = new Team();
        $teamB = new Team();
        $scoreA = new TeamScore($teamA);
        $scoreB = new TeamScore($teamB);

        // Always test in both directions for symmetry
        $tie = Scoreboard::scoreTieBreaker($scoreA, $scoreB);
        $this->assertEquals(0, $tie);
        $tie = Scoreboard::scoreTieBreaker($scoreB, $scoreA);
        $this->assertEquals(0, $tie);
    }

    /**
     * Test that the scoreboard tie breaker works with two teams with equal scores
     */
    public function testScoreTiebreakerEqualTeams()
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
        $this->assertEquals(0, $tie);
        $tie = Scoreboard::scoreTieBreaker($scoreB, $scoreA);
        $this->assertEquals(0, $tie);
    }

    /**
     * Test that the scoreboard tie breaker works if only one team has scores
     */
    public function testScoreTiebreakerOneTeamEmpty()
    {
        $teamA = new Team();
        $teamB = new Team();
        $scoreA = new TeamScore($teamA);
        foreach([6, 367, 2, 100] as $time) {
            $scoreA->solveTimes[] = $time;
        }
        $scoreB = new TeamScore($teamB);

        $tie = Scoreboard::scoreTieBreaker($scoreA, $scoreB);
        $this->assertEquals(1, $tie);
        $tie = Scoreboard::scoreTieBreaker($scoreB, $scoreA);
        $this->assertEquals(-1, $tie);
    }


    /**
     * Test that the scoreboard tie breaker works if both teams have the same
     * highest score
     */
    public function testScoreTiebreakerHighestEqual()
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
        $this->assertEquals(0, $tie);
        $tie = Scoreboard::scoreTieBreaker($scoreB, $scoreA);
        $this->assertEquals(0, $tie);
    }

    /**
     * Test that the scoreboard tie breaker works if scores are different
     */
    public function testScoreTiebreakerUnequal()
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
        $this->assertEquals(1, $tie);
        $tie = Scoreboard::scoreTieBreaker($scoreB, $scoreA);
        $this->assertEquals(-1, $tie);
    }
}
