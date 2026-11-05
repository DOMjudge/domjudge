<?php declare(strict_types=1);

namespace App\Tests\Unit\Utils\Scoreboard;

use App\Entity\RankCache;
use App\Entity\Team;
use App\Utils\Scoreboard\TeamScore;
use PHPUnit\Framework\TestCase;

class TeamScoreTest extends TestCase
{
    public function testPenaltyIsCountedOnceWithRankCache(): void
    {
        $team = (new Team())->setPenalty(30);
        // ScoreboardService::updateRankCache starts the total time from the team penalty.
        $rankCache = (new RankCache())->setTotaltimePublic(30)->setTotaltimeRestricted(50);

        self::assertSame(30, (new TeamScore($team, $rankCache, false))->totalTime);
        self::assertSame(50, (new TeamScore($team, $rankCache, true))->totalTime);
    }

    public function testPenaltyIsUsedWithoutRankCache(): void
    {
        $team = (new Team())->setPenalty(30);

        self::assertSame(30, (new TeamScore($team, null, false))->totalTime);
    }
}
