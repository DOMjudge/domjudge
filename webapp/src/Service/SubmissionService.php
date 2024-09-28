<?php declare(strict_types=1);

namespace App\Service;

use App\DataTransferObject\SubmissionRestriction;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\JudgeTask;
use App\Entity\Judging;
use App\Entity\Language;
use App\Entity\Problem;
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

class SubmissionService
{
    final public const FILENAME_REGEX = '/^[a-zA-Z0-9][a-zA-Z0-9+_\.-]*$/';
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
        protected readonly ScoreboardService $scoreboardService
    ) {}

    /**
     * Get a list of submissions that can be displayed in the interface using
     * the submission_list partial.
     *
     * @param Contest[] $contests
     *
     * @return array{Submission[], array<string, int>} array An array with
     *           two elements: the first one is the list of submissions
     *           and the second one is an array with counts.
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getSubmissionList(
        array $contests,
        SubmissionRestriction $restrictions,
        int $limit = 0,
        bool $showShadowUnverified = false
    ): array {
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

        if ($restrictions->withExternalId ?? false) {
            $queryBuilder->andWhere('s.externalid IS NOT NULL');
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
                $queryBuilder->andWhere('j.result != ej.result');
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

        if (isset($restrictions->userId)) {
            $queryBuilder
                ->andWhere('s.user = :userid')
                ->setParameter('userid', $restrictions->userId);
        }

        if (isset($restrictions->categoryId)) {
            $queryBuilder
                ->andWhere('t.category = :categoryid')
                ->setParameter('categoryid', $restrictions->categoryId);
        }

        if (isset($restrictions->visible)) {
            $queryBuilder
                ->innerJoin('t.category', 'cat')
                ->andWhere('cat.visible = true');
        }

        if (isset($restrictions->problemId)) {
            $queryBuilder
                ->andWhere('s.problem = :probid')
                ->setParameter('probid', $restrictions->problemId);
        }

        if (isset($restrictions->languageId)) {
            $queryBuilder
                ->andWhere('s.language = :langid')
                ->setParameter('langid', $restrictions->languageId);
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

        if ($this->dj->shadowMode()) {
            // When we are shadow, also load the external results
            $queryBuilder
                ->leftJoin('s.external_judgements', 'ej', Join::WITH, 'ej.valid = 1')
                ->addSelect('ej');
        }

        $submissions = $queryBuilder->getQuery()->getResult();
        if (isset($restrictions->rejudgingId)) {
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
            ->join('t.category', 'tc')
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
        ?string $source = null,
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

        if (!$language->getAllowSubmit()) {
            throw new BadRequestHttpException(
                sprintf("Language '%s' not found in database or not submittable.", $language->getLangid()));
        }

        if ($language->getRequireEntryPoint() && empty($entryPoint)) {
            $message = sprintf("Entry point required for '%s' but none given.", $language->getLangid());
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
            if ($forceImportInvalid) {
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
                if ($forceImportInvalid) {
                    $importError = $message;
                } else {
                    return null;
                }
            }
            $totalSize += $file->getSize();

            if ($source !== 'shadowing' && $language->getFilterCompilerFiles()) {
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

        if ($source !== 'shadowing' && $language->getFilterCompilerFiles() && $extensionMatchCount === 0) {
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
            if ($forceImportInvalid) {
                $importError = $message;
            } else {
                return null;
            }
        }

        $this->logger->info('Submission input verified');

        // First look up any expected results in all submission files to minimize the
        // SQL transaction time below.
        // Only do this for problem import submissions, as we do not want this for re-submitted submissions nor
        // submissions that come through the API, e.g. when doing a replay of an old contest.
        if ($this->dj->checkrole('jury') && $source == 'problem import') {
            $results = null;
            foreach ($files as $file) {
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
            ->setExternalid($externalId)
            ->setImportError($importError);

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

            $this->dj->maybeCreateJudgeTasks($judging,
                $source === 'problem import' ? JudgeTask::PRIORITY_LOW : JudgeTask::PRIORITY_DEFAULT);
        }

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

        $this->dj->auditlog('submission', $submission->getSubmitid(), 'added',
            'via ' . ($source ?? 'unknown'), null, $contest->getCid());

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
     *
     * @param array<string, string> $resultsRemap
     * @return string[]|false|null Array of expected results if found, false when multiple matches are found, or null otherwise.
     */
    public static function getExpectedResults(string $source, array $resultsRemap): array|false|null
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

        return Utils::streamZipFile($tmpfname, 's' . $submission->getSubmitid() . '.zip');
    }

    public function getSubmissionFileResponse(Submission $submission): StreamedResponse
    {
        /** @var SubmissionFile[] $files */
        $files = $submission->getFiles();
        
        if (count($files) !== 1) {
            throw new ServiceUnavailableHttpException(null, 'Submission does not contain exactly one file.');
        }

        $file = $files[0];
        $filename = $file->getFilename();
        $sourceCode = $file->getSourcecode();

        return new StreamedResponse(function () use ($sourceCode) {
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
