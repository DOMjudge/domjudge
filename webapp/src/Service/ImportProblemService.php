<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Executable;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\ProblemAttachment;
use App\Entity\ProblemAttachmentContent;
use App\Entity\ProblemStatementContent;
use App\Entity\Submission;
use App\Entity\SubmissionSource;
use App\Entity\Team;
use App\Entity\Testcase;
use App\Entity\TestcaseAggregationType;
use App\Entity\TestcaseContent;
use App\Entity\TestcaseGroup;
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
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Yaml\Yaml;
use ValueError;
use ZipArchive;

class ImportProblemService
{
    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly LoggerInterface $logger,
        protected readonly DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly EventLogService $eventLogService,
        protected readonly SubmissionService $submissionService,
        protected readonly ValidatorInterface $validator
    ) {}

    /**
     * Import a zipped problem.
     *
     * @param array<string, string[]> $messages
     * @throws DBALException
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function importZippedProblem(
        ZipArchive $zip,
        string $clientName,
        ?Problem $problem,
        ?Contest $contest,
        array &$messages
    ): ?Problem {
        // This might take a while.
        Utils::extendMaxExecutionTime(300);

        foreach (['info', 'warning', 'danger'] as $type) {
            $messages[$type] = [];
        }

        $zipEntries = [];
        for ($j = 0; $j < $zip->numFiles; $j++) {
            $zipEntries[$j] = $zip->getNameIndex($j);
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
                ->setTypesAsString(['pass-fail'])
                ->setCompareExecutable()
                ->setSpecialCompareArgs('')
                ->setRunExecutable()
                ->setMemlimit(null)
                ->setOutputlimit(null)
                ->setProblemStatementContent(null)
                ->setProblemstatementType(null);

            $contestProblem
                ?->setPoints(1)
                ->setAllowSubmit(true)
                ->setAllowJudge(true);
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

        $validationMode = 'default';
        if (!static::parseYaml($problemYaml, $messages, $validationMode, $propertyAccessor, $problem)) {
            return null;
        }
        if (!$this->searchAndAddValidator($zip, $zipEntries, $messages, $externalId, $validationMode, $problem)) {
            return null;
        }

        // Add problem statement, also look in obsolete location.
        foreach (['problem_statement/', ''] as $dir) {
            foreach (['pdf', 'html', 'txt'] as $type) {
                $filename = sprintf('%sproblem.%s', $dir, $type);
                $text     = $zip->getFromName($filename);
                if ($text !== false) {
                    $content = (new ProblemStatementContent())
                        ->setContent($text);
                    $problem
                        ->setProblemStatementContent($content)
                        ->setProblemstatementType($type);
                    $messages['info'][] = "Added/updated problem statement from: $filename";
                    break 2;
                }
            }
        }

        $this->em->persist($problem);
        if ($contestProblem) {
            $contestProblem->setProblem($problem);
            $contestProblem->setContest($contest);
            $this->em->persist($contestProblem);
        }
        $this->em->flush();

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
                $index = sprintf(
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

        // Now we need to parse metadata for test case groups so it can be referenced when dealing with test cases.
        $testCaseGroups = [];
        foreach (['', 'sample/', 'secret/'] as $type) {
            for ($j = 0; $j < $zip->numFiles; $j++) {
                $filename = $zip->getNameIndex($j);
                foreach (['test_group.yaml', 'testdata.yaml'] as $metafile) {
                    if (Utils::startsWith($filename, sprintf('data/%s', $type)) && Utils::endsWith($filename, sprintf('/%s', $metafile))) {
                        $fileContent = $zip->getFromName($filename);
                        if ($fileContent === false) {
                            $messages['danger'][] = sprintf("Could not read test group metadata file '%s'.", $filename);
                            return null;
                        }
                        try {
                            $dir = dirname($filename);
                            $testcaseGroup = $this->parseTestCaseGroupMeta($fileContent, $dir, $messages);
                            if (!$testcaseGroup) {
                                $messages['danger'][] = sprintf("Could not parse test group metadata file '%s'.", $filename);
                                return null;
                            }
                            $testCaseGroups[$dir] = $testcaseGroup;
                        } catch (Exception $e) {
                            $messages['danger'][] = sprintf("Could not parse test group metadata file '%s': %s", $filename, $e->getMessage());
                            return null;
                        }
                    }
                }
            }
        }
        foreach ($testCaseGroups as $dir => $testCaseGroup) {
            $parentDir = dirname($dir);
            if (isset($testCaseGroups[$parentDir])) {
                $testCaseGroups[$dir]->setParent($testCaseGroups[$parentDir]);
            }
        }
        if (array_key_exists('data', $testCaseGroups)) {
            $problem->setParentTestcaseGroup($testCaseGroups['data']);
            $messages['info'][] = 'Set parent testcase group for problem to "data", with ID ' .
                $testCaseGroups['data']->getTestcaseGroupId();
        }

        // First insert sample, then secret data in alphabetical order.
        foreach (['sample', 'secret'] as $type) {
            $numCases  = 0;
            $dataFiles = [];
            foreach ($zipEntries as $filename) {
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
            asort($dataFiles, SORT_STRING);
            $messages['info'][] = sprintf("Found %d %s testcase(s): {%s}.{in,ans}",
                count($dataFiles), $type, join(',', $dataFiles));

            foreach ($dataFiles as $dataFile) {
                $baseFileName = sprintf('data/%s/%s', $type, $dataFile);
                $testInput  = $zip->getFromName($baseFileName . '.in');
                $testOutput = $zip->getFromName($baseFileName . '.ans');
                $imageType = $imageThumb = false;
                foreach (['png', 'jpg', 'jpeg', 'gif'] as $imgExtension) {
                    $imageFileName = $baseFileName . '.' . $imgExtension;
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
                // Handle SVG differently, as a lot of the above concepts do not make sense in this context.
                $imageFileName = $baseFileName . '.svg';
                if (($imageFile = $zip->getFromName($imageFileName)) !== false) {
                    if (($imageFile = Utils::sanitizeSvg($imageFile)) === false) {
                        $messages['warning'][] = sprintf("Contents of '%s' is not safe.", $imageFileName);
                    }
                    $imageType = 'svg';
                    $imageThumb = $imageFile;
                }

                if (str_contains($testInput, "\r")) {
                    $messages['warning'][] = "Testcase file '$baseFileName.in' contains Windows newlines.";
                }
                if (str_contains($testOutput, "\r")) {
                    $messages['warning'][] = "Testcase file '$baseFileName.ans' contains Windows newlines.";
                }

                $md5in  = md5($testInput);
                $md5out = md5($testOutput);

                $testcaseGroup = null;
                $dir = dirname($baseFileName);
                if (isset($testCaseGroups[$dir])) {
                    $testcaseGroup = $testCaseGroups[$dir];
                }

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
                if (($descriptionFile = $zip->getFromName($baseFileName . '.desc')) !== false) {
                    $testcase->setDescription($descriptionFile);
                }
                if ($imageFile !== false) {
                    $testcase->setImageType($imageType);
                    $testcaseContent
                        ->setImage($imageFile)
                        ->setImageThumb($imageThumb);
                }
                if ($testcaseGroup) {
                    $testcase->setTestcaseGroup($testcaseGroup);
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
        foreach ($zipEntries as $j => $filename) {
            if (!Utils::startsWith($filename, 'attachments/')) {
                continue;
            }

            $content = $zip->getFromName($filename);
            if (empty($content)) {
                // Empty file or directory, ignore.
                continue;
            }

            // In doubt make files executable, but try to read it from the zip file.
            $executableBit = true;
            if ($zip->getExternalAttributesIndex($j, $opsys, $attr)
                && $opsys==ZipArchive::OPSYS_UNIX
                && (($attr >> 16) & 0100) === 0) {
                $executableBit = false;
            }

            $name = basename($filename);

            $fileParts = explode('.', $name);
            if (count($fileParts) > 1) {
                $type = $fileParts[count($fileParts) - 1];
            } else {
                $type = 'txt';
            }

            $finfo = new \finfo();
            $mime = $finfo->buffer($content);
            $mime = explode(';', $mime)[0];

            // Check if an attachment already exists, since then we overwrite it.
            $attachment = $existingAttachments[$name] ?? null;

            if ($attachment) {
                $attachmentContent = $attachment->getContent();
                if ($content !== $attachmentContent->getContent()) {
                    $attachmentContent->setContent($content);
                    $attachment
                        ->setType($type)
                        ->setMimeType($mime);
                    $messages['info'][] = sprintf("Updated attachment '%s'", $name);
                    $numAttachments++;
                }
                if ($executableBit !== $attachmentContent->isExecutable()) {
                    $attachmentContent->setIsExecutable($executableBit);
                    $messages['info'][] = sprintf("Updated executable bit of attachment '%s'", $name);
                }
            } else {
                $attachment = new ProblemAttachment();
                $attachmentContent = new ProblemAttachmentContent();
                $attachment
                    ->setProblem($problem)
                    ->setName($name)
                    ->setType($type)
                    ->setMimeType($mime)
                    ->setContent($attachmentContent);

                $attachmentContent
                    ->setContent($content)
                    ->setIsExecutable($executableBit);

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

        $this->em->wrapInTransaction(function () use ($testcases, $startRank): void {
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

        $cid = $contest?->getCid();
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
            $allowedLanguages = $this->dj->getAllowedLanguagesForContest($contest);

            // Read submission details from optional file.
            $submission_file_string = $zip->getFromName($submission_file);
            $submission_details = $submission_file_string===false ? [] :
                Utils::jsonDecode($submission_file_string);

            $numJurySolutions = 0;
            foreach ($zipEntries as $j => $path) {
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
                    foreach ($zipEntries as $k => $file) {
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
                    foreach ($allowedLanguages as $language) {
                        if (in_array($extension, $language->getExtensions())) {
                            $languageToUse = $language->getExternalid();
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
                    $annotation     = null;
                    $totalSize      = 0;
                    $filesToSubmit  = [];
                    $tempFiles      = [];
                    for ($k = 0; $k < count($files); $k++) {
                        $source = $zip->getFromIndex($indices[$k]);
                        if ($annotation === null) {
                            $annotation = SubmissionService::parseExpectedAnnotation($source,
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
                    // Parse annotation into expected results or expected score
                    $expectedResults = null;
                    $expectedScore = null;
                    if ($annotation === false) {
                        // Multiple annotations found - error already reported
                        $expectedResults = [$expectedResult];
                    } elseif ($annotation === null) {
                        // No annotation found - use directory-based expected result
                        $expectedResults = [$expectedResult];
                    } elseif ($annotation['type'] === 'score') {
                        // Numeric score annotation
                        $expectedScore = $annotation['value'];
                    } else {
                        // Result-based annotation
                        $results = $annotation['value'];
                        if (!in_array($expectedResult, $results)) {
                            $messages['danger'][] = sprintf(
                                "Annotated result '%s' does not match directory for %s",
                                implode(', ', $results), $path
                            );
                        } elseif (!empty($expectedResult)) {
                            if (count($results) > 1) {
                                $messages['warning'][] = sprintf(
                                    "Annotated results '%s' restricted to match directory for %s",
                                    implode(', ', $results), $path
                                );
                            }
                            $results = [$expectedResult];
                        }
                        $expectedResults = $results;
                    }
                    $jury_team_id = $this->dj->getUser()->getTeam()->getTeamid();
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
                            $team, $jury_user, $contestProblem, $contest, $languageToUse, $filesToSubmit, SubmissionSource::PROBLEM_IMPORT, null,
                            null, $entry_point, null, null, $submissionMessage
                        );

                        if (!$submission) {
                            if ($submissionMessage) {
                                $messages['danger'][] = $submissionMessage;
                            }
                        } else {
                            $submission = $this->em->getRepository(Submission::class)->find($submission->getSubmitid());
                            if ($expectedResults !== null) {
                                $submission->setExpectedResults($expectedResults);
                            }
                            if ($expectedScore !== null) {
                                $submission->setExpectedScore($expectedScore);
                            }
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
                $messages['warning'][] = sprintf("Could not add jury solution(s) due to unknown language: %s",
                    join(', ', $subs_with_unknown_lang));
            }
            if (!empty($too_large_subs)) {
                $messages['warning'][] = sprintf("Could not add jury solution(s) because they are too large: %s",
                    join(', ', $too_large_subs));
            }
        } else {
            $messages['warning'][] = 'No jury solutions added: problem not submittable.';
        }

        $messages['info'][] = sprintf('Saved problem %d', $problem->getProbid());

        // Only here disable problem submit to make sure the jury submissions
        // do get added above.
        if ($contestProblem) {
            $this->em->flush();
            // We need to reload the problem after the call(s) to the eventLogService.
            $problem = $this->em->getRepository(Problem::class)->find($problem->getProbid());
            $testcases = $problem->getTestcases()->toArray();
            if (count(array_filter($testcases, fn($t) => !$t->getDeleted())) == 0) {
                // We need to reload the contest problem after the call(s) to the eventLogService.
                $contestProblem = $this->em->getRepository(ContestProblem::class)->findOneBy([
                    'contest' => $contest,
                    'problem' => $problem,
                ]);
                $messages['danger'][] = 'No testcases present, disabling submitting for this problem';
                $contestProblem->setAllowSubmit(false);
            }
        }

        // Make sure we persisted all changes to DB
        $this->em->flush();

        return $problem;
    }

    /**
     * @return array{problem_id: string, messages: array<string, string[]>}
     */
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
                ->andWhere('p.externalid = :id')
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
            /** @var array<string, string[]> $messages */
            $messages    = [];
            $newProblem  = $this->importZippedProblem(
                $zip, $clientName, $problem, $contest, $messages
            );
            $allMessages = array_merge($allMessages, $messages);
            if ($newProblem) {
                $this->dj->auditlog('problem', $newProblem->getExternalid(), 'upload zip', $clientName);
                $probId = $newProblem->getExternalid();
            } else {
                $errors = array_merge($errors, $messages);
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        } finally {
            $zip?->close();
        }
        if (!empty($errors)) {
            throw new BadRequestHttpException(Utils::jsonEncode($errors));
        }
        return [
            'problem_id' => $probId,
            'messages'   => $allMessages,
        ];
    }

    /**
     *
     * @param array<int, string> $zipEntries
     * @param array{danger?: string[], info?: string[]} $messages
     */
    private function searchAndAddValidator(ZipArchive $zip, array $zipEntries, array &$messages, string $externalId, string $validationMode, ?Problem $problem): bool
    {
        $validatorFiles = [];
        foreach ($zipEntries as $filename) {
            foreach (['output_validators/', 'output_validator'] as $dir) {
                if (Utils::startsWith($filename, $dir) &&
                    !Utils::endsWith($filename, '/')) {
                    $validatorFiles[] = $filename;
                }
            }
        }
        if (count($validatorFiles) == 0) {
            if ($validationMode === 'default') {
                return true;
            } else {
                $messages['danger'][] = 'Custom validator specified but not found.';
                return false;
            }
        }

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
            $messages['danger'][] = 'Found multiple custom output validators.';
            return false;
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
                $problem->setRunExecutable($executable);
            } else {
                $problem->setCompareExecutable($executable);
            }

            $messages['info'][] = "Added output validator '$outputValidatorName'.";
        }
        return true;
    }

    /**
     * @param array<string, string[]> $messages
     */
    public function parseTestCaseGroupMeta(string $fileContent, string $name, array &$messages): ?TestcaseGroup
    {
        $yamlData = Yaml::parse($fileContent);
        if (empty($yamlData)) {
            return null;
        }
        $testcaseGroup = new TestcaseGroup();
        $testcaseGroup->setName($name);
        if (isset($yamlData['accept_score'])) {
            $value = $yamlData['accept_score'];
            if (!is_numeric($value)) {
                $messages['danger'][] = sprintf("Invalid accept_score '%s' in test group '%s'.", $value, $name);
                return null;
            }
            $testcaseGroup = $testcaseGroup->setAcceptScore(Utils::numericToBcMath($yamlData['accept_score']));
        }
        if (isset($yamlData['range'])) {
            $range = preg_split('/\s+/', $yamlData['range']);
            if (count($range) != 2 || !is_numeric($range[0]) || !is_numeric($range[1])) {
                $messages['danger'][] = sprintf("Invalid range '%s' in test group '%s'.", $yamlData['range'], $name);
                return null;
            }
            $testcaseGroup->setRangeLowerBound(Utils::numericToBcMath($range[0]));
            $testcaseGroup->setRangeUpperBound(Utils::numericToBcMath($range[1]));
        }
        if (isset($yamlData['grader_flags'])) {
            $flags = preg_split('/\s+/', $yamlData['grader_flags']);
            foreach ($flags as $flag) {
                if (in_array($flag, ['sum', 'max', 'min', 'avg'])) {
                    try {
                        $aggregationType = TestcaseAggregationType::tryFrom($flag);
                        $testcaseGroup->setAggregationType($aggregationType);
                    } catch (ValueError $e) {
                        $messages['danger'][] = sprintf("Invalid aggregation type '%s' in test group '%s'.", $flag, $name);
                        return null;
                    }
                }
                if ($flag === 'ignore_sample') {
                    $testcaseGroup->setIgnoreSample(true);
                }
                // Silently ignore currently unused flags.
                // TODO: add support for the remaining flags and error out on unknown flags.
            }
        }
        if (isset($yamlData['output_validator_flags'])) {
            $testcaseGroup->setOutputValidatorFlags($yamlData['output_validator_flags']);
        }
        if (isset($yamlData['on_reject'])) {
            $testcaseGroup->setOnRejectContinue($yamlData['on_reject'] === 'continue');
        }
        $this->em->persist($testcaseGroup);
        $this->em->flush();
        return $testcaseGroup;
    }

    /**
     * Returns true iff the yaml could be parsed correctly.
     *
     * @param array{danger?: string[], info?: string[]} $messages
     */
    public static function parseYaml(bool|string $problemYaml, array &$messages, string &$validationMode, PropertyAccessor $propertyAccessor, Problem $problem): bool
    {
        if ($problemYaml === false) {
            // While there was no problem.yaml, there was also no error in parsing.
            return true;
        }

        $yamlData = Yaml::parse($problemYaml);
        if (empty($yamlData)) {
            // Empty yaml is OK.
            return true;
        }

        $yamlProblemProperties = [];
        if (isset($yamlData['name'])) {
            if (is_array($yamlData['name'])) {
                // Prefer english name, but if not available, use first name.
                $englishOrFirstName = null;
                foreach ($yamlData['name'] as $lang => $name) {
                    if ($englishOrFirstName === null || $lang === 'en') {
                        $englishOrFirstName = $name;
                    }
                }
                $yamlProblemProperties['name'] = $englishOrFirstName;
            } else {
                $yamlProblemProperties['name'] = $yamlData['name'];
            }
        }

        $validationMode = 'default';
        if (isset($yamlData['type'])) {
            $types = explode(' ', $yamlData['type']);
            // Validation happens later when we set the properties.
            $yamlProblemProperties['typesAsString'] = $types;
            if (in_array('interactive', $types)) {
                $validationMode = 'custom interactive';
            }
        } else {
            $yamlProblemProperties['typesAsString'] = ['pass-fail'];
        }

        if (isset($yamlData['validator_flags'])) {
            $yamlProblemProperties['special_compare_args'] = $yamlData['validator_flags'];
        }

        if (isset($yamlData['validation'])
            && ($yamlData['validation'] == 'custom' ||
                $yamlData['validation'] == 'custom interactive' ||
                $yamlData['validation'] == 'custom multi-pass')) {
            $validationMode = $yamlData['validation'];

            if ($yamlData['validation'] == 'custom multi-pass') {
                $yamlProblemProperties['typesAsString'][] = 'multi-pass';
            }
            if ($yamlData['validation'] == 'custom interactive') {
                $yamlProblemProperties['typesAsString'][] = 'interactive';
            }
        }

        if (isset($yamlData['limits'])) {
            if (isset($yamlData['limits']['memory'])) {
                $yamlProblemProperties['memlimit'] = 1024 * $yamlData['limits']['memory'];
            }
            if (isset($yamlData['limits']['output'])) {
                $yamlProblemProperties['outputlimit'] = 1024 * $yamlData['limits']['output'];
            }
            if (isset($yamlData['limits']['validation_passes'])) {
                $yamlProblemProperties['multipassLimit'] = $yamlData['limits']['validation_passes'];
            }
        }

        foreach ($yamlProblemProperties as $key => $value) {
            try {
                $propertyAccessor->setValue($problem, $key, $value);
            } catch (Exception $e) {
                $messages['danger'][] = sprintf('Error: problem.%s: %s', $key, $e->getMessage());
                return false;
            }
        }

        return true;
    }
}
