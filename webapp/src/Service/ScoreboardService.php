<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Judging;
use App\Entity\Problem;
use App\Entity\RankCache;
use App\Entity\ScoreCache;
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Utils\FreezeData;
use App\Utils\Scoreboard\Filter;
use App\Utils\Scoreboard\Scoreboard;
use App\Utils\Scoreboard\TeamScore;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use App\Entity\ExternalJudgement;
use App\Utils\Scoreboard\SingleTeamScoreboard;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ScoreboardService
 *
 * Service for scoreboard-related functions
 *
 * @package App\Service
 */
class ScoreboardService
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * ScoreboardService constructor.
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param LoggerInterface        $logger
     * @param EventLogService        $eventLogService
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        LoggerInterface $logger,
        EventLogService $eventLogService
    ) {
        $this->em              = $em;
        $this->dj              = $dj;
        $this->logger          = $logger;
        $this->eventLogService = $eventLogService;
    }

    /**
     * Get scoreboard data based on the cached data in the scorecache table.
     *
     * @param Contest     $contest     The contest to get the scoreboard for.
     * @param bool        $jury        If true, the scoreboard will always be current.
     *                                 If false, frozen results will not be returned.
     * @param Filter|null $filter      Filter to use for the scoreboard.
     * @param bool        $visibleOnly Iff $jury is true, determines whether
     *                                 to show non-publicly visible teams.
     * @return Scoreboard|null
     * @throws \Exception
     */
    public function getScoreboard(
        Contest $contest,
        bool $jury = false,
        Filter $filter = null,
        bool $visibleOnly = false
    ) {
        $freezeData = new FreezeData($contest);

        // Don't leak information before start of contest
        if (!$freezeData->started() && !$jury) {
            return null;
        }

        $teams      = $this->getTeams($contest, $jury && !$visibleOnly, $filter);
        $problems   = $this->getProblems($contest);
        $categories = $this->getCategories($jury && !$visibleOnly);
        $scoreCache = $this->getScorecache($contest);

        return new Scoreboard(
            $contest, $teams, $categories, $problems,
            $scoreCache, $freezeData, $jury,
            (int)$this->dj->dbconfig_get('penalty_time', 20),
            (bool)$this->dj->dbconfig_get('score_in_seconds', false)
        );
    }

    /**
     * Get scoreboard data for a single team based on the cached data in the
     * scorecache table.
     *
     * @param Contest $contest         The contest to get the scoreboard for.
     * @param int     $teamId          The ID of the team to get the scoreboard for.
     * @param bool    $showFtsInFreeze If false, the scoreboard will hide first
     *                                 to solve for submissions after contest freeze.
     * @return Scoreboard|null
     * @throws \Exception
     */
    public function getTeamScoreboard(Contest $contest, int $teamId, bool $showFtsInFreeze = true)
    {
        $freezeData = new FreezeData($contest);

        $teams = $this->getTeams($contest, true, new Filter([], [], [], [$teamId]));
        if (empty($teams)) {
            return null;
        }
        $team       = reset($teams);
        $problems   = $this->getProblems($contest);
        $rankCache  = $this->getRankcache($contest, $team);
        $scoreCache = $this->getScorecache($contest, $team);
        $teamRank   = $this->calculateTeamRank($contest, $team, $rankCache, $freezeData, true);

        return new SingleTeamScoreboard(
            $contest, $team, $teamRank, $problems,
            $rankCache, $scoreCache, $freezeData, $showFtsInFreeze,
            (int)$this->dj->dbconfig_get('penalty_time', 20),
            (bool)$this->dj->dbconfig_get('score_in_seconds', false)
        );
    }

    /**
     * Calculate the rank for a single team based on the cache tables.
     *
     * @param Contest         $contest
     * @param Team            $team
     * @param RankCache|null  $rankCache
     * @param FreezeData|null $freezeData
     * @param bool            $jury
     * @return int
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
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

        // Number of teams that definitely ranked higher.
        $better = $this->em->createQueryBuilder()
            ->from(RankCache::class, 'r')
            ->join('r.team', 't')
            ->join('t.category', 'tc')
            ->select('COUNT(t.teamid)')
            ->andWhere('r.contest = :contest')
            ->andWhere('tc.sortorder = :sortorder')
            ->andWhere('t.enabled = 1')
            ->andWhere(sprintf('r.points_%s > :points OR '.
                               '(r.points_%s = :points AND r.totaltime_%s < :totaltime)',
                               $variant, $variant, $variant))
            ->setParameter(':contest', $contest)
            ->setParameter(':sortorder', $sortOrder)
            ->setParameter(':points', $points)
            ->setParameter(':totaltime', $totalTime)
            ->getQuery()
            ->getSingleScalarResult();

        $rank = $better + 1;

        // Resolve ties based on latest correctness points, only necessary
        // when we actually solved at least one problem, so this list should
        // usually be short.
        if ($points > 0) {
            /** @var RankCache[] $tied */
            $tied = $this->em->createQueryBuilder()
                ->from(RankCache::class, 'r')
                ->join('r.team', 't')
                ->join('t.category', 'tc')
                ->select('r, t')
                ->andWhere('r.contest = :contest')
                ->andWhere('tc.sortorder = :sortorder')
                ->andWhere('t.enabled = 1')
                ->andWhere(sprintf('r.points_%s = :points AND r.totaltime_%s = :totaltime',
                                   $variant, $variant))
                ->setParameter(':contest', $contest)
                ->setParameter(':sortorder', $sortOrder)
                ->setParameter(':points', $points)
                ->setParameter(':totaltime', $totalTime)
                ->getQuery()
                ->getResult();

            // All teams that are tied for this position. In most cases this
            // will only be the team we are finding the rank for, only
            // retrieve rest of the data when there are actual ties.
            if (count($tied) > 1) {
                // Initialize team scores for each team.
                /** @var TeamScore[] $teamScores */
                $teamScores = [];
                $teams      = [];
                foreach ($tied as $rankCache) {
                    $tiedteam = $rankCache->getTeam();
                    $teamScores[$tiedteam->getTeamid()] = new TeamScore($tiedteam);
                    $teams[] = $tiedteam;
                }

                // Get submission times for each of the teams.
                /** @var ScoreCache[] $tiedScores */
                $tiedScores = $this->em->createQueryBuilder()
                    ->from(ScoreCache::class, 's')
                    ->join('s.problem', 'p')
                    ->join('p.contest_problems', 'cp', Join::WITH, 'cp.contest = :contest')
                    ->select('s')
                    ->andWhere('s.contest = :contest')
                    ->andWhere(sprintf('s.is_correct_%s = 1', $variant))
                    ->andWhere('cp.allowSubmit = 1')
                    ->andWhere('s.team IN (:teams)')
                    ->setParameter(':contest', $contest)
                    ->setParameter(':teams', $teams)
                    ->getQuery()
                    ->getResult();

                foreach ($tiedScores as $tiedScore) {
                    $teamScores[$tiedScore->getTeam()->getTeamid()]->addSolveTime(Utils::scoretime(
                        $tiedScore->getSolveTime($restricted),
                        (bool)$this->dj->dbconfig_get('score_in_seconds', false)
                    ));
                }

                // Now check for each team if it is ranked higher than $teamid.
                foreach ($tied as $rankCache) {
                    $tiedteam = $rankCache->getTeam();
                    if ($tiedteam->getTeamid() == $team->getTeamid()) {
                        continue;
                    }
                    if (Scoreboard::scoreTiebreaker($teamScores[$tiedteam->getTeamid()],
                                                    $teamScores[$team->getTeamid()]) < 0) {
                        $rank++;
                    }
                }
            }
        }

        return $rank;
    }

    /**
     * Scoreboard calculation
     *
     * Given a contest, team and a problem (re)calculate the values for one
     * row in the scoreboard.
     *
     * Due to current transactions usage, this function MUST NOT do anything
     * inside a transaction.
     *
     * @param Contest $contest
     * @param Team    $team
     * @param Problem $problem
     * @param bool    $updateRankCache If set to false, do not update the rankcache.
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    public function calculateScoreRow(
        Contest $contest,
        Team    $team,
        Problem $problem,
        bool    $updateRankCache = true
    ) {
        $this->logger->debug(
            "ScoreboardService::calculateScoreRow '%d' '%d' '%d'",
            [ $contest->getCid(), $team->getTeamid(), $problem->getProbid() ]
        );

        // First acquire an advisory lock to prevent other calls to this
        // method from interfering with our update.
        $lockString = sprintf('domjudge.%d.%d.%d',
                              $contest->getCid(), $team->getTeamid(), $problem->getProbid());
        if ($this->em->getConnection()->fetchColumn('SELECT GET_LOCK(:lock, 3)',
                                                    [':lock' => $lockString]) != 1) {
            throw new \Exception(sprintf("ScoreboardService::calculateScoreRow failed to obtain lock '%s'",
                                         $lockString));
        }

        // Determine whether we will use external judgements instead of judgings
        $localSource           = DOMJudgeService::DATA_SOURCE_LOCAL;
        $shadow                = DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL;
        $useExternalJudgements = $this->dj->dbconfig_get('data_source', $localSource) == $shadow;

        // Note the clause 's.submittime < c.endtime': this is used to
        // filter out TOO-LATE submissions from pending, but it also means
        // that these will not count as solved. Correct submissions with
        // submittime after contest end should never happen, unless one
        // resets the contest time after successful judging.
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Submission::class, 's')
            ->select('s, c')
            ->leftJoin('s.contest', 'c')
            ->andWhere('s.teamid = :teamid')
            ->andWhere('s.probid = :probid')
            ->andWhere('s.cid = :cid')
            ->andWhere('s.valid = 1')
            ->andWhere('s.submittime < c.endtime')
            ->setParameter(':teamid', $team->getTeamid())
            ->setParameter(':probid', $problem->getProbid())
            ->setParameter(':cid', $contest->getCid())
            ->orderBy('s.submittime');

        if ($useExternalJudgements) {
            $queryBuilder
                ->addSelect('ej')
                ->leftJoin('s.external_judgements', 'ej', Join::WITH, 'ej.valid = 1');
        } else {
            $queryBuilder
                ->addSelect('j')
                ->leftJoin('s.judgings', 'j', Join::WITH, 'j.valid = 1');
        }

        // Check if we need to count compile error as a penalty.
        $compilePenalty = $this->dj->dbconfig_get('compile_penalty', true);

        /** @var Submission[] $submissions */
        $submissions = $queryBuilder->getQuery()->getResult();

        $verificationRequired = $this->dj->dbconfig_get('verification_required', false);

        // Initialize variables.
        $submissionsJury = $pendingJury = $timeJury = 0;
        $submissionsPubl = $pendingPubl = $timePubl = 0;
        $correctJury     = false;
        $correctPubl     = false;

        foreach ($submissions as $submission) {
            /** @var Judging|ExternalJudgement|null $judging */
            if ($useExternalJudgements) {
                $judging = $submission->getExternalJudgements()->first() ?: null;
            } else {
                $judging = $submission->getJudgings()->first() ?: null;
            }

            // Check if this submission has a publicly visible judging result:
            if ($judging === null || empty($judging->getResult()) ||
                (!$useExternalJudgements && $verificationRequired && !$judging->getVerified())) {
                // For the jury: only consider it pending if we don't have a
                // correct one yet. This is needed because during the freeze
                // we consider submissions after the correct one for the
                // public to not leak any info.
                if (!$correctJury) {
                    $pendingJury++;
                }
                $pendingPubl++;
                // Don't do any more counting for this submission.
                continue;
            }

            // We need to count the submission always, except when we don't want
            // to count compiler penalties and the judging is a compiler error.
            $countSubmission = $compilePenalty || $judging->getResult() != Judging::RESULT_COMPILER_ERROR;

            if (!$correctJury && $countSubmission) {
                // For the jury: only consider it as a submission if we don't
                // have a correct one yet. This is needed because during the
                // freeze we consider submissions after the correct one for
                // the public to not leak any info.
                $submissionsJury++;
            }
            if ($submission->isAfterFreeze()) {
                // Show submissions after freeze as pending to the public (if
                // SHOW_PENDING is enabled). Note that we even show these
                // submissions if they are a compiler-error and
                // compile_penalty is set to false, to not leak any info.
                $pendingPubl++;
            } elseif ($countSubmission) {
                $submissionsPubl++;
            }

            // If we encountered a correct submission during the whole contest,
            // do not consider the submissions after that one for correctness.
            if ($correctJury) {
                continue;
            }

            $absSubmitTime = (float)$submission->getSubmittime();
            $submitTime    = $contest->getContestTime($absSubmitTime);

            // if correct, don't look at any more submissions after this one.
            if ($judging->getResult() == Judging::RESULT_CORRECT) {
                $correctJury = true;
                $timeJury    = $submitTime;
                if (!$submission->isAfterFreeze()) {
                    $correctPubl = true;
                    $timePubl    = $submitTime;
                    // Stop counting after a first correct submission, but
                    // only before the freeze. We need to consider all the
                    // submissions during the freeze, because we need to show
                    // them all to the public.
                    break;
                }
            }
        }

        // See if this submission was the first to solve this problem.
        // Only relevant if it was correct in the first place.
        $firstToSolve = false;
        if ($correctJury) {
            $params = [
                ':cid' => $contest->getCid(),
                ':probid' => $problem->getProbid(),
                ':teamSortOrder' => $team->getCategory()->getSortorder(),
                ':submitTime' => $absSubmitTime,
                ':correctResult' => Judging::RESULT_CORRECT,
            ];

            // Find out how many valid submissions were submitted earlier
            // that have a valid judging that is correct, or are awaiting judgement.
            // Only if there are 0 found, we are definitely the first to solve this problem.
            // To find relevant submissions/judgings:
            // - submission needs to be valid (not invalidated)
            // - a valid judging is present, but
            //   - either it's still ongoing (pending judgement, could be correct)
            //   - or already judged to be correct (if it is judged but not correct,
            //     it is not a first to solve)
            // - or the submission is still queued for judgement (judgehost is NULL).
            if ($useExternalJudgements) {
                $firstToSolve = 0 == $this->em->getConnection()->fetchColumn('
                SELECT count(*) FROM submission s
                    LEFT JOIN external_judgement ej USING (submitid)
                    LEFT JOIN external_judgement ej2 ON ej2.submitid = s.submitid AND ej2.starttime > ej.starttime
                    LEFT JOIN team t USING(teamid)
                    LEFT JOIN team_category tc USING (categoryid)
                WHERE s.valid = 1 AND
                    (ej.result IS NULL OR ej.result = :correctResult %s) AND
                    s.cid = :cid AND s.probid = :probid AND
                    tc.sortorder = :teamSortOrder AND
                    round(s.submittime,4) < :submitTime', $params);
            } else {
                if ($verificationRequired) {
                    $verificationRequiredExtra = 'OR j.verified = 0';
                } else {
                    $verificationRequiredExtra = '';
                }
                $firstToSolve = 0 == $this->em->getConnection()->fetchColumn(sprintf('
                SELECT count(*) FROM submission s
                    LEFT JOIN judging j USING (submitid)
                    LEFT JOIN team t USING(teamid)
                    LEFT JOIN team_category tc USING (categoryid)
                WHERE s.valid = 1 AND
                    ((j.valid = 1 AND ( j.rejudgingid IS NULL AND (j.result IS NULL OR j.result = :correctResult %s))) OR
                      s.judgehost IS NULL) AND
                    s.cid = :cid AND s.probid = :probid AND
                    tc.sortorder = :teamSortOrder AND
                    round(s.submittime,4) < :submitTime', $verificationRequiredExtra), $params);
            }
        }

        // Use a direct REPLACE INTO query to drastically speed this up
        $params = [
            ':cid' => $contest->getCid(),
            ':teamid' => $team->getTeamid(),
            ':probid' => $problem->getProbid(),
            ':submissionsRestricted' => $submissionsJury,
            ':pendingRestricted' => $pendingJury,
            ':solvetimeRestricted' => (int)$timeJury,
            ':isCorrectRestricted' => (int)$correctJury,
            ':submissionsPublic' => $submissionsPubl,
            ':pendingPublic' => $pendingPubl,
            ':solvetimePublic' => (int)$timePubl,
            ':isCorrectPublic' => (int)$correctPubl,
            ':isFirstToSolve' => (int)$firstToSolve,
        ];
        $this->em->getConnection()->executeQuery('REPLACE INTO scorecache
            (cid, teamid, probid,
             submissions_restricted, pending_restricted, solvetime_restricted, is_correct_restricted,
             submissions_public, pending_public, solvetime_public, is_correct_public, is_first_to_solve)
            VALUES (:cid, :teamid, :probid, :submissionsRestricted, :pendingRestricted, :solvetimeRestricted, :isCorrectRestricted,
            :submissionsPublic, :pendingPublic, :solvetimePublic, :isCorrectPublic, :isFirstToSolve)', $params);

        if ($this->em->getConnection()->fetchColumn('SELECT RELEASE_LOCK(:lock)',
                                                    [':lock' => $lockString]) != 1) {
            throw new \Exception('ScoreboardService::calculateScoreRow failed to release lock');
        }

        // If we found a new correct result, update the rank cache too
        if ($updateRankCache && ($correctJury || $correctPubl)) {
            $this->updateRankCache($contest, $team);
        }
    }

    /**
     * Update tables used for efficiently computing team ranks.
     *
     * Given a contest and team (re)calculate the time and solved problems for a team.
     *
     * Due to current transactions usage, this function MUST NOT do anything
     * inside a transaction.
     *
     * @param Contest $contest
     * @param Team    $team
     * @throws \Exception
     */
    public function updateRankCache(Contest $contest, Team $team)
    {
        $this->logger->debug("ScoreboardService::updateRankCache '%d' '%d'",
                             [ $contest->getCid(), $team->getTeamid() ]);

        // First acquire an advisory lock to prevent other calls to this
        // method from interfering with our update.
        $lockString = sprintf('domjudge.%d.%d', $contest->getCid(), $team->getTeamid());
        if ($this->em->getConnection()->fetchColumn('SELECT GET_LOCK(:lock, 3)',
                                                    [':lock' => $lockString]) != 1) {
            throw new \Exception(sprintf("ScoreboardService::updateRankCache failed to obtain lock '%s'", $lockString));
        }

        // Fetch contest problems. We can not add it as a relation on
        // ScoreCache as Doctrine doesn't seem to like that its keys are part
        // of the primary key.
        /** @var ContestProblem[] $contestProblems */
        $contestProblems = $this->em->createQueryBuilder()
            ->from(ContestProblem::class, 'cp')
            ->select('cp')
            ->andWhere('cp.contest = :contest')
            ->setParameter(':contest', $contest)
            ->getQuery()
            ->getResult();
        $contestProblemsIndexed = [];
        foreach ($contestProblems as $cp) {
            $contestProblemsIndexed[$cp->getProblem()->getProbid()] = $cp;
        }
        $contestProblems = $contestProblemsIndexed;

        // Intialize our data
        $variants  = ['public' => false, 'restricted' => true];
        $numPoints = [];
        $totalTime = [];
        foreach ($variants as $variant => $isRestricted) {
            $numPoints[$variant] = 0;
            $totalTime[$variant] = $team->getPenalty();
        }

        $penaltyTime      = (int) $this->dj->dbconfig_get('penalty_time', 20);
        $scoreIsInSeconds = (bool)$this->dj->dbconfig_get('score_in_seconds', false);

        // Now fetch the ScoreCache entries.
        /** @var ScoreCache[] $scoreCacheRows */
        $scoreCacheRows = $this->em->createQueryBuilder()
            ->from(ScoreCache::class, 's')
            ->select('s')
            ->andWhere('s.contest = :contest')
            ->andWhere('s.team = :team')
            ->setParameter(':contest', $contest)
            ->setParameter(':team', $team)
            ->getQuery()
            ->getResult();

        // Process all score cache rows
        foreach ($scoreCacheRows as $scoreCache) {
            foreach ($variants as $variant => $isRestricted) {
                $probId = $scoreCache->getProblem()->getProbid();
                if (isset($contestProblems[$probId]) && $scoreCache->getIsCorrect($isRestricted)) {
                    $penalty = Utils::calcPenaltyTime($scoreCache->getIsCorrect($isRestricted),
                                                      $scoreCache->getSubmissions($isRestricted),
                                                      $penaltyTime, $scoreIsInSeconds);

                    $numPoints[$variant] += $contestProblems[$probId]->getPoints();
                    $totalTime[$variant] += Utils::scoretime(
                        (float)$scoreCache->getSolveTime($isRestricted),
                        $scoreIsInSeconds
                    ) + $penalty;
                }
            }
        }

        // Use a direct REPLACE INTO query to drastically speed this up.
        $params = [
            ':cid' => $contest->getCid(),
            ':teamid' => $team->getTeamid(),
            ':pointsRestricted' => $numPoints['restricted'],
            ':totalTimeRestricted' => $totalTime['restricted'],
            ':pointsPublic' => $numPoints['public'],
            ':totalTimePublic' => $totalTime['public'],
        ];
        $this->em->getConnection()->executeQuery('REPLACE INTO rankcache (cid, teamid,
            points_restricted, totaltime_restricted,
            points_public, totaltime_public)
            VALUES (:cid, :teamid, :pointsRestricted, :totalTimeRestricted, :pointsPublic, :totalTimePublic)', $params);

        if ($this->em->getConnection()->fetchColumn('SELECT RELEASE_LOCK(:lock)',
                                                    [':lock' => $lockString]) != 1) {
            throw new \Exception('ScoreboardService::updateRankCache failed to release lock');
        }
    }

    /**
     * Initialize the scoreboard filter for the given request
     * @param Request       $request
     * @param Response|null $response
     * @return Filter
     */
    public function initializeScoreboardFilter(Request $request, Response $response)
    {
        $scoreFilter = [];
        if ($this->dj->getCookie('domjudge_scorefilter')) {
            $scoreFilter = $this->dj->jsonDecode((string)$this->dj->getCookie('domjudge_scorefilter'));
        }

        if ($request->query->has('clear')) {
            $scoreFilter = [];
        }

        if ($request->query->has('filter')) {
            $scoreFilter = [];
            foreach (['affiliations', 'countries', 'categories'] as $type) {
                if ($request->query->has($type)) {
                    $scoreFilter[$type] = $request->query->get($type);
                }
            }
        }

        $this->dj->setCookie(
            'domjudge_scorefilter',
            $this->dj->jsonEncode($scoreFilter),
            0, null, '', false, false, $response
        );

        return new Filter(
            $scoreFilter['affiliations'] ?? [],
            $scoreFilter['countries'] ?? [],
            $scoreFilter['categories'] ?? [],
            $scoreFilter['teams'] ?? []
        );
    }

    /**
     * Get a list of affiliation names grouped on category name
     * @param Contest $contest
     * @return array
     */
    public function getGroupedAffiliations(Contest $contest)
    {
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(TeamCategory::class, 'cat')
            ->select('cat', 't', 'affil')
            ->leftJoin('cat.teams', 't')
            ->leftJoin('t.affiliation', 'affil')
            ->andWhere('cat.visible = 1')
            ->orderBy('cat.name')
            ->addOrderBy('affil.name');

        if (!$contest->isOpenToAllTeams()) {
            $queryBuilder
                ->leftJoin('t.contests', 'c')
                ->leftJoin('cat.contests', 'cc')
                ->andWhere('c = :contest OR cc = :contest')
                ->setParameter(':contest', $contest);
        }

        /** @var TeamCategory[] $categories */
        $categories = $queryBuilder->getQuery()->getResult();

        $groupedAffiliations = [];
        foreach ($categories as $category) {
            $affiliations = [];
            /** @var Team $team */
            foreach ($category->getTeams() as $team) {
                if ($teamaffil = $team->getAffiliation()) {
                    $affiliations[$teamaffil->getName()] = array(
                        'id'   => $this->eventLogService->externalIdFieldForEntity($teamaffil) ?
                            $teamaffil->getExternalid() :
                            $teamaffil->getAffilid(),
                        'name' => $teamaffil->getName(),
                    );
                }
            }

            if (empty($affiliations)) {
                /** @var Team $team */
                foreach ($category->getTeams() as $team) {
                    $affiliations[$team->getName()] = array(
                        'id' => -1,
                        'name' => $team->getName());
                }
            }
            if (!empty($affiliations)) {
                $groupedAffiliations[$category->getName()] = array_values($affiliations);
            }
        }

        return array_chunk($groupedAffiliations, 3, true);
    }

    /**
     * Get values to display in the scoreboard filter
     * @param Contest $contest
     * @param bool    $jury
     * @return array
     * @throws \Exception
     */
    public function getFilterValues(Contest $contest, bool $jury): array
    {
        $filters          = [
            'affiliations' => [],
            'countries' => [],
            'categories' => [],
        ];
        $showFlags        = $this->dj->dbconfig_get('show_flags', true);
        $showAffiliations = $this->dj->dbconfig_get('show_affiliations', true);

        $queryBuilder = $this->em->createQueryBuilder()
            ->from(TeamCategory::class, 'c')
            ->select('c');
        if (!$jury) {
            $queryBuilder->andWhere('c.visible = 1');
        }

        /** @var TeamCategory[] $categories */
        $categories = $queryBuilder->getQuery()->getResult();
        foreach ($categories as $category) {
            $filters['categories'][$category->getCategoryid()] = $category->getName();
        }

        // show only affiliations / countries with visible teams
        if (empty($categories) || !$showAffiliations) {
            $filters['affiliations'] = [];
        } else {
            $queryBuilder = $this->em->createQueryBuilder()
                ->from(TeamAffiliation::class, 'a')
                ->select('a')
                ->join('a.teams', 't')
                ->andWhere('t.category IN (:categories)')
                ->setParameter(':categories', $categories);
            if (!$contest->isOpenToAllTeams()) {
                $queryBuilder
                    ->leftJoin('t.contests', 'c')
                    ->join('t.category', 'cat')
                    ->leftJoin('cat.contests', 'cc')
                    ->andWhere('c = :contest OR cc = :contest')
                    ->setParameter(':contest', $contest);
            }

            /** @var TeamAffiliation[] $affiliations */
            $affiliations = $queryBuilder->getQuery()->getResult();
            foreach ($affiliations as $affiliation) {
                $filters['affiliations'][$affiliation->getAffilid()] = $affiliation->getName();
                if ($showFlags && $affiliation->getCountry() !== null) {
                    $filters['countries'][] = $affiliation->getCountry();
                }
            }
        }

        $filters['countries'] = array_unique($filters['countries']);
        sort($filters['countries']);
        asort($filters['affiliations'], SORT_FLAG_CASE);

        return $filters;
    }

    /**
     * Get the scoreboard Twig data for a given contest
     * @param Request      $request
     * @param Response     $response
     * @param string       $refreshUrl
     * @param bool         $jury
     * @param bool         $public
     * @param bool         $static
     * @param Contest|null $contest
     * @return array
     * @throws \Exception
     */
    public function getScoreboardTwigData(
        ?Request $request,
        ?Response $response,
        string $refreshUrl,
        bool $jury,
        bool $public,
        bool $static,
        Contest $contest = null,
        Scoreboard $scoreboard = null
    ) {
        $data = [
            'refresh' => [
                'after' => 30,
                'url' => $refreshUrl,
                'ajax' => true,
             ],
             'static' => $static,
        ];

        if ($contest) {

            if ($request && $response) {
                $scoreFilter = $this->initializeScoreboardFilter($request, $response);
            } else {
                $scoreFilter = null;
            }
            if ($scoreboard === null) {
                $scoreboard = $this->getScoreboard($contest, $jury, $scoreFilter);
            }

            $data['contest']              = $contest;
            $data['scoreFilter']          = $scoreFilter;
            $data['scoreboard']           = $scoreboard;
            $data['filterValues']         = $this->getFilterValues($contest, $jury);
            $data['groupedAffiliations']  = empty($scoreboard) ? $this->getGroupedAffiliations($contest) : null;
            $data['showFlags']            = $this->dj->dbconfig_get('show_flags', true);
            $data['showAffiliationLogos'] = $this->dj->dbconfig_get('show_affiliation_logos', false);
            $data['showAffiliations']     = $this->dj->dbconfig_get('show_affiliations', true);
            $data['showPending']          = $this->dj->dbconfig_get('show_pending', false);
            $data['showTeamSubmissions']  = $this->dj->dbconfig_get('show_teams_submissions', true);
            $data['scoreInSeconds']       = $this->dj->dbconfig_get('score_in_seconds', false);
            $data['maxWidth']             = $this->dj->dbconfig_get('team_column_width', 0);
        }

        if ($request && $request->isXmlHttpRequest()) {
            $data['jury']   = $jury;
            $data['public'] = $public;
            $data['ajax']   = true;
        }

        return $data;
    }

    /**
     * Get the teams to display on the scoreboard
     * @param Contest     $contest
     * @param bool        $jury
     * @param Filter|null $filter
     * @return Team[]
     */
    protected function getTeams(Contest $contest, bool $jury = false, Filter $filter = null)
    {
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Team::class, 't', 't.teamid')
            ->innerJoin('t.category', 'tc')
            ->leftJoin('t.affiliation', 'ta')
            ->select('t, tc, ta')
            ->andWhere('t.enabled = 1');

        if (!$contest->isOpenToAllTeams()) {
            $queryBuilder
                ->leftJoin('t.contests', 'c')
                ->join('t.category', 'cat')
                ->leftJoin('cat.contests', 'cc')
                ->andWhere('c.cid = :cid OR cc.cid = :cid')
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
     * Get the problems to display on the scoreboard.
     *
     * Note that this will return only a partial object for optimization purposes.
     *
     * @param Contest $contest
     * @return ContestProblem[]
     */
    protected function getProblems(Contest $contest)
    {
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(ContestProblem::class, 'cp')
            ->select('cp, partial p.{probid,externalid,name}')
            ->innerJoin('cp.problem', 'p')
            ->andWhere('cp.allowSubmit = 1')
            ->andWhere('cp.contest = :contest')
            ->setParameter(':contest', $contest)
            ->orderBy('cp.shortname');

        /** @var ContestProblem[] $contestProblems */
        $contestProblems = $queryBuilder->getQuery()->getResult();
        $contestProblemsIndexed = [];
        foreach ($contestProblems as $cp) {
            $contestProblemsIndexed[$cp->getProblem()->getProbid()] = $cp;
        }
        $contestProblems = $contestProblemsIndexed;

        return $contestProblems;
    }

    /**
     * Get the categories to display on the scoreboard
     * @param bool $jury
     * @return TeamCategory[]
     */
    protected function getCategories(bool $jury)
    {
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(TeamCategory::class, 'cat', 'cat.categoryid')
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
     * @param Contest   $contest
     * @param Team|null $team
     * @return ScoreCache[]
     */
    protected function getScorecache(Contest $contest, Team $team = null)
    {
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(ScoreCache::class, 's')
            ->select('s')
            ->andWhere('s.contest = :contest')
            ->setParameter(':contest', $contest);

        if ($team) {
            $queryBuilder
                ->andWhere('s.team = :team')
                ->setParameter(':team', $team);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Get the rank cache for the given team
     * @param Contest $contest
     * @param Team    $team
     * @return RankCache|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    protected function getRankcache(Contest $contest, Team $team)
    {
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(RankCache::class, 'r')
            ->select('r')
            ->andWhere('r.contest = :contest')
            ->andWhere('r.team = :team')
            ->setParameter(':contest', $contest)
            ->setParameter(':team', $team);

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }
}
