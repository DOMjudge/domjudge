<?php declare(strict_types=1);

namespace App\Service;

use App\DataTransferObject\SubmissionRestriction;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\JudgeTask;
use App\Entity\Judging;
use App\Entity\JudgingRun;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\SubmissionFile;
use App\Entity\SubmissionSource;
use App\Entity\Team;
use App\Entity\TestcaseAggregationType;
use App\Entity\TestcaseGroup;
use App\Entity\User;
use App\Utils\FreezeData;
use App\Utils\Utils;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use InvalidArgumentException;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use ZipArchive;

class SubmissionService
{
    final public const FILENAME_REGEX = '/^[a-zA-Z0-9_][a-zA-Z0-9+_\.-]*$/';
    final public const PROBLEM_RESULT_MATCHSTRING = ['@EXPECTED_RESULTS@: ', '@EXPECTED_SCORE@: '];
    final public const PROBLEM_RESULT_REMAP = [
        'ACCEPTED' => 'CORRECT',
        'WRONG_ANSWER' => 'WRONG-ANSWER',
        'TIME_LIMIT_EXCEEDED' => 'TIMELIMIT',
        'RUN_TIME_ERROR' => 'RUN-ERROR',
        'COMPILER_ERROR' => 'COMPILER-ERROR',
        'NO_OUTPUT' => 'NO-OUTPUT',
        'OUTPUT_LIMIT' => 'OUTPUT-LIMIT'
    ];

    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly LoggerInterface $logger,
        protected readonly DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly EventLogService $eventLogService,
        protected readonly ScoreboardService $scoreboardService,
        protected readonly PaginatorInterface $paginator,
    ) {}

    /**
     * Returns a two-element array [score, result], or [null, null] if not all scores/results are ready.
     * @param array<int, JudgingRun[]>|null $runsByGroup
     * @return array{string|null, string|null}
     */
    public static function maybeSetScoringResult(TestcaseGroup $testcaseGroup, Judging $judging, ?array $runsByGroup = null): array
    {
        if ($runsByGroup === null) {
            $runsByGroup = [];
            foreach ($judging->getRuns() as $run) {
                $group = $run->getTestcase()->getTestcaseGroup();
                if ($group !== null) {
                    $runsByGroup[$group->getTestcaseGroupId()][] = $run;
                }
            }
        }

        $allResultsReady = true;
        $allCorrect = true;
        $firstIncorrectVerdict = null;
        $results = [];
        $ignoreSample = $testcaseGroup->isIgnoreSample();

        if ($testcaseGroup->getChildren()->isEmpty()) {
            $relevantRuns = $runsByGroup[$testcaseGroup->getTestcaseGroupId()] ?? [];
            if ($testcaseGroup->getAcceptScore() !== null) {
                $acceptScore = $testcaseGroup->getAcceptScore();
                foreach ($relevantRuns as $run) {
                    if ($run->getRunresult() === null || $run->getRunresult() === '') {
                        $allResultsReady = false;
                    } elseif ($run->getRunresult() !== 'correct') {
                        $allCorrect = false;
                        if ($firstIncorrectVerdict === null) {
                            $firstIncorrectVerdict = $run->getRunresult();
                        }
                    }
                }
                if (count($relevantRuns) > 0) {
                    if ($allCorrect) {
                        $results[] = $acceptScore;
                    } else {
                        $results[] = '0';
                    }
                }
            } else {
                foreach ($relevantRuns as $run) {
                    $results[] = $run->getScore();
                    if ($run->getRunresult() === null || $run->getRunresult() === '') {
                        $allResultsReady = false;
                    } elseif ($run->getRunresult() !== 'correct') {
                        $allCorrect = false;
                        if ($firstIncorrectVerdict === null) {
                            $firstIncorrectVerdict = $run->getRunresult();
                        }
                    }
                }
            }
        } else {
            foreach ($testcaseGroup->getChildren() as $childGroup) {
                if ($ignoreSample && $childGroup->getName() === 'data/sample') {
                    continue;
                }
                $childScoreAndResult = self::maybeSetScoringResult(
                    $childGroup,
                    $judging,
                    $runsByGroup
                );
                $childScore = $childScoreAndResult[0];
                $childResult = $childScoreAndResult[1];
                if ($childResult === null || $childResult === '') {
                    $allResultsReady = false;
                } else {
                    // Always add the child score for aggregation (partial scoring)
                    $results[] = $childScore;
                    if ($childResult !== 'correct') {
                        $allCorrect = false;
                        if ($firstIncorrectVerdict === null) {
                            $firstIncorrectVerdict = $childResult;
                        }
                    }
                }
            }
        }

        $testcaseAggregationType = $testcaseGroup->getAggregationType();
        switch ($testcaseAggregationType) {
            case TestcaseAggregationType::SUM:
            case TestcaseAggregationType::AVG:
                $score = "0";
                foreach ($results as $result) {
                    if ($result === null) {
                        $allResultsReady = false;
                        break;
                    } else {
                        $score = bcadd($score, $result, ScoreboardService::SCALE);
                    }
                }
                if ($testcaseAggregationType === TestcaseAggregationType::AVG && count($results) > 0) {
                    $score = bcdiv($score, (string)count($results), ScoreboardService::SCALE);
                }
                break;
            case TestcaseAggregationType::MIN:
            case TestcaseAggregationType::MAX:
                $score = null;
                foreach ($results as $result) {
                    if ($result === null) {
                        $allResultsReady = false;
                        break;
                    } elseif ($score === null) {
                        $score = $result;
                    } else {
                        if ($testcaseAggregationType === TestcaseAggregationType::MIN
                            && bccomp($result, $score, ScoreboardService::SCALE) < 0) {
                            $score = $result;
                        }
                        if ($testcaseAggregationType === TestcaseAggregationType::MAX
                            && bccomp($result, $score, ScoreboardService::SCALE) > 0) {
                            $score = $result;
                        }
                    }
                }
                break;
            default:
                throw new InvalidArgumentException(sprintf("Unknown testcase aggregation type '%s'.",
                    $testcaseAggregationType->name));
        }

        if ($allResultsReady || (!$allCorrect && !$testcaseGroup->isOnRejectContinue())) {
            // Normalize score to string with proper scale; default to '0' for empty MIN/MAX results
            $score = bcadd($score ?? '0', '0', ScoreboardService::SCALE);
            $result = $allCorrect ? 'correct' : $firstIncorrectVerdict ?? 'judge-error';
            return [$score, $result];
        }
        return [null, null];
    }

    /**
     * Get the scoring hierarchy for the given problem and judging.
     * @return array<string, mixed>|null
     */
    public function getScoringHierarchy(Problem $problem, Judging $judging): ?array
    {
        $parentGroup = $problem->getParentTestcaseGroup();
        if ($parentGroup === null) {
            return null;
        }

        $runsByGroup = [];
        foreach ($judging->getRuns() as $run) {
            $group = $run->getTestcase()->getTestcaseGroup();
            if ($group !== null) {
                $runsByGroup[$group->getTestcaseGroupId()][] = $run;
            }
        }

        return $this->getScoringHierarchyForGroup($parentGroup, $judging, null, $runsByGroup);
    }

    /**
     * Get the scoring hierarchy for the given group and judging.
     * @param array<int, JudgingRun[]>|null $runsByGroup
     * @return array<string, mixed>
     */
    private function getScoringHierarchyForGroup(TestcaseGroup $group, Judging $judging, ?string $parentName = null, ?array $runsByGroup = null): array
    {
        $name = $group->getName();
        $displayName = $name;
        if ($parentName !== null && str_starts_with($name, $parentName)) {
            $displayName = ltrim(substr($name, strlen($parentName)), '/');
        }

        $hierarchy = [
            'name' => $name,
            'display_name' => $displayName,
            'aggregation' => $group->getAggregationType()->value,
            'accept_score' => $group->getAcceptScore(),
            'on_reject_continue' => $group->isOnRejectContinue(),
            'ignore_sample' => $group->isIgnoreSample(),
            'children' => [],
            'testcases' => [],
            'child_scores' => [],
        ];

        [$score, $result] = self::maybeSetScoringResult($group, $judging, $runsByGroup);
        $hierarchy['score'] = $score;
        $hierarchy['result'] = $result;

        if ($group->getChildren()->isEmpty()) {
            if ($group->getAcceptScore() !== null) {
                // Leaf group with accept score
                if ($result !== null) {
                    if ($result === 'correct') {
                        $hierarchy['child_scores'][] = (string)bcadd($group->getAcceptScore(), '0', ScoreboardService::SCALE);
                    } else {
                        $hierarchy['child_scores'][] = (string)bcadd('0', '0', ScoreboardService::SCALE);
                    }
                }
            }

            $relevantRuns = $runsByGroup[$group->getTestcaseGroupId()] ?? [];
            foreach ($relevantRuns as $run) {
                $tc_score = (string)bcadd((string)$run->getScore(), '0', ScoreboardService::SCALE);
                $tc_name = $run->getTestcase()->getOrigInputFilename();
                if ($tc_name !== null) {
                    $lastSlash = strrpos($tc_name, '/');
                    if ($lastSlash !== false) {
                        $tc_name = substr($tc_name, $lastSlash + 1);
                    }
                }
                $hierarchy['testcases'][] = [
                    'rank' => $run->getTestcase()->getRank(),
                    'result' => $run->getRunresult(),
                    'score' => $tc_score,
                    'orig_input_filename' => $run->getTestcase()->getOrigInputFilename(),
                    'display_name' => $tc_name,
                ];
                if ($group->getAcceptScore() === null) {
                    $hierarchy['child_scores'][] = $tc_score;
                }
            }
            // Sort testcases by rank
            usort($hierarchy['testcases'], fn($a, $b) => $a['rank'] <=> $b['rank']);
        } else {
            foreach ($group->getChildren() as $childGroup) {
                if ($group->isIgnoreSample() && $childGroup->getName() === 'data/sample') {
                    continue;
                }
                $child_hierarchy = $this->getScoringHierarchyForGroup($childGroup, $judging, $name, $runsByGroup);
                $hierarchy['children'][] = $child_hierarchy;
                if ($child_hierarchy['score'] !== null) {
                    $hierarchy['child_scores'][] = $child_hierarchy['score'];
                }
            }
        }

        return $hierarchy;
    }

    /**
     * Get a list of submissions that can be displayed in the interface using
     * the submission_list partial.
     *
     * @param Contest[] $contests
     *
     * @return array{Submission[], array<string, int>}|array{PaginationInterface<int, Submission>, array<string, int>} array An array with
     *           two elements: the first one is the list of submissions or the paginated results
     *           and the second one is an array with counts.
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getSubmissionList(
        array $contests,
        SubmissionRestriction $restrictions,
        bool $paginated = true,
        ?int $page = null,
        bool $showShadowUnverified = false,
    ): array {
        if (empty($contests)) {
            if ($paginated) {
                return [$this->paginator->paginate([], page: 1), []];
            }
            return [[], []];
        }

        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Submission::class, 's')
            ->select('s', 'j', 'cp', 'l')
            ->join('s.team', 't')
            ->join('t.categories', 'tc')
            ->join('s.contest_problem', 'cp')
            ->join('s.language', 'l')
            ->andWhere('s.contest IN (:contests)')
            ->setParameter('contests', $contests)
            ->orderBy('s.submittime', 'DESC')
            ->addOrderBy('s.submitid', 'DESC');

        if ($restrictions->withExternalId ?? false) {
            $queryBuilder
                ->andWhere('s.externalid IS NOT NULL')
                ->andWhere('s.expected_results IS NULL');
        }

        if (isset($restrictions->rejudgingId)) {
            $queryBuilder
                ->leftJoin('s.judgings', 'j', Join::WITH, 'j.rejudging = :rejudgingid')
                ->leftJoin(Judging::class, 'jold', Join::WITH,
                    'j.original_judging IS NULL AND s.submitid = jold.submission AND jold.valid = 1')
                ->leftJoin(Judging::class, 'jold2', Join::WITH,
                    'j.original_judging = jold2.judgingid')
                ->addSelect('COALESCE(jold.result, jold2.result) AS oldresult')
                ->andWhere('s.rejudging = :rejudgingid OR j.rejudging = :rejudgingid')
                ->setParameter('rejudgingid', $restrictions->rejudgingId);

            if (isset($restrictions->rejudgingDifference)) {
                if ($restrictions->rejudgingDifference) {
                    $queryBuilder->andWhere('j.result != COALESCE(jold.result, jold2.result)');
                } else {
                    $queryBuilder->andWhere('j.result = COALESCE(jold.result, jold2.result)');
                }
            }

            if (isset($restrictions->oldResult)) {
                $queryBuilder
                    ->andWhere('COALESCE(jold.result, jold2.result) = :oldresult')
                    ->setParameter('oldresult', $restrictions->oldResult);
            }
        } else {
            $queryBuilder->leftJoin('s.judgings', 'j', Join::WITH, 'j.valid = 1');
        }

        $queryBuilder->leftJoin('j.rejudging', 'r');

        if (isset($restrictions->verified)) {
            if ($restrictions->verified) {
                $queryBuilder->andWhere('j.verified = 1');
            } else {
                $queryBuilder->andWhere('j.verified = 0 OR j.verified IS NULL');
            }
        }

        if (isset($restrictions->judged)) {
            if ($restrictions->judged) {
                $queryBuilder->andWhere('j.result IS NOT NULL');
            } else {
                $queryBuilder->andWhere('j.result IS NULL OR j.endtime IS NULL');
            }
        }
        if (isset($restrictions->judging)) {
            if ($restrictions->judging) {
                $queryBuilder->andWhere('j.starttime IS NOT NULL AND j.result IS NULL');
            } else {
                $queryBuilder->andWhere('j.starttime IS NULL OR j.result IS NOT NULL');
            }
        }

        if (isset($restrictions->externallyJudged)) {
            if ($restrictions->externallyJudged) {
                $queryBuilder->andWhere('ej.result IS NOT NULL');
            } else {
                $queryBuilder->andWhere('ej.result IS NULL OR ej.endtime IS NULL');
            }
        }

        if (isset($restrictions->externallyVerified)) {
            if ($restrictions->externallyVerified) {
                $queryBuilder->andWhere('ej.verified = true');
            } else {
                $queryBuilder->andWhere('ej.verified = false');
            }
        }

        if (isset($restrictions->externalDifference)) {
            if ($restrictions->externalDifference) {
                if ($restrictions->result === 'judging' || $restrictions->externalResult === 'judging') {
                    // When either the local or external result is set to judging explicitly,
                    // coalesce the result with a known non-null value, because in MySQL
                    // 'correct' <> null is not true. By coalescing with '-' we prevent this.
                    $queryBuilder
                        ->andWhere('COALESCE(j.result, :dash) != COALESCE(ej.result, :dash)')
                        ->setParameter('dash', '-');
                } else {
                    $queryBuilder->andWhere('j.result != ej.result');
                }
            } else {
                $queryBuilder->andWhere('j.result = ej.result');
            }
        }

        if (isset($restrictions->externalResult)) {
            if ($restrictions->externalResult === 'judging') {
                $queryBuilder->andWhere('ej.result IS NULL or ej.endtime IS NULL');
            } else {
                $queryBuilder
                    ->andWhere('ej.result = :externalresult')
                    ->setParameter('externalresult', $restrictions->externalResult);
            }
        }

        if (isset($restrictions->teamId)) {
            $queryBuilder
                ->andWhere('s.team = :teamid')
                ->setParameter('teamid', $restrictions->teamId);
        }

        if (!empty($restrictions->teamIds)) {
            $queryBuilder
                ->andWhere('s.team IN (:teamids)')
                ->setParameter('teamids', $restrictions->teamIds);
        }

        if (isset($restrictions->userId)) {
            $queryBuilder
                ->andWhere('s.user = :userid')
                ->setParameter('userid', $restrictions->userId);
        }

        if (isset($restrictions->categoryId)) {
            $queryBuilder
                ->andWhere('tc.categoryid = :categoryid')
                ->setParameter('categoryid', $restrictions->categoryId);
        }

        if (!empty($restrictions->categoryIds)) {
            $queryBuilder
                ->andWhere('tc.categoryid IN (:categoryids)')
                ->setParameter('categoryids', $restrictions->categoryIds);
        }

        if (isset($restrictions->affiliationId)) {
            $queryBuilder
                ->andWhere('t.affiliation = :affiliationid')
                ->setParameter('affiliationid', $restrictions->affiliationId);
        }

        if (!empty($restrictions->affiliationIds)) {
            $queryBuilder
                ->andWhere('t.affiliation IN (:affiliationids)')
                ->setParameter('affiliationids', $restrictions->affiliationIds);
        }

        if (isset($restrictions->visible)) {
            $queryBuilder
                ->innerJoin('t.categories', 'cat')
                ->andWhere('cat.visible = true');
        }

        if (isset($restrictions->problemId)) {
            $queryBuilder
                ->andWhere('s.problem = :probid')
                ->setParameter('probid', $restrictions->problemId);
        }

        if (!empty($restrictions->problemIds)) {
            $queryBuilder
                ->andWhere('s.problem IN (:probids)')
                ->setParameter('probids', $restrictions->problemIds);
        }

        if (isset($restrictions->languageId)) {
            $queryBuilder
                ->andWhere('s.language = :langid')
                ->setParameter('langid', $restrictions->languageId);
        }

        if (!empty($restrictions->languageIds)) {
            $queryBuilder
                ->andWhere('s.language IN (:langids)')
                ->setParameter('langids', $restrictions->languageIds);
        }

        if (isset($restrictions->judgehost)) {
            $queryBuilder
                ->andWhere('s.judgehost = :judgehost')
                ->setParameter('judgehost', $restrictions->judgehost);
        }

        if (isset($restrictions->result)) {
            if ($restrictions->result === 'judging') {
                $queryBuilder
                    ->andWhere('s.importError IS NULL')
                    ->andWhere('j.result IS NULL OR j.endtime IS NULL');
            } elseif ($restrictions->result === 'import-error') {
                $queryBuilder->andWhere('s.importError IS NOT NULL');
            } else {
                $queryBuilder
                    ->andWhere('j.result = :result')
                    ->setParameter('result', $restrictions->result);
            }
        }

        if (!empty($restrictions->results)) {
            $resultsContainJudging = in_array('judging', $restrictions->results, true);
            $resultsContainQueued = in_array('queued', $restrictions->results, true);
            $resultsContainImportError = in_array('import-error', $restrictions->results, true);
            $resultsQuery = 'j.result IN (:results)';
            if ($resultsContainJudging) {
                $resultsQuery .= ' OR (j.result IS NULL AND j.starttime IS NOT NULL AND s.importError IS NULL)';
            }
            if ($resultsContainQueued) {
                $resultsQuery .= ' OR (j.result IS NULL AND j.starttime IS NULL AND s.importError IS NULL)';
            }
            if ($resultsContainImportError) {
                $resultsQuery .= ' OR s.importError IS NOT NULL';
            }

            $queryBuilder
                ->andWhere($resultsQuery)
                ->setParameter('results', $restrictions->results);
        }

        if (isset($restrictions->valid)) {
            $queryBuilder
                ->andWhere('s.valid = :valid')
                ->setParameter('valid', $restrictions->valid);
        }

        if ($this->dj->shadowMode()) {
            // When we are shadow, also load the external results
            $queryBuilder
                ->leftJoin('s.external_judgements', 'ej', Join::WITH, 'ej.valid = 1')
                ->addSelect('ej');
        }

        if ($paginated) {
            $submissions = $this->paginator->paginate($queryBuilder, page: $page ?? 1);
        } else {
            $submissions = $queryBuilder->getQuery()->getResult();
        }
        if (isset($restrictions->rejudgingId)) {
            $paginatedSubmissions = $submissions;
            if ($paginated) {
                $submissions = $submissions->getItems();
            }
            // Doctrine will return an array for each item. At index '0' will
            // be the submission and at index 'oldresult' will be the old
            // result. Remap this.
            $submissions = array_map(function ($submissionData) {
                /** @var Submission $submission */
                $submission = $submissionData[0];
                $submission->setOldResult($submissionData['oldresult']);
                return $submission;
            }, $submissions);
            if ($paginated) {
                $paginatedSubmissions->setItems($submissions);
                $submissions = $paginatedSubmissions;
            }
        }

        $counts           = [];
        $countQueryExtras = [
            'total' => '',
            'correct' => 'j.result LIKE \'correct\'',
            'ignored' => 's.valid = 0',
            'unverified' => 'j.verified = 0 AND j.result IS NOT NULL',
            'queued' => 'j.result IS NULL AND j.starttime IS NULL',
            'judging' => 'j.starttime IS NOT NULL AND j.endtime IS NULL'
        ];
        if ($showShadowUnverified) {
            $countQueryExtras['shadowUnverified'] = 'ej.verified = 0 AND ej.result IS NOT NULL AND (ej.result != j.result OR j.result IS NULL)';
            unset($countQueryExtras['unverified']);
        }
        foreach ($countQueryExtras as $count => $countQueryExtra) {
            $countQueryBuilder = (clone $queryBuilder)->select('COUNT(s.submitid) AS cnt');
            if (!empty($countQueryExtra)) {
                $countQueryBuilder->andWhere($countQueryExtra);
            }
            $counts[$count] = (int)$countQueryBuilder
                ->getQuery()
                ->getSingleScalarResult();
        }
        $counts['perteam'] = (clone $queryBuilder)
            ->select('COUNT(DISTINCT s.team) AS cnt')
            ->andWhere($countQueryExtras['queued'])
            ->getQuery()
            ->getSingleScalarResult();
        $counts['inContest'] = (clone $queryBuilder)
            ->select('COUNT(s.submitid)')
            ->join('s.contest', 'c')
            ->andWhere('s.submittime BETWEEN c.starttime AND c.endtime')
            ->andWhere('tc.visible = true')
            ->getQuery()
            ->getSingleScalarResult();

        return [$submissions, $counts];
    }

    /**
     * Determines final result for a judging given an ordered array of
     * judging runs. Runs can be NULL if not run yet. A return value of
     * NULL means that a final result cannot be determined yet; this may
     * only occur when not all testcases have been run yet.
     * @param array<string|null> $runresults
     * @param array<string, int> $resultsPrio
     */
    public static function getFinalResult(array $runresults, array $resultsPrio): ?string
    {
        // Whether we have NULL results.
        $haveNullResult = false;

        // This stores the current result and priority to be returned:
        $bestRunResult = null;
        $bestPriority  = -1;

        foreach ($runresults as $runresult) {
            if ($runresult === null) {
                $haveNullResult = true;
                break;
            } else {
                $priority = $resultsPrio[$runresult];
                if (empty($priority)) {
                    throw new InvalidArgumentException(sprintf("Unknown results '%s' found", $runresult));
                }
                if ($priority > $bestPriority) {
                    $bestRunResult = $runresult;
                    $bestPriority  = $priority;
                }
            }
        }

        // If we have NULL results, check whether the highest priority
        // result has maximal priority. Use a local copy of the
        // 'resultsPrio' array, keeping the original untouched.
        $tmp = $resultsPrio;
        rsort($tmp);
        $maxPriority = reset($tmp);

        // No highest priority result found: no final answer yet.
        if ($haveNullResult && $bestPriority < $maxPriority) {
            return null;
        }

        return $bestRunResult;
    }

    /**
     * @return array<int, array{count: int, limit: int, period: string}>
     */
    public function getRateLimitStatus(Team $team, Contest $contest): array
    {
        $rateLimits = $this->config->get('submission_rate_limit');
        if (empty($rateLimits) || $this->dj->checkrole('jury')) {
            return [];
        }

        $submitTime = Utils::now();
        $maxSeconds = max(array_keys($rateLimits));
        $startTime = $submitTime - $maxSeconds;

        $submissions = $this->em->createQueryBuilder()
            ->select('s.submittime')
            ->from(Submission::class, 's')
            ->where('s.team = :team')
            ->andWhere('s.contest = :contest')
            ->andWhere('s.submittime >= :startTime')
            ->andWhere('s.valid = 1')
            ->setParameter('team', $team)
            ->setParameter('contest', $contest)
            ->setParameter('startTime', $startTime)
            ->getQuery()
            ->getResult();

        $subTimes = array_column($submissions, 'submittime');

        $status = [];
        foreach ($rateLimits as $seconds => $limit) {
            $seconds = (int) $seconds;
            $limit = (int) $limit;
            $windowStart = $submitTime - $seconds;

            $count = 0;
            foreach ($subTimes as $subTime) {
                if ($subTime >= $windowStart) {
                    $count++;
                }
            }

            if ($count >= $limit) {
                $minutes = (float) $seconds / 60.0;
                if ($seconds < 60) {
                    $period = sprintf("%d seconds", $seconds);
                } elseif ($seconds == 60) {
                    $period = "1 minute";
                } else {
                    $period = sprintf("%g minutes", round($minutes, 1));
                }
                $status[] = [
                    'count' => $count,
                    'limit' => $limit,
                    'period' => $period,
                ];
            }
        }

        return $status;
    }

    /**
     * This function takes a (set of) temporary file(s) of a submission,
     * validates it and puts it into the database. Additionally it
     * moves it to a backup storage.
     * @param UploadedFile[]      $files
     * @throws DBALException
     */
    public function submitSolution(
        Team|int $team,
        User|int|null $user,
        ContestProblem|Problem|int $problem,
        Contest|int $contest,
        Language|string $language,
        array $files,
        SubmissionSource $source = SubmissionSource::UNKNOWN,
        ?string $juryMember = null,
        Submission|int|null $originalSubmission = null,
        ?string $entryPoint = null,
        ?string $externalId = null,
        ?float $submitTime = null,
        ?string &$message = null,
        bool $forceImportInvalid = false
    ): ?Submission {
        if (!$team instanceof Team) {
            $team = $this->em->getRepository(Team::class)->find($team);
        }
        if ($user !== null && !$user instanceof User) {
            $user = $this->em->getRepository(User::class)->find($user);
        }
        if (!$contest instanceof Contest) {
            $contest = $this->em->getRepository(Contest::class)->find($contest);
        }
        if (!$problem instanceof ContestProblem) {
            $problem = $this->em->getRepository(ContestProblem::class)->find([
                'contest' => $contest,
                'problem' => $problem
            ]);
        }
        if (!$language instanceof Language) {
            $language = $this->em->getRepository(Language::class)->findByExternalId($language);
        }
        if ($originalSubmission !== null && !$originalSubmission instanceof Submission) {
            $originalSubmission = $this->em->getRepository(Submission::class)->find($originalSubmission);
        }

        if (empty($team)) {
            throw new BadRequestHttpException("Team not found");
        }
        if (empty($problem)) {
            throw new BadRequestHttpException("Problem not found");
        }
        if (empty($contest)) {
            throw new BadRequestHttpException("Contest not found");
        }
        if (empty($language)) {
            throw new BadRequestHttpException("Language not found");
        }

        if (empty($submitTime)) {
            $submitTime = Utils::now();
        }

        $importError = null;

        if (count($files) == 0) {
            $message = "No files specified.";
            if ($forceImportInvalid) {
                $importError = $message;
            } else {
                throw new BadRequestHttpException($message);
            }
        }
        if (count($files) > $this->config->get('sourcefiles_limit')) {
            $message = "Tried to submit more than the allowed number of source files.";
            if ($forceImportInvalid) {
                $importError = $message;
            } else {
                return null;
            }
        }

        $filenames = [];
        foreach ($files as $file) {
            if (!$file->isValid()) {
                $message = $file->getErrorMessage();
                return null;
            }
            $filenames[$file->getClientOriginalName()] = $file->getClientOriginalName();
        }

        if (count($files) != count($filenames)) {
            $message = "Duplicate filenames detected.";
            if ($forceImportInvalid) {
                $importError = $message;
            } else {
                return null;
            }
        }

        $sourceSize = $this->config->get('sourcesize_limit');

        $freezeData = new FreezeData($contest);
        if (!$this->dj->checkrole('jury') && !$freezeData->started()) {
            throw new AccessDeniedHttpException(
                sprintf("The contest is closed, no submissions accepted. [c%d]", $contest->getCid()));
        }

        if (!$contest->getAllowSubmit()) {
            throw new BadRequestHttpException('Submissions for contest (temporarily) disabled');
        }

        // If there is a set of languages configured for the problem, it overrides the languages configured for the
        // contest / globally. This is useful for restricting problems to be solved in specific languages, e.g.
        // output-only problems.
        $allowedLanguages = $problem->getProblem()->getLanguages();
        if ($allowedLanguages->isEmpty()) {
            $allowedLanguages = $this->dj->getAllowedLanguagesForContest($contest);
            if (!in_array($language, $allowedLanguages, strict: true)) {
                throw new BadRequestHttpException(
                    sprintf("Language '%s' not allowed for contest '%s'.",
                        $language->getName(), $contest->getName()));
            }
        } else {
            $allowedLanguages = $allowedLanguages->toArray();
            if (!in_array($language, $allowedLanguages, strict: true)) {
                throw new BadRequestHttpException(
                    sprintf("Language '%s' not allowed for problem '%s'.",
                        $language->getName(), $problem->getProblem()->getName()));
            }
        }

        if ($language->getRequireEntryPoint() && empty($entryPoint)) {
            $message = sprintf("Entry point required for '%s' but none given.", $language->getName());
            if ($forceImportInvalid) {
                $importError = $message;
            } else {
                return null;
            }
        }

        if ($this->dj->checkrole('jury') && $entryPoint == '__auto__') {
            // Fall back to auto detection when we're importing jury submissions.
            $entryPoint = null;
        }

        if (!empty($entryPoint) && !preg_match(self::FILENAME_REGEX, $entryPoint)) {
            $message = sprintf("Entry point '%s' contains illegal characters.", $entryPoint);
            if ($forceImportInvalid || $source === SubmissionSource::SHADOWING) {
                $importError = $message;
            } else {
                return null;
            }
        }

        if (!$this->dj->checkrole('jury') && !$team->getEnabled()) {
            throw new BadRequestHttpException(
                sprintf("Team '%d' not found in database or not enabled.", $team->getTeamid()));
        }

        if ($user && !$this->dj->checkrole('jury') && !$user->getEnabled()) {
            throw new BadRequestHttpException(
                sprintf("User '%d' not found in database or not enabled.", $user->getUserid()));
        }

        if (!$problem->getAllowSubmit()) {
            throw new BadRequestHttpException(
                sprintf("Problem p%d not submittable [c%d].",
                        $problem->getProbid(), $contest->getCid()));
        }

        // If this method is called multiple times, we loose the user from Doctrine because of the internal API call
        // to add the submission to the event table. To fix this, reload the user if this is the case.
        if ($user && !$this->em->contains($user)) {
            $user = $this->em->getRepository(User::class)->find($user->getUserid());
        }

        // Submission rate limiting
        $rateLimitStatus = $this->getRateLimitStatus($team, $contest);
        if (!empty($rateLimitStatus)) {
            $violation = $rateLimitStatus[0];
            throw new BadRequestHttpException(
                sprintf("Submission limit reached: maximum of %d submissions per %s allowed.",
                    $violation['limit'], $violation['period'])
            );
        }

        // Reindex array numerically to make sure we can index it in order
        $files = array_values($files);

        $totalSize = 0;
        $extensionMatchCount = 0;
        foreach ($files as $file) {
            if (!$file->isReadable()) {
                $message = sprintf("File '%s' not found (or not readable).", $file->getRealPath());
                return null;
            }
            if (!preg_match(self::FILENAME_REGEX, $file->getClientOriginalName())) {
                $message = sprintf("Illegal filename '%s'.", $file->getClientOriginalName());
                if ($forceImportInvalid || $source === SubmissionSource::SHADOWING) {
                    $importError = $message;
                } else {
                    return null;
                }
            }
            $totalSize += $file->getSize();

            if ($source !== SubmissionSource::SHADOWING && $language->getFilterCompilerFiles()) {
                $matchesExtension = false;
                foreach ($language->getExtensions() as $extension) {
                    if (str_ends_with($file->getClientOriginalName(), '.' . $extension)) {
                        $matchesExtension = true;
                        break;
                    }
                }
                if ($matchesExtension) {
                    $extensionMatchCount++;
                }
            }
        }

        if ($source !== SubmissionSource::SHADOWING && $language->getFilterCompilerFiles() && $extensionMatchCount === 0) {
            $message = sprintf(
                "None of the submitted files match any of the allowed " .
                "extensions for %s (allowed: %s)",
                $language->getName(), implode(', ', $language->getExtensions())
            );
            if ($forceImportInvalid) {
                $importError = $message;
            } else {
                return null;
            }
        }

        if ($totalSize > $sourceSize * 1024) {
            $message = sprintf("Submission file(s) are larger than %d kB.", $sourceSize);
            if ($forceImportInvalid || $source === SubmissionSource::SHADOWING) {
                $importError = $message;
            } else {
                return null;
            }
        }

        $this->logger->info('Submission input verified');

        // First look up any expected results/score in all submission files to minimize the
        // SQL transaction time below.
        // Only do this for problem import submissions, as we do not want this for re-submitted submissions nor
        // submissions that come through the API, e.g. when doing a replay of an old contest.
        $expectedResults = null;
        $expectedScore = null;
        if ($this->dj->checkrole('jury') && $source === SubmissionSource::PROBLEM_IMPORT) {
            $annotation = null;
            foreach ($files as $file) {
                $fileAnnotation = self::parseExpectedAnnotation(
                    file_get_contents($file->getRealPath()),
                    $this->config->get('results_remap')
                );
                if ($fileAnnotation === false) {
                    $message = sprintf(
                        "Found more than one @EXPECTED_RESULTS@/@EXPECTED_SCORE@ in file '%s'.",
                        $file->getClientOriginalName()
                    );
                    return null;
                }
                if ($fileAnnotation !== null) {
                    if ($annotation !== null) {
                        $message = sprintf(
                            "Found more than one file with @EXPECTED_RESULTS@/@EXPECTED_SCORE@, e.g. in '%s'.",
                            $file->getClientOriginalName()
                        );
                        return null;
                    }
                    $annotation = $fileAnnotation;
                }
            }
            if ($annotation !== null) {
                $expectedResults = $annotation['results'];
                $expectedScore = $annotation['score'];
            }
        }

        $submission = new Submission();
        $submission
            ->setTeam($team)
            ->setUser($user)
            ->setContest($contest)
            ->setProblem($problem->getProblem())
            ->setContestProblem($problem)
            ->setLanguage($language)
            ->setSubmittime($submitTime)
            ->setOriginalSubmission($originalSubmission)
            ->setEntryPoint($entryPoint)
            ->setExternalid($externalId)
            ->setImportError($importError)
            ->setSource($source);

        // Add expected results/score from source. We only do this for jury submissions
        // to prevent accidental auto-verification of team submissions.
        if ($this->dj->checkrole('jury')) {
            if (!empty($expectedResults)) {
                $submission->setExpectedResults($expectedResults);
            }
            if ($expectedScore !== null) {
                $submission->setExpectedScore($expectedScore);
            }
        }
        $this->em->persist($submission);

        foreach ($files as $rank => $file) {
            $submissionFile = new SubmissionFile();
            $submissionFile
                ->setFilename($file->getClientOriginalName())
                ->setRank($rank)
                ->setSourcecode(file_get_contents($file->getRealPath()));
            $submissionFile->setSubmission($submission);
            $this->em->persist($submissionFile);
        }

        if (!$importError) {
            $judging = new Judging();
            $judging
                ->setContest($contest)
                ->setSubmission($submission);
            $submission->addJudging($judging);
            if ($juryMember !== null) {
                $judging->setJuryMember($juryMember);
            }
            $this->em->persist($judging);
            // This is so that we can use the submitid/judgingid below.
            $this->em->flush();

            $priority = match ($source) {
                SubmissionSource::PROBLEM_IMPORT => JudgeTask::PRIORITY_LOW,
                default => JudgeTask::PRIORITY_DEFAULT,
            };
            // Create judgetask as invalid when evaluating as analyst.
            $lazyEval = $this->config->get('lazy_eval_results');
            // We create invalid judgetasks, and only mark them valid when they are interesting for the analysts.
            $start_invalid = $lazyEval === DOMJudgeService::EVAL_ANALYST && $source == SubmissionSource::SHADOWING;
            $this->dj->maybeCreateJudgeTasks($judging, $priority, valid: !$start_invalid);
        }

        $this->em->wrapInTransaction(function () use ($contest, $submission): void {
            $this->em->flush();
            $this->eventLogService->log('submission', $submission->getSubmitid(),
                                        EventLogService::ACTION_CREATE, $contest->getCid());
        });

        // Reload submission, contest, team and contestproblem for now, as
        // EventLogService::log will clear the Doctrine entity manager.
        /** @var Contest $contest */
        /** @var Team $team */
        /** @var ContestProblem $problem */
        $submission = $this->em->getRepository(Submission::class)->find($submission->getSubmitid());
        $contest    = $this->em->getRepository(Contest::class)->find($contest->getCid());
        $team       = $this->em->getRepository(Team::class)->find($team->getTeamid());
        $problem    = $this->em->getRepository(ContestProblem::class)->find([
            'problem' => $problem->getProblem(),
            'contest' => $problem->getContest(),
        ]);

        $this->scoreboardService->calculateScoreRow($contest, $team, $problem->getProblem());

        $this->dj->alert('submit', sprintf('submission %d: team %d, language %s, problem %d',
                                           $submission->getSubmitid(), $team->getTeamid(),
                                           $language->getLangid(), $problem->getProblem()->getProbid()));

        $this->dj->auditlog('submission', $submission->getExternalid(), 'added',
            'via ' . $source->value, null, $contest->getExternalid());

        if (Utils::difftime((float)$contest->getEndtime(), $submitTime) <= 0) {
            $this->logger->info(
                "The contest is closed, submission stored but not processed. [c%d]",
                [ $contest->getCid() ]
            );
        }

        return $submission;
    }

    /**
     * Normalize the given expected result.
     */
    public static function normalizeExpectedResult(string $result): string
    {
        $result = trim(mb_strtoupper($result));
        if (in_array($result, array_keys(self::PROBLEM_RESULT_REMAP))) {
            return self::PROBLEM_RESULT_REMAP[$result];
        }
        return $result;
    }

    /**
     * Parse expected annotation from source file, returning structured data.
     *
     * Returns an array with:
     *   - 'results': array of result names (if found)
     *   - 'score': numeric score (if found)
     * Returns false if multiple annotations of the same type found, null if no annotation found.
     *
     * @param array<string, string> $resultsRemap
     * @return array{results: string[]|null, score: string|null}|false|null
     */
    public static function parseExpectedAnnotation(string $source, array $resultsRemap): array|false|null
    {
        $expectedResults = null;
        $expectedScore = null;

        foreach (self::PROBLEM_RESULT_MATCHSTRING as $pattern) {
            $pos = mb_stripos($source, $pattern);
            if ($pos !== false) {
                // Check if we find another match after the first one, since
                // that is not allowed.
                if (mb_stripos($source, $pattern, $pos + mb_strlen($pattern)) !== false) {
                    return false;
                }

                $beginpos = $pos + mb_strlen($pattern);
                $endpos = mb_strpos($source, "\n", $beginpos);
                if ($endpos === false) {
                    $endpos = mb_strlen($source);
                }
                $str = trim(mb_substr($source, $beginpos, $endpos - $beginpos));

                // If @EXPECTED_SCORE@ is used with a numeric value, treat it as an expected score
                if ($pattern === '@EXPECTED_SCORE@: ' && is_numeric($str)) {
                    if ($expectedScore !== null) {
                        return false;
                    }
                    $expectedScore = $str;
                } else {
                    // Otherwise, treat as expected results (list of result names)
                    $results = explode(',', mb_strtoupper($str));
                    foreach ($results as $key => $val) {
                        $result = self::normalizeExpectedResult($val);
                        $lowerResult = mb_strtolower($result);
                        if (isset($resultsRemap[$lowerResult])) {
                            $result = mb_strtoupper($resultsRemap[$lowerResult]);
                        }
                        $results[$key] = $result;
                    }
                    if ($expectedResults !== null) {
                        return false;
                    }
                    $expectedResults = $results;
                }
            }
        }

        if ($expectedResults === null && $expectedScore === null) {
            return null;
        }

        return [
            'results' => $expectedResults,
            'score' => $expectedScore,
        ];
    }

    /**
     * Compute the filename of a given submission. $fileData must be an array
     * that contains the data from submission and submission_file.
     *
     * @param array<string, string> $fileData
     */
    public function getSourceFilename(array $fileData): string
    {
        return implode('.', [
            'c' . $fileData['cid'],
            's' . $fileData['submitid'],
            't' . $fileData['teamid'],
            'p' . $fileData['probid'],
            $fileData['langid'],
            $fileData['rank'],
            $fileData['filename']
        ]);
    }

    /**
     * Get a response object containing the given submission as a ZIP.
     *
     * @throws ServiceUnavailableHttpException
     */
    public function getSubmissionZipResponse(Submission $submission): StreamedResponse
    {
        $files = $submission->getFiles();
        $zip   = new ZipArchive;
        if (!($tmpfname = tempnam($this->dj->getDomjudgeTmpDir(), "submission_file-"))) {
            throw new ServiceUnavailableHttpException(null, 'Could not create temporary file.');
        }

        $res = $zip->open($tmpfname, ZipArchive::OVERWRITE);
        if ($res !== true) {
            throw new ServiceUnavailableHttpException(null, "Could not create temporary zip file.");
        }
        foreach ($files as $file) {
            $zip->addFromString($file->getFilename(), $file->getSourcecode());
        }
        $zip->close();

        return Utils::streamZipFile($tmpfname, 's' . $submission->getSubmitid() . '.zip');
    }

    public function getSubmissionFileResponse(Submission $submission): StreamedResponse
    {
        $files = $submission->getFiles();

        if ($files->count() !== 1) {
            throw new ServiceUnavailableHttpException(null, 'Submission does not contain exactly one file.');
        }

        $file = $files[0];
        $filename = $file->getFilename();
        $sourceCode = $file->getSourcecode();

        return new StreamedResponse(function () use ($sourceCode): void {
            echo $sourceCode;
        }, 200, [
            'Content-Type'        => 'text/plain',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => strlen($sourceCode),
        ]);
    }

    public function getSubmissionFileCount(Submission $submission): int
    {
        return count($submission->getFiles());
    }
}
