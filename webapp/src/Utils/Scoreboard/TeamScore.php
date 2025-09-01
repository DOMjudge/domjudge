<?php declare(strict_types=1);

namespace App\Utils\Scoreboard;

use App\Entity\RankCache;
use App\Entity\Team;

class TeamScore
{
    public int $numPoints = 0;

    public int $rank = 0;
    public int $totalTime;
    public int $totalRuntime = 0;
    public string|float|null $score = "0";

    public function __construct(public Team $team, public ?RankCache $rankCache, bool $restricted)
    {
        $this->totalTime = $team->getPenalty();
        if ($this->rankCache) {
            if ($restricted) {
                $this->numPoints = $rankCache->getPointsRestricted();
                $this->totalTime += $rankCache->getTotaltimeRestricted();
                $this->totalRuntime = $rankCache->getTotalruntimeRestricted();
                $this->score = $rankCache->getScoreRestricted();
            } else {
                $this->numPoints = $rankCache->getPointsPublic();
                $this->totalTime += $rankCache->getTotaltimePublic();
                $this->totalRuntime = $rankCache->getTotalruntimePublic();
                $this->score = $rankCache->getScorePublic();
            }
        }
    }

    public function getSortKey(bool $restricted): string
    {
        if ($this->rankCache === null) {
            // Sorts teams without a rank last.
            return '.';
        }
        return $restricted ? $this->rankCache->getSortKeyRestricted() : $this->rankCache->getSortKeyPublic();
    }
}
