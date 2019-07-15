<?php declare(strict_types=1);

namespace App\Utils\Scoreboard;

use App\Entity\Team;

class TeamScore
{
    /**
     * @var Team
     */
    protected $team;

    /**
     * @var int
     */
    protected $numberOfPoints = 0;

    /**
     * @var float[]
     */
    protected $solveTimes = [];

    /**
     * @var int
     */
    protected $rank = 0;

    /**
     * @var int
     */
    protected $totalTime;

    /**
     * TeamScore constructor.
     * @param Team $team
     */
    public function __construct(Team $team)
    {
        $this->team      = $team;
        $this->totalTime = $team->getPenalty();
    }

    /**
     * @return Team
     */
    public function getTeam(): Team
    {
        return $this->team;
    }

    /**
     * @return int
     */
    public function getNumberOfPoints(): int
    {
        return $this->numberOfPoints;
    }

    /**
     * @param int $numberOfPoints
     */
    public function addNumberOfPoints(int $numberOfPoints)
    {
        $this->numberOfPoints += $numberOfPoints;
    }

    /**
     * @return float[]
     */
    public function getSolveTimes(): array
    {
        return $this->solveTimes;
    }

    /**
     * @param float $solveTime
     */
    public function addSolveTime(float $solveTime)
    {
        $this->solveTimes[] = $solveTime;
    }

    /**
     * @return int
     */
    public function getRank(): int
    {
        return $this->rank;
    }

    /**
     * @param int $rank
     */
    public function setRank(int $rank)
    {
        $this->rank = $rank;
    }

    /**
     * @return int
     */
    public function getTotalTime(): int
    {
        return $this->totalTime;
    }

    /**
     * @param int $totalTime
     */
    public function addTotalTime(int $totalTime)
    {
        $this->totalTime += $totalTime;
    }
}
