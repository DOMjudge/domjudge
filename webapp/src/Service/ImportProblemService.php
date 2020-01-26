<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Executable;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\Testcase;
use App\Entity\TestcaseContent;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

/**
 * Class ImportProblemService
 * @package App\Service
 */
class ImportProblemService
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var ConfigurationService
     */
    protected $config;

    /**
     * @var SubmissionService
     */
    protected $submissionService;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService,
        SubmissionService $submissionService,
        ValidatorInterface $validator
    ) {
        $this->em                = $em;
        $this->logger            = $logger;
        $this->dj                = $dj;
        $this->config            = $config;
        $this->eventLogService   = $eventLogService;
        $this->submissionService = $submissionService;
        $this->validator         = $validator;
    }

    /**
     * Import a zipped problem
     * @param ZipArchive   $zip
     * @param              $clientName
     * @param Problem|null $problem
     * @param Contest|null $contest
     * @param array        $messages
     * @return Problem|null
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function importZippedProblem(
        ZipArchive $zip,
        $clientName,
        Problem $problem = null,
        Contest $contest = null,
        array &$messages = []
    ) {
        // This might take a while
        ini_set('max_execution_time', '300');

        $propertiesFile  = 'domjudge-problem.ini';
        $yamlFile        = 'problem.yaml';
        $tleFile         = '.timelimit';
        $submission_file = 'submissions.json';
        $problemIsNew   = $problem === null;

        $iniKeysProblem        = ['name', 'timelimit', 'special_run', 'special_compare'];
        $iniKeysContestProblem = ['allow_submit', 'allow_judge', 'points', 'color'];

        $defaultTimelimit = 10;

        // Read problem properties
        $propertiesString = $zip->getFromName($propertiesFile);
        $properties       = $propertiesString === false ? [] : parse_ini_string($propertiesString);

        // Only preserve valid keys:
        $problemProperties        = array_intersect_key($properties, array_flip($iniKeysProblem));
        $contestProblemProperties = array_intersect_key($properties, array_flip($iniKeysContestProblem));

        // Set timelimit from alternative source:
        if (!isset($problemProperties['timelimit']) &&
            ($str = $zip->getFromName($tleFile)) !== false) {
            $problemProperties['timelimit'] = trim($str);
        }

        // Take problem:externalid from zip filename, and use as backup for
        // problem:name and contestproblem:shortname if these are not specified.
        $externalId = preg_replace('/[^a-zA-Z0-9-_]/', '', basename($clientName, '.zip'));
        if ((string)$externalId === '') {
            throw new InvalidArgumentException("Could not extract an identifier from '$clientName'.");
        }

        if (!array_key_exists('externalid', $problemProperties)) {
            $problemProperties['externalid'] = $externalId;
        }

        $contestProblemProperties['shortname'] = $externalId;

        // Set default of 1 point for a problem if not specified
        if (!isset($contestProblemProperties['points'])) {
            $contestProblemProperties['points'] = 1;
        }

        if (isset($problemProperties['special_compare'])) {
            $problemProperties['compare_executable'] =
                $this->em->getRepository(Executable::class)->find($problemProperties['special_compare']);
            unset($problemProperties['special_compare']);
        }
        if (isset($problemProperties['special_run'])) {
            $problemProperties['run_executable'] =
                $this->em->getRepository(Executable::class)->find($problemProperties['special_run']);
            unset($problemProperties['special_run']);
        }

        /** @var ContestProblem|null $contestProblem */
        $contestProblem = null;
        if ($problem === null) {
            $problem = new Problem();

            // Set sensible defaults for name and timelimit if not specified:
            if (!isset($problemProperties['name'])) {
                $problemProperties['name'] = $contestProblemProperties['shortname'];
            }
            if (!isset($problemProperties['timelimit'])) {
                $problemProperties['timelimit'] = $defaultTimelimit;
            }

            if ($contest !== null) {
                $contestProblem = new ContestProblem();
                $contestProblem
                    ->setProblem($problem)
                    ->setContest($contest);
            }
        } else {
            if ($contest !== null) {
                // Find the correct contest problem
                /** @var ContestProblem $possibleContestProblem */
                foreach ($problem->getContestProblems() as $possibleContestProblem) {
                    if ($possibleContestProblem->getCid() === $contest->getCid()) {
                        $contestProblem = $possibleContestProblem;
                        break;
                    }
                }

                if ($contestProblem === null) {
                    $contestProblem = new ContestProblem();
                    $contestProblem
                        ->setProblem($problem)
                        ->setContest($contest);
                } else {
                    // Don't overwrite the shortname for a contestproblem when
                    // it already exists.
                    unset($contestProblemProperties['shortname']);
                }
            }
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        foreach ($problemProperties as $key => $value) {
            $propertyAccessor->setValue($problem, $key, $value);
        }

        $hasErrors = false;
        $errors    = $this->validator->validate($problem);
        if ($errors->count()) {
            $hasErrors = true;
            /** @var ConstraintViolationInterface $error */
            foreach ($errors as $error) {
                $messages[] = sprintf(
                    'Error: problem.%s: %s',
                    $error->getPropertyPath(),
                    $error->getMessage()
                );
            }
        }

        if ($contestProblem !== null) {
            foreach ($contestProblemProperties as $key => $value) {
                $propertyAccessor->setValue($contestProblem, $key, $value);
            }

            $errors = $this->validator->validate($contestProblem);
            if ($errors->count()) {
                $hasErrors = true;
                /** @var ConstraintViolationInterface $error */
                foreach ($errors as $error) {
                    $messages[] = sprintf(
                        'Error: contestproblem.%s: %s',
                        $error->getPropertyPath(),
                        $error->getMessage()
                    );
                }
            }
        }

        if ($hasErrors) {
            return null;
        }

        // parse problem.yaml
        $problemYaml = $zip->getFromName($yamlFile);
        if ($problemYaml !== false) {
            $yamlData = Yaml::parse($problemYaml);

            if (!empty($yamlData)) {
                if (isset($yamlData['uuid']) && $contestProblem !== null) {
                    $contestProblem->setShortname($yamlData['uuid']);
                }

                $yamlProblemProperties = [];
                if (isset($yamlData['name'])) {
                    if (is_array($yamlData['name'])) {
                        foreach ($yamlData['name'] as $lang => $name) {
                            // TODO: select a specific instead of the first language
                            $yamlProblemProperties['name'] = $name;
                            break;
                        }
                    } else {
                        $yamlProblemProperties['name'] = $yamlData['name'];
                    }
                }
                if (isset($yamlData['validator_flags'])) {
                    $yamlProblemProperties['special_compare_args'] = $yamlData['validator_flags'];
                }

                if (isset($yamlData['validation'])
                    && ($yamlData['validation'] == 'custom' ||
                        $yamlData['validation'] == 'custom interactive')) {
                    // search for validator
                    $validatorFiles = [];
                    for ($j = 0; $j < $zip->numFiles; $j++) {
                        $filename = $zip->getNameIndex($j);
                        if (Utils::startsWith($filename, 'output_validators/') &&
                            !Utils::endsWith($filename, '/')) {
                            $validatorFiles[] = $filename;
                        }
                    }
                    if (sizeof($validatorFiles) == 0) {
                        $messages[] = 'Custom validator specified but not found.';
                    } else {
                        // file(s) have to share common directory
                        $validatorDir = mb_substr($validatorFiles[0], 0, mb_strrpos($validatorFiles[0], '/')) . '/';
                        $sameDir      = true;
                        foreach ($validatorFiles as $validatorFile) {
                            if (!Utils::startsWith($validatorFile, $validatorDir)) {
                                $sameDir    = false;
                                $messages[] = sprintf('%s does not start with %s.',
                                                      $validatorFile, $validatorDir);
                                break;
                            }
                        }
                        if (!$sameDir) {
                            $messages[] = 'Found multiple custom output validators.';
                        } else {
                            $tmpzipfiledir = exec("mktemp -d --tmpdir=" .
                                                  $this->dj->getDomjudgeTmpDir(),
                                                  $dontcare, $retval);
                            if ($retval != 0) {
                                throw new ServiceUnavailableHttpException(
                                    null, 'failed to create temporary directory'
                                );
                            }
                            chmod($tmpzipfiledir, 0700);
                            foreach ($validatorFiles as $validatorFile) {
                                $content     = $zip->getFromName($validatorFile);
                                $filebase    = basename($validatorFile);
                                $newfilename = $tmpzipfiledir . "/" . $filebase;
                                file_put_contents($newfilename, $content);
                                if ($filebase === 'build' || $filebase === 'run') {
                                    // mark special files as executable
                                    chmod($newfilename, 0755);
                                }
                            }

                            exec("zip -r -j '$tmpzipfiledir/outputvalidator.zip' '$tmpzipfiledir'",
                                 $dontcare, $retval);
                            if ($retval != 0) {
                                throw new ServiceUnavailableHttpException(
                                    null, 'failed to create zip file for output validator.'
                                );
                            }

                            $outputValidatorZip  = file_get_contents($tmpzipfiledir.'/outputvalidator.zip');
                            $outputValidatorName = substr($externalId, 0, 20) . '_cmp';
                            if ($this->em->getRepository(Executable::class)->find($outputValidatorName)) {
                                // avoid name clash
                                $clashCount = 2;
                                while ($this->em->getRepository(Executable::class)->find(
                                    $outputValidatorName . '_' . $clashCount)) {
                                    $clashCount++;
                                }
                                $outputValidatorName = $outputValidatorName . "_" . $clashCount;
                            }

                            $combinedRunCompare = $yamlData['validation'] == 'custom interactive';
                            $executable         = new Executable();
                            $executable
                                ->setExecid($outputValidatorName)
                                ->setMd5sum(md5($outputValidatorZip))
                                ->setZipfile($outputValidatorZip)
                                ->setDescription(sprintf('output validator for %s', $problem->getName()))
                                ->setType($combinedRunCompare ? 'run' : 'compare');
                            $this->em->persist($executable);

                            if ($combinedRunCompare) {
                                $problem->setCombinedRunCompare(true);
                                $problem->setRunExecutable($executable);
                            } else {
                                $problem->setCompareExecutable($executable);
                            }

                            $messages[] = "Added output validator '$outputValidatorName'";
                        }
                    }
                }

                if (isset($yamlData['limits'])) {
                    if (isset($yamlData['limits']['memory'])) {
                        $yamlProblemProperties['memlimit'] = 1024 * $yamlData['limits']['memory'];
                    }
                    if (isset($yamlData['limits']['output'])) {
                        $yamlProblemProperties['outputlimit'] = 1024 * $yamlData['limits']['output'];
                    }
                }

                foreach ($yamlProblemProperties as $key => $value) {
                    $propertyAccessor->setValue($problem, $key, $value);
                }
            }
        }

        // Add problem statement, also look in obsolete location
        foreach (['', 'problem_statement/'] as $dir) {
            foreach (['pdf', 'html', 'txt'] as $type) {
                $filename = sprintf('%sproblem.%s', $dir, $type);
                $text     = $zip->getFromName($filename);
                if ($text !== false) {
                    $problem
                        ->setProblemtext($text)
                        ->setProblemtextType($type);
                    $messages[] = "Added problem statement from: <tt>$filename</tt>";
                    break;
                }
            }
        }

        // Insert/update testcases
        if ($problem->getProbid()) {
            // Find the current max rank
            $maxRank = (int)$this->em->createQueryBuilder()
                ->from(Testcase::class, 't')
                ->select('MAX(t.rank)')
                ->andWhere('t.problem = :problem')
                ->setParameter(':problem', $problem)
                ->getQuery()
                ->getSingleScalarResult();
            $rank    = $maxRank + 1;
        } else {
            $rank = 1;
        }

        /** @var Testcase[] $testcases */
        $testcases = [];

        // first insert sample, then secret data in alphabetical order
        foreach (['sample', 'secret'] as $type) {
            $numCases  = 0;
            $dataFiles = [];
            for ($j = 0; $j < $zip->numFiles; $j++) {
                $filename = $zip->getNameIndex($j);
                if (Utils::startsWith($filename, sprintf('data/%s/', $type)) &&
                    Utils::endsWith($filename, '.in')) {
                    $fileout  = preg_replace("/\.in$/", ".ans", $filename);
                    if ($zip->locateName($fileout) !== false) {
                        $basename = basename($filename, ".in");
                        $dirname = dirname($filename);
                        $dataFiles[] = preg_replace("|^data/$type/|", "", sprintf('%s/%s', $dirname, $basename));
                    }
                }
            }
            asort($dataFiles);

            foreach ($dataFiles as $dataFile) {
                $testIn      = $zip->getFromName(sprintf('data/%s/%s.in', $type, $dataFile));
                $testOut     = $zip->getFromName(sprintf('data/%s/%s.ans', $type, $dataFile));
                $imageFile = $imageType = $imageThumb = false;
                foreach (['png', 'jpg', 'jpeg', 'gif'] as $imgExtension) {
                    $imageFileName = sprintf('data/%s/%s.%s', $type, $dataFile, $imgExtension);
                    if (($imageFile = $zip->getFromName($imageFileName)) !== false) {
                        $imageType = Utils::getImageType($imageFile, $errormsg);
                        if ($imageType === false) {
                            $messages[] = sprintf("reading '%s': %s", $imageFileName, $errormsg);
                            $imageFile  = false;
                        } elseif ($imageType !== ($imgExtension == 'jpg' ? 'jpeg' : $imgExtension)) {
                            $messages[] = sprintf("extension of '%s' does not match type '%s'",
                                                  $imageFileName, $imageType);
                            $imageFile  = false;
                        } else {
                            $thumbnailSize = $this->config->get('thumbnail_size');
                            $imageThumb    = Utils::getImageThumb(
                                $imageFile, $thumbnailSize,
                                $this->dj->getDomjudgeTmpDir(),
                                $errormsg
                            );
                            if ($imageThumb === false) {
                                $imageThumb = null;
                                $messages[] = sprintf("reading '%s': %s", $imageFileName, $errormsg);
                            }
                        }
                        break;
                    }
                }

                $md5in  = md5($testIn);
                $md5out = md5($testOut);

                if ($problem->getProbid()) {
                    // Skip testcases that already exist identically
                    $existingTestcase = $this->em
                        ->createQueryBuilder()
                        ->from(Testcase::class, 't')
                        ->select('t')
                        ->andWhere('t.md5sum_input = :inputmd5')
                        ->andWhere('t.md5sum_output = :outputmd5')
                        ->andWhere('t.sample = :sample')
                        ->andWhere('t.orig_input_filename = :orig_input_filename')
                        ->andWhere('t.problem = :problem')
                        ->setParameter(':inputmd5', $md5in)
                        ->setParameter(':outputmd5', $md5out)
                        ->setParameter(':sample', $type === 'sample')
                        ->setParameter(':orig_input_filename', $dataFile)
                        ->setParameter(':problem', $problem)
                        ->getQuery()
                        ->getOneOrNullResult();

                    if (isset($existingTestcase)) {
                        $messages[] = sprintf('Skipped %s testcase <tt>%s</tt>: already exists', $type, $dataFile);
                        continue;
                    }
                }

                $testcase        = new Testcase();
                $testcaseContent = new TestcaseContent();
                $testcase
                    ->setContent($testcaseContent)
                    ->setProblem($problem)
                    ->setRank($rank)
                    ->setSample($type === 'sample')
                    ->setMd5sumInput($md5in)
                    ->setMd5sumOutput($md5out)
                    ->setOrigInputFilename($dataFile);
                $testcaseContent
                    ->setInput($testIn)
                    ->setOutput($testOut);
                if (($descriptionFile = $zip->getFromName(sprintf('data/%s/%s.desc', $type, $dataFile))) !== false) {
                    $testcase->setDescription($descriptionFile);
                }
                if ($imageFile !== false) {
                    $testcase->setImageType($imageType);
                    $testcaseContent
                        ->setImage($imageFile)
                        ->setImageThumb($imageThumb);
                }
                $this->em->persist($testcase);

                $rank++;
                $numCases++;

                $testcases[] = $testcase;

                $messages[] = sprintf('Added %s testcase from: <tt>%s.{in,ans}</tt>', $type, $dataFile);
            }
            $messages[] = sprintf("Added %d %s testcase(s).", $numCases, $type);
        }

        $this->em->persist($problem);
        $this->em->flush();
        if ($contestProblem) {
            $contestProblem->setProblem($problem);
            $contestProblem->setContest($contest);
            $this->em->persist($contestProblem);
        }

        $this->em->flush();

        $cid = $contest ? $contest->getCid() : null;
        $this->eventLogService->log('problem', $problem->getProbid(), $problemIsNew ? 'create' : 'update', $cid);

        foreach ($testcases as $testcase) {
            $this->eventLogService->log('testcase', $testcase->getTestcaseid(), 'create');
        }

        // submit reference solutions
        if ($contest === null) {
            $messages[] = 'No jury solutions added: problem is not linked to a contest (yet).';
        } elseif (!$this->dj->getUser()->getTeam()) {
            $messages[] = 'No jury solutions added: must associate team with your user first.';
        } elseif ($contestProblem->getAllowSubmit()) {
            // First find all submittable languages:
            /** @var Language[] $allowedLanguages */
            $allowedLanguages = $this->em->createQueryBuilder()
                ->from(Language::class, 'l', 'l.langid')
                ->select('l')
                ->andWhere('l.allowSubmit = true')
                ->getQuery()
                ->getResult();

            // Read submission details from optional file.
            $submission_file_string = $zip->getFromName($submission_file);
            $submission_details = $submission_file_string===FALSE ? array() :
                $this->dj->jsonDecode($submission_file_string);

            $numJurySolutions = 0;
            for ($j = 0; $j < $zip->numFiles; $j++) {
                $path = $zip->getNameIndex($j);
                if (!Utils::startsWith($path, 'submissions/')) {
                    // Skipping non-submission files silently.
                    continue;
                }
                $pathComponents = explode('/', $path);
                if (!((count($pathComponents) == 3 && !empty($pathComponents[2])) ||
                    (count($pathComponents) == 4 && empty($pathComponents[3])))) {
                    // Skipping files and directories at the wrong level.
                    // Note that multi-file submissions sit in a subdirectory.
                    continue;
                }

                if (count($pathComponents) == 3) {
                    // Single file submission
                    $files   = [$pathComponents[2]];
                    $indices = [$j];
                } else {
                    // Multi file submission
                    $files   = [];
                    $indices = [];
                    $length  = mb_strrpos($path, '/') + 1;
                    $prefix  = mb_substr($path, 0, $length);
                    for ($k = 0; $k < $zip->numFiles; $k++) {
                        $file = $zip->getNameIndex($k);
                        // Only allow multi-file submission with all files directly under the directory.
                        if (strncmp($prefix, $file, $length) == 0 && mb_strlen($file) > $length &&
                            mb_strrpos($file, '/') + 1 == $length) {
                            $files[]   = mb_substr($file, $length);
                            $indices[] = $k;
                        }
                    }
                }

                unset($languageToUse);
                foreach ($files as $file) {
                    $parts = explode(".", $file);
                    if (count($parts) == 1) {
                        continue;
                    }
                    $extension = end($parts);
                    foreach ($allowedLanguages as $key => $language) {
                        if (in_array($extension, $language->getExtensions())) {
                            $languageToUse = $key;
                            break 2;
                        }
                    }
                }
                if (isset($submission_details[$path]['langid'])) {
                    $languageToUse = $submission_details[$path]['langid'];
                }

                $tmpDir = $this->dj->getDomjudgeTmpDir();

                if (empty($languageToUse)) {
                    $messages[] = "Could not add jury solution <tt>$path</tt>: unknown language.";
                } else {
                    $expectedResult = SubmissionService::normalizeExpectedResult($pathComponents[1]);
                    $results        = null;
                    $totalSize      = 0;
                    $filesToSubmit  = [];
                    $tempFiles      = [];
                    for ($k = 0; $k < count($files); $k++) {
                        $source = $zip->getFromIndex($indices[$k]);
                        if ($results === null) {
                            $results = SubmissionService::getExpectedResults($source,
                                $this->config->get('results_remap'));
                        }
                        if (!($tempFileName = tempnam($tmpDir, 'ref_solution-'))) {
                            throw new ServiceUnavailableHttpException(null,
                                sprintf('Could not create temporary file in directory %s', $tmpDir));
                        }
                        if (file_put_contents($tempFileName, $source) === false) {
                            throw new ServiceUnavailableHttpException(
                                null,
                                sprintf("Could not write to temporary file '%s'.", $tempFileName)
                            );
                        }
                        $filesToSubmit[] = new UploadedFile($tempFileName, $files[$k], null, null, true);
                        $totalSize       += filesize($tempFileName);
                        $tempFiles[]     = $tempFileName;
                    }
                    if ($results === null) {
                        $results[] = $expectedResult;
                    } elseif (!in_array($expectedResult, $results)) {
                        $messages[] = sprintf("Annotated result '%s' does not match directory for %s",
                                              implode(', ', $results), $path);
                    } elseif (!empty($expectedResult)) {
                        $results = [$expectedResult];
                    }
                    $jury_team_id = $this->dj->getUser()->getTeamid();
                    if (isset($submission_details[$path]['team'])) {
                        $json_team = $this->em->getRepository(Team::class)
                            ->findOneBy(['name' => $submission_details[$path]['team']]);
                        if (isset($json_team)) {
                            $json_team_id = $json_team->getTeamid();
                            if (isset($json_team_id)) {
                                $jury_team_id = $json_team_id;
                            }
                        }
                    }
                    $entry_point = '__auto__';
                    if (isset($submission_details[$path]['entry_point'])) {
                        $entry_point = $submission_details[$path]['entry_point'];
                    }
                    if ($totalSize <= $this->config->get('sourcesize_limit') * 1024) {
                        $contest        = $this->em->getRepository(Contest::class)->find(
                            $contest->getCid());
                        $team           = $this->em->getRepository(Team::class)->find($jury_team_id);
                        $contestProblem = $this->em->getRepository(ContestProblem::class)->find(
                            [
                                'problem' => $problem,
                                'contest' => $contest,
                            ]
                        );
                        $submission     = $this->submissionService->submitSolution(
                            $team, $contestProblem, $contest, $languageToUse, $filesToSubmit,
                            null, $entry_point, null, null, $submissionMessage
                        );

                        if (!$submission) {
                            $messages[] = $submissionMessage;
                            return null;
                        }
                        $submission = $this->em->getRepository(Submission::class)->find($submission->getSubmitid());
                        $submission->setExpectedResults($results);
                        // Flush changes to submission
                        $this->em->flush();

                        $messages[] = "Added jury solution from: <tt>$path</tt></li>";
                        $numJurySolutions++;
                    } else {
                        $messages[] = "Could not add jury solution <tt>$path</tt>: too large.";
                    }

                    foreach ($tempFiles as $f) {
                        unlink($f);
                    }
                }
            }

            $messages[] = sprintf('Added %d jury solution(s).', $numJurySolutions);
        } else {
            $messages[] = 'No jury solutions added: problem not submittable';
        }

        $messages[] = sprintf('Saved problem %d', $problem->getProbid());

        return $problem;
    }
}
