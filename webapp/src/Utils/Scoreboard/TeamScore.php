<?php declare(strict_types=1);

namespace App\Utils\Scoreboard;

use App\Entity\Team;

class TeamScore
{
    public Team $team;
    public int $numPoints = 0;

    /** @var float[] */
    public array $solveTimes = [];
    public int $rank = 0;
    public int $totalTime;
    public int $totalRuntime = 0;

    public function __construct(Team $team)
    {
        $this->team      = $team;
        $this->totalTime = $team->getPenalty();
    }
}
