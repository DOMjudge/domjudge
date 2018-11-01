<?php declare(strict_types=1);

namespace DOMJudgeBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\ContestProblem;
use DOMJudgeBundle\Entity\JudgingRun;
use DOMJudgeBundle\Entity\Language;
use DOMJudgeBundle\Entity\Submission;
use DOMJudgeBundle\Entity\SubmissionFileWithSourceCode;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Utils\FreezeData;
use DOMJudgeBundle\Utils\Utils;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class SubmissionService
 * @package DOMJudgeBundle\Service
 */
class SubmissionService
{
    const FILENAME_REGEX = '/^[a-zA-Z0-9][a-zA-Z0-9+_\.-]*$/';
    const PROBLEM_RESULT_MATCHSTRING = ['@EXPECTED_RESULTS@: ', '@EXPECTED_SCORE@: '];
    const PROBLEM_RESULT_REMAP = [
        'ACCEPTED' => 'CORRECT',
        'WRONG_ANSWER' => 'WRONG-ANSWER',
        'TIME_LIMIT_EXCEEDED' => 'TIMELIMIT',
        'RUN_TIME_ERROR' => 'RUN-ERROR'
    ];

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var DOMJudgeService
     */
    protected $DOMJudgeService;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * @var ScoreboardService
     */
    protected $scoreboardService;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        DOMJudgeService $DOMJudgeService,
        EventLogService $eventLogService,
        ScoreboardService $scoreboardService,
        string $rootDir
    ) {
        $this->entityManager     = $entityManager;
        $this->logger            = $logger;
        $this->DOMJudgeService   = $DOMJudgeService;
        $this->eventLogService   = $eventLogService;
        $this->scoreboardService = $scoreboardService;

        // TODO: do this in a correct fashion using a Makefile
        $dir          = realpath($rootDir . '/../../etc/');
        $staticConfig = $dir . '/domserver-static.php';
        require_once $staticConfig;
    }

    /**
     * Determines final result for a judging given an ordered array of
     * judging runs. Runs can be NULL if not run yet. A return value of
     * NULL means that a final result cannot be determined yet; this may
     * only occur when not all testcases have been run yet.
     * @param JudgingRun[] $runs
     * @param array        $resultsPrio
     * @return string|null
     */
    public function getFinalResult(array $runs, array $resultsPrio)
    {
        // Whether we have NULL results
        $haveNullResult = false;

        // This stores the current result and priority to be returned:
        $bestRun      = null;
        $bestPriority = -1;

        foreach ($runs as $testCase => $run) {
            if ($run === null) {
                $haveNullResult = true;
            } else {
                $priority = $resultsPrio[$run->getRunresult()];
                if (empty($priority)) {
                    throw new \InvalidArgumentException(
                        sprintf("Unknown results '%s' found", $run->getRunresult()));
                }
                if ($priority > $bestPriority) {
                    $bestRun      = $run;
                    $bestPriority = $priority;
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

        return $bestRun ? $bestRun->getRunresult() : null;
    }

    /**
     * This function takes a (set of) temporary file(s) of a submission,
     * validates it and puts it into the database. Additionally it
     * moves it to a backup storage.
     * @param Team|int           $team
     * @param ContestProblem|int $problem
     * @param Contest|int        $contest
     * @param Language|string    $language
     * @param UploadedFile[]     $files
     * @param int|null           $originalSubmitId
     * @param string|null        $entryPoint
     * @param string|null        $externalId
     * @param float|null         $submitTime
     * @param string|null        $externalResult
     * @return Submission
     * @throws \Exception
     */
    public function submitSolution(
        $team,
        $problem,
        $contest,
        $language,
        array $files,
        $originalSubmitId,
        string $entryPoint = null,
        $externalId = null,
        float $submitTime = null,
        $externalResult = null
    ) {
        if (!$team instanceof Team) {
            $team = $this->entityManager->getRepository(Team::class)->find($team);
        }
        if (!$contest instanceof Contest) {
            $contest = $this->entityManager->getRepository(Contest::class)->find($contest);
        }
        if (!$problem instanceof ContestProblem) {
            $problem = $this->entityManager->getRepository(ContestProblem::class)->find([
                                                                                            'cid' => $contest->getCid(),
                                                                                            'probid' => $problem
                                                                                        ]);
        }
        if (!$language instanceof Language) {
            $language = $this->entityManager->getRepository(Language::class)->find($language);
        }

        if (empty($team)) {
            throw new \BadMethodCallException("Team not found");
        }
        if (empty($problem)) {
            throw new \BadMethodCallException("Problem not found");
        }
        if (empty($contest)) {
            throw new \BadMethodCallException("Contest not found");
        }
        if (empty($language)) {
            throw new \BadMethodCallException("Language not found");
        }

        if (empty($submitTime)) {
            $submitTime = Utils::now();
        }

        if (count($files) == 0) {
            throw new \BadMethodCallException("No files specified.");
        }
        if (count($files) > $this->DOMJudgeService->dbconfig_get('sourcefiles_limit', 100)) {
            throw new \BadMethodCallException("Tried to submit more than the allowed number of source files.");
        }

        $filenames = [];
        foreach ($files as $file) {
            if (!$file->isValid()) {
                throw new \BadMethodCallException($file->getErrorMessage());
            }
            $filenames[$file->getClientOriginalName()] = $file->getClientOriginalName();
        }

        if (count($files) != count($filenames)) {
            throw new \BadMethodCallException("Duplicate filenames detected.");
        }

        $sourceSize = $this->DOMJudgeService->dbconfig_get('sourcesize_limit');

        $freezeData = new FreezeData($contest);
        if (!$this->DOMJudgeService->checkrole('jury') && !$freezeData->started()) {
            throw new \BadMethodCallException(
                sprintf("The contest is closed, no submissions accepted. [c%d]", $contest->getCid()));
        }

        if (!$language->getAllowSubmit()) {
            throw new \BadMethodCallException(
                sprintf("Language '%s' not found in database or not submittable.", $language->getLangid()));
        }

        if ($language->getRequireEntryPoint() && empty($entryPoint)) {
            throw new \BadMethodCallException(
                sprintf("Entry point required for '%s' but none given.", $language->getLangid()));
        }

        if ($this->DOMJudgeService->checkrole('jury') && $entryPoint == '__auto__') {
            // Fall back to auto detection when we're importing jury submissions.
            $entryPoint = null;
        }

        if (!empty($entryPoint) && !preg_match(self::FILENAME_REGEX, $entryPoint)) {
            throw new \BadMethodCallException(sprintf("Entry point '%s' contains illegal characters."), $entryPoint);
        }

        if (!$this->DOMJudgeService->checkrole('jury') && !$team->getEnabled()) {
            throw new \BadMethodCallException(
                sprintf("Team '%d' not found in database or not enabled.", $team->getTeamid()));
        }

        if (!$problem->getAllowSubmit()) {
            throw new \BadMethodCallException(
                sprintf("Problem p%d not submittable [c%d].",
                        $problem->getProbid(), $contest->getCid()));
        }

        // Reindex array numerically to make sure we can index it in onder
        $files = array_values($files);

        $totalSize = 0;
        foreach ($files as $file) {
            if (!$file->isReadable()) {
                throw new \BadMethodCallException("File '%s' not found (or not readable).", $file->getRealPath());
            }
            if (!preg_match(self::FILENAME_REGEX, $file->getClientOriginalName())) {
                throw new \BadMethodCallException(sprintf("Illegal filename '%s'.", $file->getClientOriginalName()));
            }
            $totalSize += $file->getSize();
        }

        if ($totalSize > $sourceSize * 1024) {
            throw new \BadMethodCallException(sprintf("Submission file(s) are larger than %d kB.", $sourceSize));
        }

        $this->logger->info('input verified');

        // First look up any expected results in file, so as to minimize the SQL transaction time below.
        if ($this->DOMJudgeService->checkrole('jury')) {
            $results = $this->getExpectedResults(file_get_contents($files[0]->getRealPath()));
        }

        $submission = new Submission();
        $submission
            ->setTeam($team)
            ->setContestProblem($problem)
            ->setLanguage($language)
            ->setSubmittime($submitTime)
            ->setOrigsubmitid($originalSubmitId)
            ->setEntryPoint($entryPoint)
            ->setExternalid($externalId)
            ->setExternalresult($externalResult);

        // Add expected results from source. We only do this for jury submissions
        // to prevent accidental auto-verification of team submissions.
        if ($this->DOMJudgeService->checkrole('jury') && !empty($results)) {
            $submission->setExpectedResults($results);
        }
        $this->entityManager->persist($submission);

        foreach ($files as $rank => $file) {
            $submissionFile = new SubmissionFileWithSourceCode();
            $submissionFile
                ->setFilename($file->getClientOriginalName())
                ->setRank($rank)
                ->setSourcecode(file_get_contents($file->getRealPath()));
            $submissionFile->setSubmission($submission);
            $this->entityManager->persist($submissionFile);
        }

        $this->entityManager->transactional(function () use ($contest, $submission) {
            $this->entityManager->flush();
            $this->eventLogService->log('submission', $submission->getSubmitid(),
                                        EventLogService::ACTION_CREATE, $contest->getCid());
        });

        // Reload contest, team and contestproblem for now, as EventLogService::log will clear the Doctrine entity manager
        $contest = $this->entityManager->getRepository(Contest::class)->find($contest->getCid());
        $team    = $this->entityManager->getRepository(Team::class)->find($team->getTeamid());
        $problem = $this->entityManager->getRepository(ContestProblem::class)->find([
                                                                                        'probid' => $problem->getProbid(),
                                                                                        'cid' => $problem->getCid()
                                                                                    ]);

        $this->scoreboardService->calculateScoreRow($contest, $team, $problem->getProblem());

        $this->DOMJudgeService->alert('submit', sprintf('submission %d: team %d, language %s, problem %d',
                                                        $submission->getSubmitid(), $team->getTeamid(),
                                                        $language->getLangid(), $problem->getProbid()));

        if (is_writable(SUBMITDIR)) {
            // Copy the submission to SUBMITDIR for safe-keeping
            foreach ($files as $rank => $file) {
                $fdata  = [
                    'cid' => $contest->getCid(),
                    'submitid' => $submission->getSubmitid(),
                    'teamid' => $team->getTeamid(),
                    'probid' => $problem->getProbid(),
                    'langid' => $language->getLangid(),
                    'rank' => $rank,
                    'filename' => $file->getClientOriginalName()
                ];
                $toFile = SUBMITDIR . '/' . $this->getSourceFilename($fdata);
                if (!@copy($file->getRealPath(), $toFile)) {
                    $this->logger->warning(sprintf("Could not copy '%s' to '%s'", $file->getRealPath(), $toFile));
                }
            }
        } else {
            $this->logger->debug('SUBMITDIR not writable, skipping');
        }

        if (Utils::difftime((float)$contest->getEndtime(), $submitTime) <= 0) {
            $this->logger->info(
                sprintf("The contest is closed, submission stored but not processed. [c%d]", $contest->getCid()));
        }

        return $submission;
    }

    /**
     * Checks given source file for expected results string
     * @param string $source
     * @return array|null Array of expected results if found or null otherwise
     */
    protected function getExpectedResults(string $source)
    {
        $matchstring = null;
        $pos         = false;
        foreach (self::PROBLEM_RESULT_MATCHSTRING as $matchstring) {
            if (($pos = mb_stripos($source, $matchstring)) !== false) {
                break;
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
            $results[$key] = $this->normalizeExpectedResult($val);
        }

        return $results;
    }

    /**
     * Normalize the given expected result
     * @param string $result
     * @return string
     */
    protected function normalizeExpectedResult(string $result): string
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
     * @param array $fileData
     * @return string
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
}
