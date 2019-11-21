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
 * Class SingleTeamScoreboard
 *
 * This class represents the scoreboard for a single team. It exists because
 * we can do some smart things to speed up calculating data for a single team.
 *
 * @package App\Utils\Scoreboard
 */
class SingleTeamScoreboard extends Scoreboard
{
    /**
     * @var Team
     */
    protected $team;

    /**
     * @var int
     */
    protected $teamRank;

    /**
     * @var RankCache|null
     */
    protected $rankCache;

    /**
     * @var bool
     */
    protected $showRestrictedFts;

    /**
     * SingleTeamScoreboard constructor.
     * @param Contest          $contest
     * @param Team             $team
     * @param int              $teamRank
     * @param ContestProblem[] $problems
     * @param RankCache|null   $rankCache
     * @param ScoreCache[]     $scoreCache
     * @param FreezeData       $freezeData
     * @param bool             $showFtsInFreeze
     * @param int              $penaltyTime
     * @param bool             $scoreIsInSeconds
     */
    public function __construct(
        Contest $contest,
        Team $team,
        int $teamRank,
        array $problems,
        $rankCache,
        array $scoreCache,
        FreezeData $freezeData,
        bool $showFtsInFreeze,
        int $penaltyTime,
        bool $scoreIsInSeconds
    ) {
        $this->team              = $team;
        $this->teamRank          = $teamRank;
        $this->rankCache         = $rankCache;
        $this->showRestrictedFts = $showFtsInFreeze || $freezeData->showFinal();
        parent::__construct($contest, [$team->getTeamid() => $team], [], $problems, $scoreCache, $freezeData, true,
            $penaltyTime, $scoreIsInSeconds);
    }

    /**
     * @inheritdoc
     */
    protected function calculateScoreboard()
    {
        $teamScore = $this->scores[$this->team->getTeamid()];
        if ($this->rankCache !== null) {
            $teamScore->addNumberOfPoints($this->rankCache->getPointsRestricted());
            $teamScore->addTotalTime($this->rankCache->getTotaltimeRestricted());
        }
        $teamScore->setRank($this->teamRank);

        // Loop all info the scoreboard cache and put it in our own datastructure
        $this->matrix = [];
        foreach ($this->scoreCache as $scoreRow) {
            // Skip this row if the problem is not known by us
            if (!array_key_exists($scoreRow->getProblem()->getProbid(), $this->problems)) {
                continue;
            }

            $penalty = Utils::calcPenaltyTime(
                $scoreRow->getIsCorrect($this->restricted), $scoreRow->getSubmissions($this->restricted),
                $this->penaltyTime, $this->scoreIsInSeconds
            );

            $this->matrix[$scoreRow->getTeam()->getTeamid()][$scoreRow->getProblem()->getProbid()] = new ScoreboardMatrixItem(
                $scoreRow->getIsCorrect($this->restricted),
                $scoreRow->getIsCorrect($this->showRestrictedFts) && $scoreRow->getIsFirstToSolve(),
                $scoreRow->getSubmissions($this->restricted),
                $scoreRow->getPending($this->restricted),
                $scoreRow->getSolveTime($this->restricted),
                $penalty
            );
        }

        // Fill in empty places in the matrix
        foreach ($this->problems as $contestProblem) {
            // Provide default scores when nothing submitted for this team + problem yet
            $teamId    = $this->team->getTeamid();
            $problemId = $contestProblem->getProbid();
            if (!isset($this->matrix[$teamId][$problemId])) {
                $this->matrix[$teamId][$problemId] = new ScoreboardMatrixItem(false, false, 0, 0, 0, 0);
            }
        }
    }
}
