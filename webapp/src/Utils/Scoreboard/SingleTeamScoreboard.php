<?php declare(strict_types=1);

namespace App\Utils\Scoreboard;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\RankCache;
use App\Entity\ScoreCache;
use App\Entity\Team;
use App\Utils\FreezeData;
use App\Utils\Utils;

/**
 * This class represents the scoreboard for a single team. It exists because
 * we can do some smart things to speed up calculating data for a single team.
 */
class SingleTeamScoreboard extends Scoreboard
{
    protected readonly bool $showRestrictedFts;

    /**
     * @param ContestProblem[] $problems
     * @param ScoreCache[]     $scoreCache
     */
    public function __construct(
        Contest $contest,
        protected readonly Team $team,
        protected readonly int $teamRank,
        array $problems,
        protected readonly ?RankCache $rankCache,
        array $scoreCache,
        FreezeData $freezeData,
        bool $showFtsInFreeze,
        int $penaltyTime,
        bool $scoreIsInSeconds
    ) {
        $this->showRestrictedFts = $showFtsInFreeze || $freezeData->showFinal();
        parent::__construct($contest, [$team->getTeamid() => $team], [], $problems, $scoreCache, $freezeData, true,
            $penaltyTime, $scoreIsInSeconds);
    }

    protected function calculateScoreboard(): void
    {
        $teamScore = $this->scores[$this->team->getTeamid()];
        if ($this->rankCache !== null) {
            $teamScore->numPoints += $this->rankCache->getPointsRestricted();
            $teamScore->totalTime += $this->rankCache->getTotaltimeRestricted();
            $teamScore->totalRuntime += $this->rankCache->getTotalruntimeRestricted();
        }
        $teamScore->rank = $this->teamRank;

        // Loop all info the scoreboard cache and put it in our own data structure.
        $this->matrix = [];
        foreach ($this->scoreCache as $scoreRow) {
            // Skip this row if the problem is not known by us.
            if (!array_key_exists($scoreRow->getProblem()->getProbid(), $this->problems)) {
                continue;
            }

            $penalty = Utils::calcPenaltyTime(
                $scoreRow->getIsCorrect($this->restricted), $scoreRow->getSubmissions($this->restricted),
                $this->penaltyTime, $this->scoreIsInSeconds
            );

            $this->matrix[$scoreRow->getTeam()->getTeamid()][$scoreRow->getProblem()->getProbid()] = new ScoreboardMatrixItem(
                isCorrect: $scoreRow->getIsCorrect($this->restricted),
                isFirst: $scoreRow->getIsCorrect($this->showRestrictedFts) && $scoreRow->getIsFirstToSolve(),
                numSubmissions: $scoreRow->getSubmissions($this->restricted),
                numSubmissionsPending: $scoreRow->getPending($this->restricted),
                time: $scoreRow->getSolveTime($this->restricted),
                penaltyTime: $penalty,
                runtime: $scoreRow->getRuntime($this->restricted),
                numSubmissionsInFreeze: $scoreRow->getPending(false),
            );
        }

        // Fill in empty places in the matrix.
        foreach ($this->problems as $contestProblem) {
            // Provide default scores when nothing submitted for this team + problem yet.
            $teamId    = $this->team->getTeamid();
            $problemId = $contestProblem->getProbid();
            if (!isset($this->matrix[$teamId][$problemId])) {
                $this->matrix[$teamId][$problemId] = new ScoreboardMatrixItem(
                    isCorrect: false,
                    isFirst: false,
                    numSubmissions: 0,
                    numSubmissionsPending: 0,
                    time: 0,
                    penaltyTime: 0,
                    runtime: 0);
            }
        }
    }
}
