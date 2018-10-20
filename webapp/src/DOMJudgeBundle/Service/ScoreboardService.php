<?php declare(strict_types=1);

namespace DOMJudgeBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\ContestProblem;
use DOMJudgeBundle\Entity\RankCache;
use DOMJudgeBundle\Entity\ScoreCache;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Entity\TeamCategory;
use DOMJudgeBundle\Utils\FreezeData;
use DOMJudgeBundle\Utils\Scoreboard\Filter;
use DOMJudgeBundle\Utils\Scoreboard\Scoreboard;
use DOMJudgeBundle\Utils\Scoreboard\SingleTeamScoreboard;
use DOMJudgeBundle\Utils\Scoreboard\TeamScore;
use DOMJudgeBundle\Utils\Utils;

/**
 * Class ScoreboardService
 *
 * Service for scoreboard-related functions
 *
 * @package DOMJudgeBundle\Service
 */
class ScoreboardService
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var DOMJudgeService
     */
    protected $DOMJudgeService;

    /**
     * ScoreboardService constructor.
     * @param EntityManagerInterface $entityManager
     * @param DOMJudgeService $DOMJudgeService
     */
    public function __construct(EntityManagerInterface $entityManager, DOMJudgeService $DOMJudgeService)
    {
        $this->entityManager   = $entityManager;
        $this->DOMJudgeService = $DOMJudgeService;
    }

    /**
     * Get scoreboard data based on the cached data in the scorecache table
     *
     * @param Contest $contest The contest to get the scoreboard for
     * @param bool $jury If true, the scoreboard will always be current. If false, frozen results will not be returned
     * @param Filter|null $filter Filter to use for the scoreboard
     * @param bool $visibleOnly Iff $jury is true, determines whether to show non-publicly visible teams
     * @return Scoreboard|null
     * @throws \Exception
     */
    public function getScoreboard(Contest $contest, bool $jury = false, Filter $filter = null, bool $visibleOnly = false)
    {
        $freezeData = new FreezeData($contest);

        // Don't leak information before start of contest
        if (!$freezeData->started() && !$jury) {
            return null;
        }

        $teams      = $this->getTeams($contest, $jury && !$visibleOnly, $filter);
        $problems   = $this->getProblems($contest);
        $categories = $this->getCategories($jury && !$visibleOnly);
        $scoreCache = $this->getScorecache($contest);

        return new Scoreboard($teams, $categories, $problems, $scoreCache, $freezeData, $jury,
                              (int)$this->DOMJudgeService->dbconfig_get('penalty_time', 20),
                              (bool)$this->DOMJudgeService->dbconfig_get('score_in_seconds', false));
    }

    /**
     * Get scoreboard data for a single team based on the cached data in the scorecache table
     *
     * @param Contest $contest The contest to get the scoreboard for
     * @param int $teamId The ID of the team to get the scoreboard for
     * @param bool $jury If true, the scoreboard will always be current. If false, frozen results will not be returned
     * @return Scoreboard|null
     * @throws \Exception
     */
    public function getTeamScoreboard(Contest $contest, int $teamId, bool $jury = false)
    {
        $freezeData = new FreezeData($contest);

        // Don't leak information before start of contest
        if (!$freezeData->started()) {
            return null;
        }

        $teams      = $this->getTeams($contest, true, new Filter([], [], [], [$teamId]));
        if (empty($teams)) {
            return null;
        }
        $team       = reset($teams);
        $problems   = $this->getProblems($contest);
        $rankCache  = $this->getRankcache($contest, $team);
        $scoreCache = $this->getScorecache($contest, $team);
        if ($jury || !$freezeData->showFrozen(false)) {
            $teamRank = $this->calculateTeamRank($contest, $team, $rankCache, $freezeData, $jury);
        } else {
            $teamRank = 0;
        }

        return new SingleTeamScoreboard($team, $teamRank, $problems, $rankCache, $scoreCache, $freezeData, $jury,
                                        (int)$this->DOMJudgeService->dbconfig_get('penalty_time', 20),
                                        (bool)$this->DOMJudgeService->dbconfig_get('score_in_seconds', false));
    }

    /**
     * Get the teams to display on the scoreboard
     * @param Contest $contest
     * @param bool $jury
     * @param Filter|null $filter
     * @return Team[]
     */
    protected function getTeams(Contest $contest, bool $jury = false, Filter $filter = null)
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Team', 't', 't.teamid')
            ->innerJoin('t.category', 'tc')
            ->leftJoin('t.affiliation', 'ta')
            ->select('t, tc, ta')
            ->andWhere('t.enabled = 1');

        if (!$contest->getPublic()) {
            $queryBuilder
                ->join('t.contests', 'c')
                ->andWhere('c.cid = :cid')
                ->setParameter(':cid', $contest->getCid());
        }

        if (!$jury) {
            $queryBuilder->andWhere('tc.visible = 1');
        }

        if ($filter) {
            if ($filter->getAffiliations()) {
                $queryBuilder
                    ->andWhere('t.affilid IN (:affiliations)')
                    ->setParameter(':affiliations', $filter->getAffiliations());
            }

            if ($filter->getCategories()) {
                $queryBuilder
                    ->andWhere('t.categoryid IN (:categories)')
                    ->setParameter(':categories', $filter->getCategories());
            }

            if ($filter->getCountries()) {
                $queryBuilder
                    ->andWhere('ta.country IN (:countries)')
                    ->setParameter(':countries', $filter->getCountries());
            }

            if ($filter->getTeams()) {
                $queryBuilder
                    ->andWhere('t.teamid IN (:teams)')
                    ->setParameter(':teams', $filter->getTeams());
            }
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Get the problems to display on the scoreboard
     * @param Contest $contest
     * @return ContestProblem[]
     */
    protected function getProblems(Contest $contest)
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:ContestProblem', 'cp', 'cp.probid')
            ->select('cp, p')
            ->innerJoin('cp.problem', 'p')
            ->andWhere('cp.allow_submit = 1')
            ->andWhere('cp.cid = :cid')
            ->setParameter(':cid', $contest->getCid())
            ->orderBy('cp.shortname');

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Get the categories to display on the scoreboard
     * @param bool $jury
     * @return TeamCategory[]
     */
    protected function getCategories(bool $jury)
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:TeamCategory', 'cat', 'cat.categoryid')
            ->select('cat')
            ->orderBy('cat.sortorder')
            ->addOrderBy('cat.name')
            ->addOrderBy('cat.categoryid');

        if (!$jury) {
            $queryBuilder->andWhere('cat.visible = 1');
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Get the scorecache used to calculate the scoreboard
     * @param Contest $contest
     * @param Team|null $team
     * @return ScoreCache[]
     */
    protected function getScorecache(Contest $contest, Team $team = null)
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:ScoreCache', 's')
            ->join('s.contest_problem', 'cp')
            ->select('s, cp')
            ->where('s.cid = :cid')
            ->setParameter(':cid', $contest->getCid());

        if ($team) {
            $queryBuilder
                ->andWhere('s.teamid = :teamid')
                ->setParameter(':teamid', $team->getTeamid());
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Get the rank cache for the given team
     * @param Contest $contest
     * @param Team $team
     * @return RankCache|null
     */
    protected function getRankcache(Contest $contest, Team $team)
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:RankCache', 'r')
            ->select('r')
            ->where('r.cid = :cid')
            ->andWhere('r.teamid = :teamid')
            ->setParameter(':cid', $contest->getCid())
            ->setParameter(':teamid', $team->getTeamid());

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    /**
     * Calculate the rank for a single team based on the cache tables
     *
     * @param Contest $contest
     * @param Team $team
     * @param RankCache|null $rankCache
     * @param FreezeData|null $freezeData
     * @param bool $jury
     * @return int
     */
    public function calculateTeamRank(
        Contest $contest,
        Team $team,
        RankCache $rankCache = null,
        FreezeData $freezeData = null,
        bool $jury = false
    ) {
        if ($freezeData === null) {
            $freezeData = new FreezeData($contest);
        }
        if ($rankCache === null) {
            $rankCache = $this->getRankcache($contest, $team);
        }
        $restricted = ($jury || $freezeData->showFinal(false));
        $variant    = $restricted ? 'restricted' : 'public';
        $points     = $rankCache ? $rankCache->getPointsRestricted() : 0;
        $totalTime  = $rankCache ? $rankCache->getTotaltimeRestricted() : 0;
        $sortOrder  = $team->getCategory()->getSortorder();

        // Number of teams that definitely ranked higher
        $better = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:RankCache', 'r')
            ->join('r.team', 't')
            ->join('t.category', 'tc')
            ->select('COUNT(t.teamid)')
            ->andWhere('r.cid = :cid')
            ->andWhere('tc.sortorder = :sortorder')
            ->andWhere('t.enabled = 1')
            ->andWhere(sprintf('r.points_%s > :points OR (r.points_%s = :points AND r.totaltime_%s < :totaltime)', $variant, $variant,
                               $variant))
            ->setParameter(':cid', $contest->getCid())
            ->setParameter(':sortorder', $sortOrder)
            ->setParameter(':points', $points)
            ->setParameter(':totaltime', $totalTime)
            ->getQuery()
            ->getSingleScalarResult();

        $rank = $better + 1;

        // Resolve ties based on latest correctness points, only necessary when we actually
        // solved at least one problem, so this list should usually be short
        if ($points > 0) {
            /** @var RankCache[] $tied */
            $tied = $this->entityManager->createQueryBuilder()
                ->from('DOMJudgeBundle:RankCache', 'r')
                ->join('r.team', 't')
                ->join('t.category', 'tc')
                ->select('r, t')
                ->andWhere('r.cid = :cid')
                ->andWhere('tc.sortorder = :sortorder')
                ->andWhere('t.enabled = 1')
                ->andWhere(sprintf('r.points_%s = :points AND r.totaltime_%s = :totaltime', $variant, $variant))
                ->setParameter(':cid', $contest->getCid())
                ->setParameter(':sortorder', $sortOrder)
                ->setParameter(':points', $points)
                ->setParameter(':totaltime', $totalTime)
                ->getQuery()
                ->getResult();

            // All teams that are tied for this position, in most cases this will only be the team we are finding the rank for,
            // only retrieve rest of the data when there are actual ties
            if (count($tied) > 1) {
                // Initialize team scores for each team
                /** @var TeamScore[] $teamScores */
                $teamScores = [];
                $teamIds    = [];
                foreach ($tied as $rankCache) {
                    $teamScores[$rankCache->getTeamid()] = new TeamScore($rankCache->getTeam());
                    $teamIds[]                           = $rankCache->getTeamid();
                }

                // Get submission times for each of the teams
                /** @var ScoreCache[] $tiedScores */
                $tiedScores = $this->entityManager->createQueryBuilder()
                    ->from('DOMJudgeBundle:ScoreCache', 's')
                    ->join('s.contest_problem', 'cp')
                    ->select('s')
                    ->andWhere('s.cid = :cid')
                    ->andWhere(sprintf('s.is_correct_%s = 1', $variant))
                    ->andWhere('cp.allow_submit = 1')
                    ->andWhere('s.teamid IN (:teamids)')
                    ->setParameter(':cid', $contest->getCid())
                    ->setParameter(':teamids', $teamIds)
                    ->getQuery()
                    ->getResult();

                foreach ($tiedScores as $tiedScore) {
                    $teamScores[$tiedScore->getTeamid()]->addSolveTime(Utils::scoretime(
                        $tiedScore->getSolveTime($restricted),
                        (bool)$this->DOMJudgeService->dbconfig_get('score_in_seconds', false)
                    ));
                }

                // Now check for each team if it is ranked higher than $teamid
                foreach ($tied as $rankCache) {
                    if ($rankCache->getTeamid() == $team->getTeamid()) {
                        continue;
                    }
                    if (Scoreboard::scoreTiebreaker($teamScores[$rankCache->getTeamid()], $teamScores[$team->getTeamid()]) < 0) {
                        $rank++;
                    }
                }
            }
        }

        return $rank;
    }
}
