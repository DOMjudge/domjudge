<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Contest;
use App\Entity\Team;
use App\Utils\Scoreboard\Scoreboard;

class AwardService
{
    protected EventLogService $eventLogService;

    protected array $awardCache = [];

    public function __construct(EventLogService $eventLogService)
    {
        $this->eventLogService = $eventLogService;
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
            $result = [
                'id' => $type,
                'citation' => 'Winner(s) of group ' . $groups[$id],
                'team_ids' => $team_ids
            ];
            $results[] = $result;
        }
        foreach ($problem_winners as $id => $team_ids) {
            $type = 'first-to-solve-' . $id;
            $result = [
                'id' => $type,
                'citation' => 'First to solve problem ' . $problem_shortname[$id],
                'team_ids' => $team_ids
            ];
            $results[] = $result;
        }
        $overall_winners = $medal_winners = [];

        $additionalBronzeMedals = $contest->getB() ?? 0;

        // Can we assume this is ordered just walk the first 12+B entries?
        foreach ($scoreboard->getScores() as $teamScore) {
            if ($teamScore->numPoints == 0) {
                continue;
            }
            $rank = $teamScore->rank;
            $teamid = $teamScore->team->getApiId($this->eventLogService);
            if ($rank === 1) {
                $overall_winners[] = $teamid;
            }
            if ($contest->getMedalsEnabled() && $contest->getMedalCategories()->contains($teamScore->team->getCategory())) {
                if ($rank <= $contest->getGoldMedals()) {
                    $medal_winners['gold'][] = $teamid;
                } elseif ($rank <= $contest->getGoldMedals() + $contest->getSilverMedals()) {
                    $medal_winners['silver'][] = $teamid;
                } elseif ($rank <= $contest->getGoldMedals() + $contest->getSilverMedals() + $contest->getBronzeMedals() + $additionalBronzeMedals) {
                    $medal_winners['bronze'][] = $teamid;
                }
            }
        }
        if (count($overall_winners) > 0) {
            $type = 'winner';
            $result = [
                'id' => $type,
                'citation' => 'Contest winner',
                'team_ids' => $overall_winners
            ];
            $results[] = $result;
        }
        foreach ($medal_winners as $metal => $team_ids) {
            $type = $metal . '-medal';
            $result = [
                'id' => $metal . '-medal',
                'citation' => ucfirst($metal) . ' medal winner',
                'team_ids' => $team_ids
            ];
            $results[] = $result;
        }

        $this->awardCache[$contest->getCid()] = $results;
    }

    public function getAwards(Contest $contest, Scoreboard $scoreboard, string $requestedType = null): ?array
    {
        if (!isset($this->awardCache[$contest->getCid()])) {
            $this->loadAwards($contest, $scoreboard);
        }

        if ($requestedType === null) {
            return $this->awardCache[$contest->getCid()];
        }

        foreach ($this->awardCache[$contest->getCid()] as $award) {
            if ($award['id'] == $requestedType) {
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
            $awardsById[$award['id']] = $award;
        }
        $medalNames = ['gold-medal', 'silver-medal', 'bronze-medal'];
        foreach ($medalNames as $medalName) {
            if (in_array($teamid, $awardsById[$medalName]['team_ids'] ?? [])) {
                return $medalName;
            }
        }
        return null;
    }
}
