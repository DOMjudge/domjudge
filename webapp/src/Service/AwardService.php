<?php declare(strict_types=1);

namespace App\Service;

use App\DataTransferObject\Award;
use App\Entity\Contest;
use App\Entity\Team;
use App\Utils\Scoreboard\Scoreboard;

class AwardService
{
    /** @var array<int, Award[]> */
    protected array $awardCache = [];

    public function __construct(protected readonly EventLogService $eventLogService)
    {
    }

    protected function loadAwards(Contest $contest, Scoreboard $scoreboard): void
    {
        $group_winners = $problem_winners = $problem_shortname = [];
        $groups = [];
        foreach ($scoreboard->getTeams() as $team) {
            $teamid = $team->getApiId($this->eventLogService);
            if ($scoreboard->isBestInCategory($team)) {
                $catId = $team->getCategory()->getApiId($this->eventLogService);
                $group_winners[$catId][] = $teamid;
                $groups[$catId] = $team->getCategory()->getName();
            }
            foreach ($scoreboard->getProblems() as $problem) {
                $shortname = $problem->getShortname();
                $probid = $problem->getApiId($this->eventLogService);
                if ($scoreboard->solvedFirst($team, $problem)) {
                    $problem_winners[$probid][] = $teamid;
                    $problem_shortname[$probid] = $shortname;
                }
            }
        }
        $results = [];
        foreach ($group_winners as $id => $team_ids) {
            $type = 'group-winner-' . $id;
            $results[] = new Award(
                id: $type,
                citation: 'Winner(s) of group ' . $groups[$id],
                teamIds: $team_ids
            );
        }
        foreach ($problem_winners as $id => $team_ids) {
            $type = 'first-to-solve-' . $id;
            $results[] = new Award(
                id: $type,
                citation: 'First to solve problem ' . $problem_shortname[$id],
                teamIds: $team_ids
            );
        }
        $overall_winners = $medal_winners = [];

        $additionalBronzeMedals = $contest->getB() ?? 0;

        $currentSortOrder = -1;

        // For every team that we skip because it is not in a medal category, we need to include one
        // additional rank. So keep track of the number of skipped teams
        $skippedTeams = 0;

        foreach ($scoreboard->getScores() as $teamScore) {
            // If we are checking a new sort order, reset the number of skipped teams
            if ($teamScore->team->getCategory()->getSortorder() !== $currentSortOrder) {
                $currentSortOrder = $teamScore->team->getCategory()->getSortorder();
                $skippedTeams = 0;
            }

            if ($teamScore->numPoints == 0) {
                continue;
            }
            $rank = $teamScore->rank;
            $teamid = $teamScore->team->getApiId($this->eventLogService);
            if ($rank === 1) {
                $overall_winners[] = $teamid;
            }
            if ($contest->getMedalsEnabled()) {
                if ($contest->getMedalCategories()->contains($teamScore->team->getCategory())) {
                    if ($rank - $skippedTeams <= $contest->getGoldMedals()) {
                        $medal_winners['gold'][] = $teamid;
                    } elseif ($rank - $skippedTeams <= $contest->getGoldMedals() + $contest->getSilverMedals()) {
                        $medal_winners['silver'][] = $teamid;
                    } elseif ($rank - $skippedTeams <= $contest->getGoldMedals() + $contest->getSilverMedals() + $contest->getBronzeMedals() + $additionalBronzeMedals) {
                        $medal_winners['bronze'][] = $teamid;
                    }
                } else {
                    $skippedTeams++;
                }
            }
        }
        if (count($overall_winners) > 0) {
            $type = 'winner';
            $results[] = new Award(
                id: $type,
                citation: 'Contest winner',
                teamIds: $overall_winners
            );
        }
        foreach ($medal_winners as $metal => $team_ids) {
            $type = $metal . '-medal';
            $results[] = new Award(
                id: $type,
                citation: ucfirst($metal) . ' medal winner',
                teamIds: $team_ids
            );
        }

        $this->awardCache[$contest->getCid()] = $results;
    }

    /**
     * @return Award[]
     */
    public function getAwards(Contest $contest, Scoreboard $scoreboard): array
    {
        if (!isset($this->awardCache[$contest->getCid()])) {
            $this->loadAwards($contest, $scoreboard);
        }

        return $this->awardCache[$contest->getCid()];
    }

    public function getAward(Contest $contest, Scoreboard $scoreboard, string $requestedType): ?Award
    {
        if (!isset($this->awardCache[$contest->getCid()])) {
            $this->loadAwards($contest, $scoreboard);
        }

        foreach ($this->awardCache[$contest->getCid()] as $award) {
            if ($award->id == $requestedType) {
                return $award;
            }
        }

        return null;
    }

    public function medalType(Team $team, Contest $contest, Scoreboard $scoreboard): ?string
    {
        $teamid = $team->getApiId($this->eventLogService);
        if (!isset($this->awardCache[$contest->getCid()])) {
            $this->loadAwards($contest, $scoreboard);
        }
        $awards = $this->awardCache[$contest->getCid()];
        $awardsById = [];
        foreach ($awards as $award) {
            $awardsById[$award->id] = $award;
        }
        $medalNames = ['gold-medal', 'silver-medal', 'bronze-medal'];
        foreach ($medalNames as $medalName) {
            if (in_array($teamid, $awardsById[$medalName]->teamIds ?? [])) {
                return $medalName;
            }
        }
        return null;
    }
}
