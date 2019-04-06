<?php declare(strict_types=1);

namespace DOMJudgeBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\ContestProblem;
use DOMJudgeBundle\Entity\Executable;
use DOMJudgeBundle\Entity\Language;
use DOMJudgeBundle\Entity\Problem;
use DOMJudgeBundle\Entity\Submission;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Entity\TestcaseWithContent;
use DOMJudgeBundle\Utils\Utils;
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
 * @package DOMJudgeBundle\Service
 */
class ImportProblemService
{
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
    protected $dj;

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
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        DOMJudgeService $dj,
        EventLogService $eventLogService,
        SubmissionService $submissionService,
        ValidatorInterface $validator
    ) {
        $this->entityManager     = $entityManager;
        $this->logger            = $logger;
        $this->dj                = $dj;
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
     * @param string|null  $errorMessage
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
        array &$messages = [],
        string &$errorMessage = null
    ) {
        // This might take a while
        ini_set('max_execution_time', '300');

        $propertiesFile = 'domjudge-problem.ini';
        $yamlFile       = 'problem.yaml';
        $tleFile        = '.timelimit';
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
        if (!isset($problemProperties['timelimit']) && ($str = $zip->getFromName($tleFile)) !== false) {
            $problemProperties['timelimit'] = trim($str);
        }

        // Take problem:externalid from zip filename, and use as backup for
        // problem:name and contestproblem:shortname if these are not specified.
        $externalId = preg_replace('/[^a-zA-Z0-9-_]/', '', basename($clientName, '.zip'));
        if ((string)$externalId === '') {
            throw new \InvalidArgumentException(sprintf("Could not extract an identifier from '%s'.", $clientName));
        }

        if (!array_key_exists('externalid', $problemProperties)) {
            $problemProperties['externalid'] = $externalId;
        }

        // Rename old probid to contestproblem:shortname
        if (isset($contestProblemProperties['probid'])) {
            $shortname = $contestProblemProperties['probid'];
            unset($contestProblemProperties['probid']);
            $contestProblemProperties['shortname'] = $shortname;
        } else {
            $contestProblemProperties['shortname'] = $externalId;
        }

        // Set default of 1 point for a problem if not specified
        if (!isset($contestProblemProperties['points'])) {
            $contestProblemProperties['points'] = 1;
        }

        if (isset($problemProperties['special_compare'])) {
            $problemProperties['compare_executable'] = $this->entityManager->getRepository(Executable::class)->find($problemProperties['special_compare']);
            unset($problemProperties['special_compare']);
        }
        if (isset($problemProperties['special_run'])) {
            $problemProperties['run_executable'] = $this->entityManager->getRepository(Executable::class)->find($problemProperties['special_run']);
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
                $messages[] = sprintf('Error: problem.%s: %s', $error->getPropertyPath(), $error->getMessage());
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
                    $messages[] = sprintf('Error: contestproblem.%s: %s', $error->getPropertyPath(),
                                          $error->getMessage());
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
                    && ($yamlData['validation'] == 'custom' || $yamlData['validation'] == 'custom interactive')) {
                    // search for validator
                    $validatorFiles = [];
                    for ($j = 0; $j < $zip->numFiles; $j++) {
                        $filename = $zip->getNameIndex($j);
                        if (Utils::startsWith($filename, 'output_validators/') && !Utils::endsWith($filename, '/')) {
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
                                $messages[] = sprintf('%s does not start with %s.', $validatorFile,
                                                      $validatorDir);
                                break;
                            }
                        }
                        if (!$sameDir) {
                            $messages[] = 'Found multiple custom output validators.';
                        } else {
                            $tmpzipfiledir = exec("mktemp -d --tmpdir=" . $this->dj->getDomjudgeTmpDir(),
                                                  $dontcare, $retval);
                            if ($retval != 0) {
                                throw new ServiceUnavailableHttpException(null, 'failed to create temporary directory');
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

                            exec("zip -r -j '$tmpzipfiledir/outputvalidator.zip' '$tmpzipfiledir'", $dontcare, $retval);
                            if ($retval != 0) {
                                throw new ServiceUnavailableHttpException(null,
                                                                          'failed to create zip file for output validator.');
                            }

                            $outputValidatorZip  = file_get_contents(sprintf('%s/outputvalidator.zip', $tmpzipfiledir));
                            $outputValidatorName = $externalId . '_cmp';
                            if ($this->entityManager->getRepository(Executable::class)->find($outputValidatorName)) {
                                // avoid name clash
                                $clashCount = 2;
                                while ($this->entityManager->getRepository(Executable::class)->find($outputValidatorName . '_' . $clashCount)) {
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
                            $this->entityManager->persist($executable);

                            if ($combinedRunCompare) {
                                $problem->setCombinedRunCompare(true);
                                $problem->setRunExecutable($executable);
                            } else {
                                $problem->setCompareExecutable($executable);
                            }

                            $messages[] = sprintf("Added output validator '%s'", $outputValidatorName);
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
                    $messages[] = sprintf('Added problem statement from: <tt>%s</tt>', $filename);
                    break;
                }
            }
        }

        // Insert/update testcases
        if ($problem->getProbid()) {
            // Find the current max rank
            $maxRank = (int)$this->entityManager->createQueryBuilder()
                ->from('DOMJudgeBundle:Testcase', 't')
                ->select('MAX(t.rank)')
                ->andWhere('t.problem = :problem')
                ->setParameter(':problem', $problem)
                ->getQuery()
                ->getSingleScalarResult();
            $rank    = $maxRank + 1;
        } else {
            $rank = 1;
        }

        /** @var TestcaseWithContent[] $testcases */
        $testcases = [];

        // first insert sample, then secret data in alphabetical order
        foreach (['sample', 'secret'] as $type) {
            $numCases  = 0;
            $dataFiles = [];
            for ($j = 0; $j < $zip->numFiles; $j++) {
                $filename = $zip->getNameIndex($j);
                if (Utils::startsWith($filename, sprintf('data/%s/', $type)) && Utils::endsWith($filename, '.in')) {
                    $basename = basename($filename, ".in");
                    $fileout  = sprintf('data/%s/%s.ans', $type, $basename);
                    if ($zip->locateName($fileout) !== false) {
                        $dataFiles[] = $basename;
                    }
                }
            }
            asort($dataFiles);

            echo "<ul>\n";
            foreach ($dataFiles as $dataFile) {
                $testIn      = $zip->getFromName(sprintf('data/%s/%s.in', $type, $dataFile));
                $testOut     = $zip->getFromName(sprintf('data/%s/%s.ans', $type, $dataFile));
                $description = $dataFile;
                if (($descriptionFile = $zip->getFromName(sprintf('data/%s/%s.desc', $type, $dataFile))) !== false) {
                    $description = $descriptionFile;
                }
                $imageFile = $imageType = $imageThumb = false;
                foreach (['png', 'jpg', 'jpeg', 'gif'] as $imgExtension) {
                    $imageFileName = sprintf('data/%s/%s.%s', $type, $dataFile, $imgExtension);
                    if (($imageFile = $zip->getFromName($imageFileName)) !== false) {
                        $imageType = Utils::getImageType($imageFile, $errormsg);
                        if ($imageType === false) {
                            $messages[] = sprintf("reading '%s': %s", $imageFileName, $errormsg);
                            $imageFile  = false;
                        } elseif ($imageType !== ($imgExtension == 'jpg' ? 'jpeg' : $imgExtension)) {
                            $messages[] = sprintf("extension of '%s' does not match type '%s'", $imageFileName,
                                                  $imageType);
                            $imageFile  = false;
                        } else {
                            $thumbnailSize = $this->dj->dbconfig_get('thumbnail_size', 128);
                            $imageThumb    = Utils::getImageThumb($imageFile, $thumbnailSize,
                                                                  $this->dj->getDomjudgeTmpDir(),
                                                                  $errormsg);
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
                    $existingTestcase = $this->entityManager
                        ->createQueryBuilder()
                        ->from('DOMJudgeBundle:Testcase', 't')
                        ->select('t')
                        ->andWhere('t.md5sum_input = :inputmd5')
                        ->andWhere('t.md5sum_output = :outputmd5')
                        ->andWhere('t.sample = :sample')
                        ->andWhere('t.description = :description')
                        ->andWhere('t.problem = :problem')
                        ->setParameter(':inputmd5', $md5in)
                        ->setParameter(':outputmd5', $md5out)
                        ->setParameter(':sample', $type === 'sample')
                        ->setParameter(':description', $description)
                        ->setParameter(':problem', $problem)
                        ->getQuery()
                        ->getOneOrNullResult();

                    if (isset($existingTestcase)) {
                        $messages[] = sprintf('Skipped %s testcase <tt>%s</tt>: already exists', $type, $dataFile);
                        continue;
                    }
                }

                $testcase = new TestcaseWithContent();
                $testcase
                    ->setProblem($problem)
                    ->setRank($rank)
                    ->setSample($type === 'sample')
                    ->setMd5sumInput($md5in)
                    ->setMd5sumOutput($md5out)
                    ->setInput($testIn)
                    ->setOutput($testOut)
                    ->setDescription($description);
                if ($imageFile !== false) {
                    $testcase
                        ->setImage($imageFile)
                        ->setImageThumb($imageThumb)
                        ->setImageType($imageType);
                }
                $this->entityManager->persist($testcase);

                $rank++;
                $numCases++;

                $testcases[] = $testcase;

                $messages[] = sprintf('Added %s testcase from: <tt>%s.{in,ans}</tt>', $type, $dataFile);
            }
            $messages[] = sprintf("Added %d %s testcase(s).", $numCases, $type);
        }

        $this->entityManager->persist($problem);
        $this->entityManager->flush();
        if ($contestProblem) {
            $contestProblem->setProbid($problem->getProbid());
            $contestProblem->setCid($contest->getCid());
            $this->entityManager->persist($contestProblem);
        }

        $this->entityManager->flush();

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
            $allowedLanguages = $this->entityManager->createQueryBuilder()
                ->from('DOMJudgeBundle:Language', 'l', 'l.langid')
                ->select('l')
                ->andWhere('l.allowSubmit = true')
                ->getQuery()
                ->getResult();

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

                $tmpDir = $this->dj->getDomjudgeTmpDir();

                if (empty($languageToUse)) {
                    $messages[] = sprintf('Could not add jury solution <tt>%s</tt>: unknown language.', $path);
                } else {
                    $expectedResult = SubmissionService::normalizeExpectedResult($pathComponents[1]);
                    $results        = null;
                    $totalSize      = 0;
                    $filesToSubmit  = [];
                    $tempFiles      = [];
                    for ($k = 0; $k < count($files); $k++) {
                        $source = $zip->getFromIndex($indices[$k]);
                        if ($results === null) {
                            $results = SubmissionService::getExpectedResults($source, $this->DOMJudgeService->dbconfig_get('results_remap', []));
                        }
                        if (!($tempFileName = tempnam($tmpDir, 'ref_solution-'))) {
                            throw new ServiceUnavailableHttpException(null,
                                                                      sprintf('Could not create temporary file in directory %s',
                                                                              $tmpDir));
                        }
                        if (file_put_contents($tempFileName, $source) === false) {
                            throw new ServiceUnavailableHttpException(null,
                                                                      sprintf("Could not write to temporary file '%s'.",
                                                                              $tempFileName));
                        }
                        $filesToSubmit[] = new UploadedFile($tempFileName, $files[$k], null, null, null, true);
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
                    if ($totalSize <= $this->dj->dbconfig_get('sourcesize_limit') * 1024) {
                        $contest        = $this->entityManager->getRepository(Contest::class)->find($contest->getCid());
                        $team           = $this->entityManager->getRepository(Team::class)->find($this->dj->getUser()->getTeamid());
                        $contestProblem = $this->entityManager->getRepository(ContestProblem::class)->find([
                                                                                                               'probid' => $problem->getProbid(),
                                                                                                               'cid' => $contest->getCid()
                                                                                                           ]);
                        $submission     = $this->submissionService->submitSolution($team, $contestProblem, $contest,
                                                                                   $languageToUse, $filesToSubmit, null,
                                                                                   '__auto__', null, null, null,
                                                                                   $submissionMessage);
                        if (!$submission) {
                            $errorMessage = $submissionMessage;
                            return null;
                        }
                        $submission = $this->entityManager->getRepository(Submission::class)->find($submission->getSubmitid());
                        $submission->setExpectedResults($results);
                        // Flush changes to submission
                        $this->entityManager->flush();

                        $messages[] = sprintf('Added jury solution from: <tt>%s</tt></li>', $path);
                        $numJurySolutions++;
                    } else {
                        $messages[] = sprintf('Could not add jury solution <tt>%s</tt>: too large.', $path);
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
