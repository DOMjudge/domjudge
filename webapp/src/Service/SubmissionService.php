<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\JudgeTask;
use App\Entity\Judging;
use App\Entity\Language;
use App\Entity\Submission;
use App\Entity\SubmissionFile;
use App\Entity\Team;
use App\Entity\User;
use App\Utils\FreezeData;
use App\Utils\Utils;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use ZipArchive;

/**
 * Class SubmissionService
 * @package App\Service
 */
class SubmissionService
{
    const FILENAME_REGEX = '/^[a-zA-Z0-9][a-zA-Z0-9+_\.-]*$/';
    const PROBLEM_RESULT_MATCHSTRING = ['@EXPECTED_RESULTS@: ', '@EXPECTED_SCORE@: '];
    const PROBLEM_RESULT_REMAP = [
        'ACCEPTED' => 'CORRECT',
        'WRONG_ANSWER' => 'WRONG-ANSWER',
        'TIME_LIMIT_EXCEEDED' => 'TIMELIMIT',
        'RUN_TIME_ERROR' => 'RUN-ERROR',
        'COMPILER_ERROR' => 'COMPILER-ERROR',
        'NO_OUTPUT' => 'NO-OUTPUT',
        'OUTPUT_LIMIT' => 'OUTPUT-LIMIT'
    ];

    protected EntityManagerInterface $em;
    protected LoggerInterface $logger;
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected EventLogService $eventLogService;
    protected ScoreboardService $scoreboardService;

    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService,
        ScoreboardService $scoreboardService
    ) {
        $this->em                = $em;
        $this->logger            = $logger;
        $this->dj                = $dj;
        $this->config            = $config;
        $this->eventLogService   = $eventLogService;
        $this->scoreboardService = $scoreboardService;
    }

    /**
     * Get a list of submissions that can be displayed in the interface using
     * the submission_list partial.
     *
     * Restrictions can contain the following keys;
     * - rejudgingid: ID of a rejudging to filter on
     * - verified: If true, only return verified submissions.
     *             If false, only return unverified or unjudged submissions.
     * - judged: If true, only return judged submissions.
     *           If false, only return unjudged submissions.
     * - rejudgingdiff: If true, only return judgings that differ from their
     *                  original result in final verdict. Vice versa if false.
     * - teamid: ID of a team to filter on
     * - categoryid: ID of a team category to filter on
     * - probid: ID of a problem to filter on
     * - langid: ID of a language to filter on
     * - judgehost: hostname of a judgehost to filter on
     * - old_result: result of old judging to filter on
     * - result: result of current judging to filter on
     *
     * @return array An array with two elements: the first one is the list of
     *               submissions and the second one is an array with counts.
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getSubmissionList(array $contests, array $restrictions, int $limit = 0): array
    {
        if (empty($contests)) {
            return [[], []];
        }

        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Submission::class, 's')
            ->select('s', 'j', 'cp')
            ->join('s.team', 't')
            ->join('s.contest_problem', 'cp')
            ->andWhere('s.contest IN (:contests)')
            ->setParameter('contests', array_keys($contests))
            ->orderBy('s.submittime', 'DESC')
            ->addOrderBy('s.submitid', 'DESC');

        if ($limit > 0) {
            $queryBuilder->setMaxResults($limit);
        }

        if ($restrictions['with_external_id'] ?? false) {
            $queryBuilder->andWhere('s.externalid IS NOT NULL');
        }

        if (isset($restrictions['rejudgingid'])) {
            $queryBuilder
                ->leftJoin('s.judgings', 'j', Join::WITH, 'j.rejudging = :rejudgingid')
                ->leftJoin(Judging::class, 'jold', Join::WITH,
                    'j.original_judging IS NULL AND s.submitid = jold.submission AND jold.valid = 1')
                ->leftJoin(Judging::class, 'jold2', Join::WITH,
                    'j.original_judging = jold2.judgingid')
                ->addSelect('COALESCE(jold.result, jold2.result) AS oldresult')
                ->andWhere('s.rejudging = :rejudgingid OR j.rejudging = :rejudgingid')
                ->setParameter('rejudgingid', $restrictions['rejudgingid']);

            if (isset($restrictions['rejudgingdiff'])) {
                if ($restrictions['rejudgingdiff']) {
                    $queryBuilder->andWhere('j.result != COALESCE(jold.result, jold2.result)');
                } else {
                    $queryBuilder->andWhere('j.result = COALESCE(jold.result, jold2.result)');
                }
            }

            if (isset($restrictions['old_result'])) {
                $queryBuilder
                    ->andWhere('COALESCE(jold.result, jold2.result) = :oldresult')
                    ->setParameter('oldresult', $restrictions['old_result']);
            }
        } else {
            $queryBuilder->leftJoin('s.judgings', 'j', Join::WITH, 'j.valid = 1');
        }

        $queryBuilder->leftJoin('j.rejudging', 'r');

        if (isset($restrictions['verified'])) {
            if ($restrictions['verified']) {
                $queryBuilder->andWhere('j.verified = 1');
            } else {
                $queryBuilder->andWhere('j.verified = 0 OR j.verified IS NULL');
            }
        }

        if (isset($restrictions['judged'])) {
            if ($restrictions['judged']) {
                $queryBuilder->andWhere('j.result IS NOT NULL');
            } else {
                $queryBuilder->andWhere('j.result IS NULL OR j.endtime IS NULL');
            }
        }

        if (isset($restrictions['externally_judged'])) {
            if ($restrictions['externally_judged']) {
                $queryBuilder->andWhere('ej.result IS NOT NULL');
            } else {
                $queryBuilder->andWhere('ej.result IS NULL OR ej.endtime IS NULL');
            }
        }

        if (isset($restrictions['externally_verified'])) {
            if ($restrictions['externally_verified']) {
                $queryBuilder->andWhere('ej.verified = true');
            } else {
                $queryBuilder->andWhere('ej.verified = false');
            }
        }

        if (isset($restrictions['external_diff'])) {
            if ($restrictions['external_diff']) {
                $queryBuilder->andWhere('j.result != ej.result');
            } else {
                $queryBuilder->andWhere('j.result = ej.result');
            }
        }

        if (isset($restrictions['external_result'])) {
            if ($restrictions['external_result'] === 'judging') {
                $queryBuilder->andWhere('ej.result IS NULL or ej.endtime IS NULL');
            } else {
                $queryBuilder
                    ->andWhere('ej.result = :externalresult')
                    ->setParameter('externalresult', $restrictions['external_result']);
            }
        }

        if (isset($restrictions['teamid'])) {
            $queryBuilder
                ->andWhere('s.team = :teamid')
                ->setParameter('teamid', $restrictions['teamid']);
        }

        if (isset($restrictions['userid'])) {
            $queryBuilder
                ->andWhere('s.user = :userid')
                ->setParameter('userid', $restrictions['userid']);
        }

        if (isset($restrictions['categoryid'])) {
            $queryBuilder
                ->andWhere('t.category = :categoryid')
                ->setParameter('categoryid', $restrictions['categoryid']);
        }

        if (isset($restrictions['visible'])) {
            $queryBuilder
                ->innerJoin('t.category', 'cat')
                ->andWhere('cat.visible = true');
        }

        if (isset($restrictions['probid'])) {
            $queryBuilder
                ->andWhere('s.problem = :probid')
                ->setParameter('probid', $restrictions['probid']);
        }

        if (isset($restrictions['langid'])) {
            $queryBuilder
                ->andWhere('s.language = :langid')
                ->setParameter('langid', $restrictions['langid']);
        }

        if (isset($restrictions['judgehost'])) {
            $queryBuilder
                ->andWhere('s.judgehost = :judgehost')
                ->setParameter('judgehost', $restrictions['judgehost']);
        }

        if (isset($restrictions['result'])) {
            if ($restrictions['result'] === 'judging') {
                $queryBuilder->andWhere('j.result IS NULL OR j.endtime IS NULL');
            } else {
                $queryBuilder
                    ->andWhere('j.result = :result')
                    ->setParameter('result', $restrictions['result']);
            }
        }

        if ($this->config->get('data_source') ==
            DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL) {
            // When we are shadow, also load the external results
            $queryBuilder
                ->leftJoin('s.external_judgements', 'ej', Join::WITH, 'ej.valid = 1')
                ->addSelect('ej');
        }

        $submissions = $queryBuilder->getQuery()->getResult();
        if (isset($restrictions['rejudgingid'])) {
            // Doctrine will return an array for each item. At index '0' will
            // be the submission and at index 'oldresult' will be the old
            // result. Remap this.
            $submissions = array_map(function ($submissionData) {
                /** @var Submission $submission */
                $submission = $submissionData[0];
                $submission->setOldResult($submissionData['oldresult']);
                return $submission;
            }, $submissions);
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

        return [$submissions, $counts];
    }

    /**
     * Determines final result for a judging given an ordered array of
     * judging runs. Runs can be NULL if not run yet. A return value of
     * NULL means that a final result cannot be determined yet; this may
     * only occur when not all testcases have been run yet.
     * @param string[] $runresults
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
     * This function takes a (set of) temporary file(s) of a submission,
     * validates it and puts it into the database. Additionally it
     * moves it to a backup storage.
     * @param Team|int            $team
     * @param User|int|null       $user
     * @param ContestProblem|int  $problem
     * @param Contest|int         $contest
     * @param Language|string     $language
     * @param UploadedFile[]      $files
     * @param Submission|int|null $originalSubmission
     * @throws DBALException
     */
    public function submitSolution(
        $team,
        $user,
        $problem,
        $contest,
        $language,
        array $files,
        ?string $source = null,
        ?string $juryMember = null,
        $originalSubmission = null,
        ?string $entryPoint = null,
        ?string $externalId = null,
        ?float $submitTime = null,
        ?string &$message = null
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
            $language = $this->em->getRepository(Language::class)->find($language);
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

        if (count($files) == 0) {
            throw new BadRequestHttpException("No files specified.");
        }
        if (count($files) > $this->config->get('sourcefiles_limit')) {
            $message = "Tried to submit more than the allowed number of source files.";
            return null;
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
            return null;
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

        if (!$language->getAllowSubmit()) {
            throw new BadRequestHttpException(
                sprintf("Language '%s' not found in database or not submittable.", $language->getLangid()));
        }

        if ($language->getRequireEntryPoint() && empty($entryPoint)) {
            $message = sprintf("Entry point required for '%s' but none given.", $language->getLangid());
            return null;
        }

        if ($this->dj->checkrole('jury') && $entryPoint == '__auto__') {
            // Fall back to auto detection when we're importing jury submissions.
            $entryPoint = null;
        }

        if (!empty($entryPoint) && !preg_match(self::FILENAME_REGEX, $entryPoint)) {
            $message = sprintf("Entry point '%s' contains illegal characters.", $entryPoint);
            return null;
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
                return null;
            }
            $totalSize += $file->getSize();

            if ($source !== 'shadowing' && $language->getFilterCompilerFiles()) {
                $matchesExtension = false;
                foreach ($language->getExtensions() as $extension) {
                    $extensionLength = strlen($extension);
                    if (substr($file->getClientOriginalName(), -$extensionLength) === $extension) {
                        $matchesExtension = true;
                        break;
                    }
                }
                if ($matchesExtension) {
                    $extensionMatchCount++;
                }
            }
        }

        if ($source !== 'shadowing' && $language->getFilterCompilerFiles() && $extensionMatchCount === 0) {
            $message = sprintf(
                "None of the submitted files match any of the allowed " .
                "extensions for %s (allowed: %s)",
                $language->getName(), implode(', ', $language->getExtensions())
            );
            return null;
        }

        if ($totalSize > $sourceSize * 1024) {
            $message = sprintf("Submission file(s) are larger than %d kB.", $sourceSize);
            return null;
        }

        $this->logger->info('Submission input verified');

        // First look up any expected results in all submission files to minimize the
        // SQL transaction time below.
        if ($this->dj->checkrole('jury')) {
            $results = null;
            foreach ($files as $rank => $file) {
                $fileResult = self::getExpectedResults(file_get_contents($file->getRealPath()),
                    $this->config->get('results_remap'));
                if ($fileResult === false) {
                        $message = sprintf("Found more than one @EXPECTED_RESULTS@ in file '%s'.",
                            $file->getClientOriginalName());
                        return null;
                }
                if ($fileResult !== null) {
                    if ($results !== null) {
                        $message = sprintf("Found more than one file with @EXPECTED_RESULTS@, e.g. in '%s'.",
                            $file->getClientOriginalName());
                        return null;
                    }
                    $results = $fileResult;
                }
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
            ->setExternalid($externalId);

        // Add expected results from source. We only do this for jury submissions
        // to prevent accidental auto-verification of team submissions.
        if ($this->dj->checkrole('jury') && !empty($results)) {
            $submission->setExpectedResults($results);
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

        $judging = new Judging();
        $judging
            ->setContest($contest)
            ->setSubmission($submission);
        if ($juryMember !== null) {
            $judging->setJuryMember($juryMember);
        }
        $this->em->persist($judging);
        // This is so that we can use the submitid/judgingid below.
        $this->em->flush();

        $this->dj->maybeCreateJudgeTasks($judging,
            $source === 'problem import' ? JudgeTask::PRIORITY_LOW : JudgeTask::PRIORITY_DEFAULT);

        $this->em->wrapInTransaction(function () use ($contest, $submission) {
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
        $contest = $this->em->getRepository(Contest::class)->find($contest->getCid());
        $team    = $this->em->getRepository(Team::class)->find($team->getTeamid());
        $problem = $this->em->getRepository(ContestProblem::class)->find([
            'problem' => $problem->getProblem(),
            'contest' => $problem->getContest(),
        ]);

        $this->scoreboardService->calculateScoreRow($contest, $team, $problem->getProblem());

        $this->dj->alert('submit', sprintf('submission %d: team %d, language %s, problem %d',
                                           $submission->getSubmitid(), $team->getTeamid(),
                                           $language->getLangid(), $problem->getProblem()->getProbid()));

        $this->dj->auditlog('submission', $submission->getSubmitid(), 'added',
            'via ' . $source ?? 'unknown', null, $contest->getCid());

        if (Utils::difftime((float)$contest->getEndtime(), $submitTime) <= 0) {
            $this->logger->info(
                "The contest is closed, submission stored but not processed. [c%d]",
                [ $contest->getCid() ]
            );
        }

        return $submission;
    }

    /**
     * Checks given source file for expected results string
     * @return array|false|null Array of expected results if found, false when multiple matches are found, or null otherwise.
     */
    public static function getExpectedResults(string $source, array $resultsRemap)
    {
        $matchstring = null;
        $pos         = false;
        foreach (self::PROBLEM_RESULT_MATCHSTRING as $pattern) {
            $currentPos = mb_stripos($source, $pattern);
            if ($currentPos !== false) {
                // Check if we find another match after the first one, since
                // that is not allowed.
                if (mb_stripos($source, $pattern, $currentPos+1) !== false) {
                    return false;
                }
                // Check that another pattern did not give a match already.
                if ($pos !== false) {
                    return false;
                }
                $pos = $currentPos;
                $matchstring = $pattern;
            }
        }

        if ($pos === false) {
            return null;
        }

        $beginpos = $pos + mb_strlen($matchstring);
        $endpos   = mb_strpos($source, "\n", $beginpos);
        $str      = mb_substr($source, $beginpos, $endpos - $beginpos);
        $results  = explode(',', trim(mb_strtoupper($str)));

        foreach ($results as $key => $val) {
            $result = self::normalizeExpectedResult($val);
            $lowerResult = mb_strtolower($result);
            if (isset($resultsRemap[$lowerResult])) {
                $result = mb_strtoupper($resultsRemap[$lowerResult]);
            }
            $results[$key] = $result;
        }

        return $results;
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
     * Compute the filename of a given submission. $fileData must be an array
     * that contains the data from submission and submission_file.
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
        /** @var SubmissionFile[] $files */
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

        $filename = 's' . $submission->getSubmitid() . '.zip';

        $response = new StreamedResponse();
        $response->setCallback(function () use ($tmpfname) {
            $fp = fopen($tmpfname, 'rb');
            fpassthru($fp);
            unlink($tmpfname);
        });
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Content-Length', filesize($tmpfname));
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        $response->headers->set('Connection', 'Keep-Alive');
        $response->headers->set('Accept-Ranges', 'bytes');

        return $response;
    }
}
