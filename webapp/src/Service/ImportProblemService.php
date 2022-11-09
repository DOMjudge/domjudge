<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Executable;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\ProblemAttachment;
use App\Entity\ProblemAttachmentContent;
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\Testcase;
use App\Entity\TestcaseContent;
use App\Utils\Utils;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
    protected EntityManagerInterface $em;
    protected LoggerInterface $logger;
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected SubmissionService $submissionService;
    protected EventLogService $eventLogService;
    protected ValidatorInterface $validator;

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
     * Import a zipped problem.
     * @throws DBALException
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function importZippedProblem(
        ZipArchive $zip,
        string $clientName,
        ?Problem $problem = null,
        ?Contest $contest = null,
        ?array &$messages = []
    ): ?Problem {
        // This might take a while.
        ini_set('max_execution_time', '300');

        foreach (['info', 'warning', 'danger'] as $type) {
            $messages[$type] = [];
        }

        $propertiesFile  = 'domjudge-problem.ini';
        $yamlFile        = 'problem.yaml';
        $tleFile         = '.timelimit';
        $submission_file = 'submissions.json';
        $problemIsNew    = $problem === null;

        $iniKeysProblem        = ['name', 'timelimit', 'special_run', 'special_compare', 'externalid'];
        $iniKeysContestProblem = ['allow_submit', 'allow_judge', 'points', 'color', 'short-name'];

        $defaultTimelimit = 10;

        $propertiesString = $zip->getFromName($propertiesFile);
        $problemYaml = $zip->getFromName($yamlFile);

        if ($propertiesString === false && $problemYaml === false) {
            $messages['danger'][] = sprintf('ZIP file contains neither %s nor %s, not a valid problem archive?',
                $propertiesFile, $yamlFile);
            return null;
        }

        $properties = [];

        if ($propertiesString !== false) {
            $tryParseProperties = parse_ini_string($propertiesString);
            if (is_array($tryParseProperties)) {
                $properties = $tryParseProperties;
            } else {
                $messages['warning'][] = "The given domjudge-problem.ini is invalid, ignoring.";
            }
        }

        // Only preserve valid keys:
        $problemProperties        = array_intersect_key($properties, array_flip($iniKeysProblem));
        $contestProblemProperties = array_intersect_key($properties, array_flip($iniKeysContestProblem));

        // The property in the INI is called short-name (because that is what we have called it in exports),
        // but the property on the contest problem is shortname, so remap it.
        if (isset($contestProblemProperties['short-name'])) {
            $contestProblemProperties['shortname'] = $contestProblemProperties['short-name'];
            unset($contestProblemProperties['short-name']);
        }

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

        if (!isset($contestProblemProperties['shortname'])) {
            $contestProblemProperties['shortname'] = $externalId;
        }

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
            if ($problem->getExternalid() !== $problemProperties['externalid']) {
                $messages['danger'][] = sprintf(
                    'Error: External ID of problem to import into (%s) does not match new external ID (%s).',
                $problem->getExternalid(), $problemProperties['externalid']
                );
                return null;
            }

            if ($contest !== null) {
                // Find the correct contest problem.
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

        // Reset problem settings that might not appear in the ZIP back to default.
        // Note that we only reset settings here that make sense. In particular, we will
        // not reset any settings that could also have been set while importing the problem set
        // through problems.yaml/json or problemset.yaml. This means that we will not set the
        // color and shortname properties on the contest problem unless explicitly specified.
        // The same holds for the timelimit of the problem.
        if ($problem->getProbid()) {
            $problem
                ->setCompareExecutable()
                ->setSpecialCompareArgs('')
                ->setRunExecutable()
                ->setCombinedRunCompare(false)
                ->setMemlimit(null)
                ->setOutputlimit(null)
                ->setProblemtext(null)
                ->setProblemtextType(null);

            if ($contestProblem) {
                $contestProblem
                    ->setPoints(1)
                    ->setAllowSubmit(true)
                    ->setAllowJudge(true);
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
                $messages['danger'][] = sprintf(
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
                    $messages['danger'][] = sprintf(
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
                            // TODO: select a specific instead of the first language.
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
                    $this->searchAndAddValidator($zip, $messages, $externalId, $yamlData['validation'], $problem);
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

        // Add problem statement, also look in obsolete location.
        foreach (['problem_statement/', ''] as $dir) {
            foreach (['pdf', 'html', 'txt'] as $type) {
                $filename = sprintf('%sproblem.%s', $dir, $type);
                $text     = $zip->getFromName($filename);
                if ($text !== false) {
                    $problem
                        ->setProblemtext($text)
                        ->setProblemtextType($type);
                    $messages['info'][] = "Added/updated problem statement from: $filename";
                    break 2;
                }
            }
        }

        // Load the current testcases to see if we need to delete, update or insert testcases.
        $existingTestcases = [];
        if ($problem->getProbid()) {
            /** @var Testcase[] $testcaseData */
            $testcaseData = $this->em
                ->createQueryBuilder()
                ->from(Testcase::class, 't')
                ->select('t')
                ->andWhere('t.problem = :problem')
                ->setParameter('problem', $problem)
                ->getQuery()
                ->getResult();
            foreach ($testcaseData as $testcase) {
                $index                     = sprintf(
                    '%s-%s-%s',
                    $testcase->getMd5sumInput(),
                    $testcase->getMd5sumOutput(),
                    $testcase->getOrigInputFilename());
                $existingTestcases[$index] = $testcase;
            }
        }

        $touchedTestcases = [];

        // Keep track of the final testcases. We do this because we need rank them.
        // We can't do this 'on the fly' when updating since we will get
        // issues with duplicate keys. Instead, we give every testcase a temporary
        // rank we know is unique and give the final ranks in the end.
        /** @var Testcase[] $testcases */
        $testcases     = [];
        $testcaseNames = [];

        $startRank = 1;
        // We temporarily assign ranks starting from the current max rank + 1 when updating a problem.
        // These are guaranteed to not exist.
        if ($problem->getProbid()) {
            $startRank = $problem->getTestcases()->count() + 1;
        }
        $rank       = $startRank;
        $actualRank = 1;

        // First insert sample, then secret data in alphabetical order.
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
                $testInput  = $zip->getFromName(sprintf('data/%s/%s.in', $type, $dataFile));
                $testOutput = $zip->getFromName(sprintf('data/%s/%s.ans', $type, $dataFile));
                $imageFile  = $imageType = $imageThumb = false;
                foreach (['png', 'jpg', 'jpeg', 'gif'] as $imgExtension) {
                    $imageFileName = sprintf('data/%s/%s.%s', $type, $dataFile, $imgExtension);
                    if (($imageFile = $zip->getFromName($imageFileName)) !== false) {
                        $imageType = Utils::getImageType($imageFile, $errormsg);
                        if ($imageType === false) {
                            $messages['info'][] = sprintf("Reading '%s': %s", $imageFileName, $errormsg);
                            $imageFile  = false;
                        } elseif ($imageType !== ($imgExtension == 'jpg' ? 'jpeg' : $imgExtension)) {
                            $messages['warning'][] = sprintf("Extension of '%s' does not match type '%s'.",
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
                                $messages['info'][] = sprintf("Reading '%s': %s", $imageFileName, $errormsg);
                            }
                        }
                        break;
                    }
                }

                $md5in  = md5($testInput);
                $md5out = md5($testOutput);

                // Check if we have an existing testcase with the same data.
                $index = sprintf('%s-%s-%s', $md5in, $md5out, $dataFile);
                $touchedTestcases[$index] = $index;
                if (isset($existingTestcases[$index])) {
                    $testcase = $existingTestcases[$index];
                    $sample   = $type === 'sample';
                    $changed = false;
                    if ($testcase->getSample() !== $sample) {
                        $testcase->setSample($sample);
                        $changed = true;
                    }
                    if ($testcase->getRank() !== $actualRank) {
                        $changed = true;
                    }
                    $testcase->setRank($rank);
                    if ($changed) {
                        $numCases++;
                        $testcaseNames[] = $dataFile;
                    }
                    $rank++;
                    $actualRank++;
                    $testcases[] = $testcase;
                    continue;
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
                    ->setInput($testInput)
                    ->setOutput($testOutput);
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
                $actualRank++;
                $numCases++;

                $testcases[] = $testcase;
                $testcaseNames[] = $dataFile;
            }
            if ($numCases > 0) {
                $messages['info'][] = sprintf("Added/updated %d %s testcase(s): {%s}.{in,ans}",
                    $numCases, $type, join(',', $testcaseNames));
            }
        }

        $removedTestcases = [];
        foreach ($existingTestcases as $index => $testcase) {
            if (!isset($touchedTestcases[$index])) {
                $removedTestcases[] = $testcase->getOrigInputFilename();
                $testcase
                    ->setDeleted(true)
                    ->setProblem(null);
            }
        }

        if (!empty($removedTestcases)) {
            $messages['info'][] = sprintf("Removed %d testcase(s): {%s}.{in,ans}",
                count($removedTestcases), join(',', $removedTestcases));
        }

        // Load the current attachments to see if we need to delete, update or insert attachments
        $existingAttachments = [];
        if ($problem->getProbid()) {
            /** @var ProblemAttachment[] $attachmentData */
            $attachmentData = $this->em
                ->createQueryBuilder()
                ->from(ProblemAttachment::class, 'a')
                ->select('a')
                ->andWhere('a.problem = :problem')
                ->setParameter('problem', $problem)
                ->getQuery()
                ->getResult();
            foreach ($attachmentData as $attachment) {
                $existingAttachments[$attachment->getName()] = $attachment;
            }
        }

        $touchedAttachments = [];

        $numAttachments = 0;
        for ($j = 0; $j < $zip->numFiles; $j++) {
            $filename = $zip->getNameIndex($j);
            if (!Utils::startsWith($filename, 'attachments/')) {
                continue;
            }

            $content = $zip->getFromName($filename);
            if (empty($content)) {
                // Empty file or directory, ignore.
                continue;
            }

            $name = basename($filename);

            $fileParts = explode('.', $name);
            if (count($fileParts) > 0) {
                $type = $fileParts[count($fileParts) - 1];
            } else {
                $type = 'txt';
            }

            // Check if an attachment already exists, since then we overwrite it.
            if (isset($existingAttachments[$name])) {
                $attachment = $existingAttachments[$name];
            } else {
                $attachment = null;
            }

            if ($attachment) {
                $attachmentContent = $attachment->getContent();
                if ($content !== $attachmentContent->getContent()) {
                    $attachmentContent->setContent($content);
                    $messages['info'][] = sprintf("Updated attachment '%s'", $name);
                    $numAttachments++;
                }
            } else {
                $attachment = new ProblemAttachment();
                $attachmentContent = new ProblemAttachmentContent();
                $attachment
                    ->setProblem($problem)
                    ->setName($name)
                    ->setType($type)
                    ->setContent($attachmentContent);

                $attachmentContent->setContent($content);

                $this->em->persist($attachment);

                $messages['info'][] = sprintf("Added attachment '%s'", $name);
                $numAttachments++;
            }

            $touchedAttachments[$attachment->getName()] = $attachment->getName();
        }

        if ($numAttachments > 0) {
            $messages['info'][] = sprintf("Added/updated %d attachment(s).", $numAttachments);
        }

        $removedAttachments = [];
        foreach ($existingAttachments as $index => $attachment) {
            if (!isset($touchedAttachments[$index])) {
                $removedAttachments[] = $attachment->getName();
                $this->em->remove($attachment);
            }
        }

        if (!empty($removedAttachments)) {
            $messages['info'][] = sprintf("Removed %d attachments(s): {%s}",
                count($removedAttachments), join(',', $removedAttachments));
        }

        $this->em->persist($problem);
        $this->em->wrapInTransaction(function () use ($testcases, $startRank) {
            $this->em->flush();
            // Set actual ranks if needed.
            if ($startRank !== 1) {
                $rank = 1;
                foreach ($testcases as $testcase) {
                    $testcase->setRank($rank);
                    $rank++;
                }
            }
            $this->em->flush();
        });
        if ($contestProblem) {
            $contestProblem->setProblem($problem);
            $contestProblem->setContest($contest);
            $this->em->persist($contestProblem);
            $this->em->flush();
        }

        $cid = $contest ? $contest->getCid() : null;
        $probid = $problem->getProbid();
        $this->eventLogService->log('problem', $problem->getProbid(), $problemIsNew ? 'create' : 'update', $cid);

        // Submit reference solutions.
        if ($contest === null) {
            $messages['warning'][] = 'No jury solutions added: problem is not linked to a contest (yet).';
        } elseif (!$this->dj->getUser()->getTeam()) {
            $messages['warning'][] = 'No jury solutions added: must associate team with your user first.';
        } elseif ($contestProblem->getAllowSubmit()) {
            $subs_with_unknown_lang = [];
            $too_large_subs = [];
            $successful_subs = [];

            // As EventLogService::log() will clear the entity manager, the problem and the contest became detached. We
            // need to reload them.
            // We seem to need to explicitly clear the EntityManager, otherwise we will receive inconsistent data.
            $this->em->clear();
            $problem = $this->em->getRepository(Problem::class)->find($probid);
            $contest = $this->em->getRepository(Contest::class)->find($cid);

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
            $submission_details = $submission_file_string===false ? [] :
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
                    $subs_with_unknown_lang[] = "'" . $path . "'";
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
                    if ($results === false || $results === null) {
                        $results[] = $expectedResult;
                    } elseif (!in_array($expectedResult, $results)) {
                        $messages['info'][] = sprintf("Annotated result '%s' does not match directory for %s",
                                              implode(', ', $results), $path);
                    } elseif (!empty($expectedResult)) {
                        $results = [$expectedResult];
                    }
                    $jury_team_id = $this->dj->getUser()->getTeam() ? $this->dj->getUser()->getTeam()->getTeamid() : null;
                    $jury_user = $this->dj->getUser();
                    if (isset($submission_details[$path]['team'])) {
                        /** @var Team|null $json_team */
                        $json_team = $this->em->getRepository(Team::class)
                            ->findOneBy(['name' => $submission_details[$path]['team']]);
                        if (isset($json_team)) {
                            $json_team_id = $json_team->getTeamid();
                            if (isset($json_team_id)) {
                                $jury_team_id = $json_team_id;
                                if (!$json_team->getUsers()->isEmpty()) {
                                    $jury_user = $json_team->getUsers()->first();
                                }
                            }
                        }
                    }
                    $entry_point = '__auto__';
                    if (isset($submission_details[$path]['entry_point'])) {
                        $entry_point = $submission_details[$path]['entry_point'];
                    }
                    if ($totalSize <= $this->config->get('sourcesize_limit') * 1024) {
                        $contest        = $this->em->getRepository(Contest::class)->find($contest->getCid());
                        $team           = $this->em->getRepository(Team::class)->find($jury_team_id);
                        $contestProblem = $this->em->getRepository(ContestProblem::class)->find(
                            [
                                'problem' => $problem,
                                'contest' => $contest,
                            ]
                        );
                        $submission     = $this->submissionService->submitSolution(
                            $team, $jury_user, $contestProblem, $contest, $languageToUse, $filesToSubmit, 'problem import', null,
                            null, $entry_point, null, null, $submissionMessage
                        );

                        if (!$submission) {
                            $messages['danger'][] = $submissionMessage;
                        } else {
                            $submission = $this->em->getRepository(Submission::class)->find($submission->getSubmitid());
                            $submission->setExpectedResults($results);
                            // Flush changes to submission.
                            $this->em->flush();

                            $successful_subs[] = "'" . $path . "'";
                            $numJurySolutions++;
                        }
                    } else {
                        $too_large_subs[] = "'" . $path . "'";
                    }

                    foreach ($tempFiles as $f) {
                        unlink($f);
                    }
                }
            }

            if ($numJurySolutions > 0) {
                $messages['info'][] = sprintf('Added %d jury solution(s): %s', $numJurySolutions,
                    join(', ', $successful_subs));
            }
            if (!empty($subs_with_unknown_lang)) {
                $messages['warning'][] = sprintf("Could not add jury solution due to unknown language: %s",
                    join(', ', $subs_with_unknown_lang));
            }
            if (!empty($too_large_subs)) {
                $messages['warning'][] = sprintf("Could not add jury solution because they are too large: %s",
                    join(', ', $too_large_subs));
            }
        } else {
            $messages['warning'][] = 'No jury solutions added: problem not submittable.';
        }

        $messages['info'][] = sprintf('Saved problem %d', $problem->getProbid());

        return $problem;
    }

    public function importProblemFromRequest(Request $request, ?int $contestId = null): array
    {
        $file = $request->files->get('zip');
        if (empty($file)) {
            throw new BadRequestHttpException('ZIP file missing');
        }

        $contest = null;
        if ($contestId) {
            /** @var Contest $contest */
            $contest = $this->em->getRepository(Contest::class)->find($contestId);
        }
        $allMessages = [];

        // Only timeout after 2 minutes, since importing may take a while.
        set_time_limit(120);

        $probId  = $request->request->get('problem');
        $problem = null;
        if (!empty($probId)) {
            $problem = $this->em->createQueryBuilder()
                ->from(Problem::class, 'p')
                ->select('p')
                ->andWhere(sprintf('p.%s = :id', $this->eventLogService->externalIdFieldForEntity(Problem::class) ?? 'probid'))
                ->setParameter('id', $probId)
                ->getQuery()
                ->getOneOrNullResult();
            if (empty($problem)) {
                throw new BadRequestHttpException('Specified \'problem\' does not exist.');
            }
        }
        $errors = [];
        $zip    = null;
        try {
            $zip         = $this->dj->openZipFile($file->getRealPath());
            $clientName  = $file->getClientOriginalName();
            $messages    = [];
            $newProblem  = $this->importZippedProblem(
                $zip, $clientName, $problem, $contest, $messages
            );
            $allMessages = array_merge($allMessages, $messages);
            if ($newProblem) {
                $this->dj->auditlog('problem', $newProblem->getProbid(), 'upload zip', $clientName);
                $probId = $newProblem->getApiId($this->eventLogService);
            } else {
                $errors = array_merge($errors, $messages);
            }
        } catch (Exception $e) {
            $allMessages[] = $e->getMessage();
        } finally {
            if ($zip) {
                $zip->close();
            }
        }
        if (!empty($errors)) {
            throw new BadRequestHttpException($this->dj->jsonEncode($errors));
        }
        return [
            'problem_id' => $probId,
            'messages'   => $allMessages,
        ];
    }

    private function searchAndAddValidator(ZipArchive $zip, ?array &$messages, $externalId, $validationMode, ?Problem $problem): void
    {
        $validatorFiles = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (Utils::startsWith($filename, 'output_validators/') &&
                !Utils::endsWith($filename, '/')) {
                $validatorFiles[] = $filename;
            }
        }
        if (sizeof($validatorFiles) == 0) {
            $messages['danger'][] = 'Custom validator specified but not found.';
        } else {
            // File(s) have to share common directory.
            $validatorDir = mb_substr($validatorFiles[0], 0, mb_strrpos($validatorFiles[0], '/')) . '/';
            $sameDir = true;
            foreach ($validatorFiles as $validatorFile) {
                if (!Utils::startsWith($validatorFile, $validatorDir)) {
                    $sameDir = false;
                    $messages['warning'][] = sprintf('%s does not start with %s.',
                        $validatorFile, $validatorDir);
                    break;
                }
            }
            if (!$sameDir) {
                $messages['warning'][] = 'Found multiple custom output validators.';
            } else {
                $tmpzipfiledir = exec("mktemp -d --tmpdir=" .
                    $this->dj->getDomjudgeTmpDir(),
                    $dontcare, $retval);
                if ($retval != 0) {
                    throw new ServiceUnavailableHttpException(
                        null, 'Failed to create temporary directory.'
                    );
                }
                chmod($tmpzipfiledir, 0700);
                foreach ($validatorFiles as $validatorFile) {
                    $content = $zip->getFromName($validatorFile);
                    $filebase = basename($validatorFile);
                    $newfilename = $tmpzipfiledir . "/" . $filebase;
                    file_put_contents($newfilename, $content);
                    if ($filebase === 'build' || $filebase === 'run') {
                        // Mark special files as executable.
                        chmod($newfilename, 0755);
                    }
                }

                exec("zip -r -j '$tmpzipfiledir/outputvalidator.zip' '$tmpzipfiledir'",
                    $dontcare, $retval);
                if ($retval != 0) {
                    throw new ServiceUnavailableHttpException(
                        null, 'Failed to create ZIP file for output validator.'
                    );
                }

                $outputValidatorZip = file_get_contents($tmpzipfiledir . '/outputvalidator.zip');
                $outputValidatorName = substr($externalId, 0, 20) . '_cmp';
                if ($this->em->getRepository(Executable::class)->find($outputValidatorName)) {
                    // Avoid name clash.
                    $clashCount = 2;
                    while ($this->em->getRepository(Executable::class)->find(
                        $outputValidatorName . '_' . $clashCount)) {
                        $clashCount++;
                    }
                    $outputValidatorName = $outputValidatorName . "_" . $clashCount;
                }

                $combinedRunCompare = $validationMode == 'custom interactive';

                if (!($tempzipFile = tempnam($this->dj->getDomjudgeTmpDir(), "/executable-"))) {
                    throw new ServiceUnavailableHttpException(null, 'Failed to create temporary file.');
                }
                file_put_contents($tempzipFile, $outputValidatorZip);
                $zipArchive = new ZipArchive();
                $zipArchive->open($tempzipFile, ZipArchive::CREATE);

                $executable = new Executable();
                $executable
                    ->setExecid($outputValidatorName)
                    ->setImmutableExecutable($this->dj->createImmutableExecutable($zipArchive))
                    ->setDescription(sprintf('output validator for %s', $problem->getName()))
                    ->setType($combinedRunCompare ? 'run' : 'compare');
                $this->em->persist($executable);

                if ($combinedRunCompare) {
                    $problem->setCombinedRunCompare(true);
                    $problem->setRunExecutable($executable);
                } else {
                    $problem->setCompareExecutable($executable);
                }

                $messages['info'][] = "Added output validator '$outputValidatorName'.";
            }
        }
    }
}
