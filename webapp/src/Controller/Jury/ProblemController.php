<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Judging;
use App\Entity\Problem;
use App\Entity\ProblemAttachment;
use App\Entity\ProblemAttachmentContent;
use App\Entity\Submission;
use App\Entity\SubmissionFile;
use App\Entity\Testcase;
use App\Entity\TestcaseContent;
use App\Form\Type\ProblemAttachmentType;
use App\Form\Type\ProblemType;
use App\Form\Type\ProblemUploadType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ImportProblemService;
use App\Service\SubmissionService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

/**
 * @Route("/jury/problems")
 * @IsGranted("ROLE_JURY")
 */
class ProblemController extends BaseController
{
    protected EntityManagerInterface $em;
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected KernelInterface $kernel;
    protected EventLogService $eventLogService;
    protected SubmissionService $submissionService;
    protected ImportProblemService $importProblemService;

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config,
        KernelInterface $kernel,
        EventLogService $eventLogService,
        SubmissionService $submissionService,
        ImportProblemService $importProblemService
    ) {
        $this->em                   = $em;
        $this->dj                   = $dj;
        $this->config               = $config;
        $this->kernel               = $kernel;
        $this->eventLogService      = $eventLogService;
        $this->submissionService    = $submissionService;
        $this->importProblemService = $importProblemService;
    }

    /**
     * @Route("", name="jury_problems")
     */
    public function indexAction(): Response
    {
        $problems = $this->em->createQueryBuilder()
            ->select('partial p.{probid,externalid,name,timelimit,memlimit,outputlimit}', 'COUNT(tc.testcaseid) AS testdatacount')
            ->from(Problem::class, 'p')
            ->leftJoin('p.testcases', 'tc')
            ->orderBy('p.probid', 'ASC')
            ->groupBy('p.probid')
            ->getQuery()->getResult();

        $table_fields = [
            'probid' => ['title' => 'ID', 'sort' => true, 'default_sort' => true],
            'name' => ['title' => 'name', 'sort' => true],
            'num_contests' => ['title' => '# contests', 'sort' => true],
            'timelimit' => ['title' => 'time limit', 'sort' => true],
            'memlimit' => ['title' => 'memory limit', 'sort' => true],
            'outputlimit' => ['title' => 'output limit', 'sort' => true],
            'num_testcases' => ['title' => '# test cases', 'sort' => true],
        ];

        // Insert external ID field when configured to use it.
        if ($externalIdField = $this->eventLogService->externalIdFieldForEntity(Problem::class)) {
            $table_fields = array_slice($table_fields, 0, 1, true) +
                [$externalIdField => ['title' => 'external ID', 'sort' => true]] +
                array_slice($table_fields, 1, null, true);
        }

        $contestCountData = $this->em->createQueryBuilder()
            ->from(ContestProblem::class, 'cp')
            ->select('COUNT(cp.shortname) AS count', 'p.probid')
            ->join('cp.problem', 'p')
            ->groupBy('cp.problem')
            ->getQuery()
            ->getResult();

        $contestCounts = [];
        foreach ($contestCountData as $problemCount) {
            $contestCounts[$problemCount['probid']] = $problemCount['count'];
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $problems_table   = [];
        foreach ($problems as $row) {
            /** @var Problem $p */
            $p              = $row[0];
            $problemdata    = [];
            $problemactions = [];
            // Get whatever fields we can from the problem object itself.
            foreach ($table_fields as $k => $v) {
                if ($propertyAccessor->isReadable($p, $k)) {
                    $problemdata[$k] = ['value' => $propertyAccessor->getValue($p, $k)];
                }
            }

            // Create action links
            if ($p->getProblemtextType()) {
                $problemactions[] = [
                    'icon' => 'file-' . $p->getProblemtextType(),
                    'title' => 'view problem description',
                    'link' => $this->generateUrl('jury_problem_text', [
                        'probId' => $p->getProbid(),
                    ])
                ];
            } else {
                $problemactions[] = [];
            }
            $problemactions[] = [
                'icon' => 'save',
                'title' => 'export problem as zip-file',
                'link' => $this->generateUrl('jury_export_problem', [
                    'problemId' => $p->getProbid(),
                ])
            ];

            if ($this->isGranted('ROLE_ADMIN')) {
                $problemactions[] = [
                    'icon' => 'edit',
                    'title' => 'edit this problem',
                    'link' => $this->generateUrl('jury_problem_edit', [
                        'probId' => $p->getProbid(),
                    ])
                ];
                $problemactions[] = [
                    'icon' => 'trash-alt',
                    'title' => 'delete this problem',
                    'link' => $this->generateUrl('jury_problem_delete', [
                        'probId' => $p->getProbid(),
                    ]),
                    'ajaxModal' => true,
                ];
            }

            // Add formatted {mem,output}limit row data for the table.
            foreach (['memlimit', 'outputlimit'] as $col) {
                $orig_value = @$problemdata[$col]['value'];
                if (!isset($orig_value)) {
                    $problemdata[$col] = [
                        'value' => 'default',
                        'cssclass' => 'disabled',
                    ];
                } else {
                    $problemdata[$col] = [
                        'value' => Utils::printsize(1024 * $orig_value),
                        'sortvalue' => $orig_value,
                    ];
                }
            }

            // merge in the rest of the data
            $problemdata = array_merge($problemdata, [
                'num_contests' => ['value' => (int)($contestCounts[$p->getProbid()] ?? 0)],
                'num_testcases' => ['value' => (int)$row['testdatacount']],
            ]);

            // Save this to our list of rows
            $problems_table[] = [
                'data' => $problemdata,
                'actions' => $problemactions,
                'link' => $this->generateUrl('jury_problem', ['probId' => $p->getProbid()]),
            ];
        }
        $data = [
            'problems' => $problems_table,
            'table_fields' => $table_fields,
            'num_actions' => $this->isGranted('ROLE_ADMIN') ? 4 : 2,
        ];

        return $this->render('jury/problems.html.twig', $data);
    }

    /**
     * @Route("/{problemId<\d+>}/export", name="jury_export_problem")
     * @IsGranted("ROLE_JURY")
     * @throws NonUniqueResultException
     */
    public function exportAction(int $problemId): StreamedResponse
    {
        // This might take a while.
        ini_set('max_execution_time', '300');
        /** @var Problem $problem */
        $problem = $this->em->createQueryBuilder()
            ->from(Problem::class, 'p')
            ->leftJoin('p.contest_problems', 'cp', Join::WITH, 'cp.contest = :contest')
            ->select('p', 'cp')
            ->andWhere('p.probid = :problemId')
            ->setParameter('problemId', $problemId)
            ->setParameter('contest', $this->dj->getCurrentContest())
            ->getQuery()
            ->getOneOrNullResult();

        /** @var ContestProblem|null $contestProblem */
        $contestProblem = $problem->getContestProblems()->first();

        // Build up INI data.
        $iniData = [
            'timelimit' => $problem->getTimelimit(),
            'special_run' => $problem->getRunExecutable() ? $problem->getRunExecutable()->getExecid() : null,
            'special_compare' => $problem->getCompareExecutable() ? $problem->getCompareExecutable()->getExecid() : null,
            'color' => $contestProblem ? $contestProblem->getColor() : null,
        ];

        $iniString = "";
        foreach ($iniData as $key => $value) {
            if (!empty($value)) {
                $iniString .= $key . "='" . $value . "'\n";
            }
        }

        // Build up YAML.
        $yaml = ['name' => $problem->getName()];
        if (!empty($problem->getCompareExecutable())) {
            $yaml['validation'] = 'custom';
        }
        if (!empty($problem->getSpecialCompareArgs())) {
            $yaml['validator_flags'] = $problem->getSpecialCompareArgs();
        }
        if (!empty($problem->getMemlimit())) {
            $yaml['limits']['memory'] = (int)round($problem->getMemlimit() / 1024);
        }
        if (!empty($problem->getOutputlimit())) {
            $yaml['limits']['output'] = (int)round($problem->getOutputlimit() / 1024);
        }

        $yamlString = '# Problem exported by DOMjudge on ' . date('c') . "\n" . Yaml::dump($yaml);

        $zip = new ZipArchive();
        if (!($tempFilename = tempnam($this->dj->getDomjudgeTmpDir(), "export-"))) {
            throw new ServiceUnavailableHttpException(null, 'Could not create temporary file.');
        }

        $res = $zip->open($tempFilename, ZipArchive::OVERWRITE);
        if ($res !== true) {
            throw new ServiceUnavailableHttpException(null, 'Could not create temporary zip file.');
        }
        $zip->addFromString('domjudge-problem.ini', $iniString);
        $zip->addFromString('problem.yaml', $yamlString);

        if (!empty($problem->getProblemtext())) {
            $zip->addFromString('problem.' . $problem->getProblemtextType(),
                                stream_get_contents($problem->getProblemtext()));
        }

        foreach ([true, false] as $isSample) {
            /** @var Testcase[] $testcases */
            $testcases = $this->em->createQueryBuilder()
                ->from(Testcase::class, 't')
                ->join('t.content', 'c')
                ->select('t', 'c')
                ->andWhere('t.problem = :problem')
                ->andWhere('t.sample = :sample')
                ->setParameter('problem', $problem)
                ->setParameter('sample', $isSample)
                ->orderBy('t.ranknumber')
                ->getQuery()
                ->getResult();
            $this->addTestcasesToZip($testcases, $zip, $isSample);
        }

        /** @var Submission[] $solutions */
        $solutions = $this->em->createQueryBuilder()
            ->from(Submission::class, 's')
            ->select('s')
            ->andWhere('s.problem = :problem')
            ->andWhere('s.contest = :contest')
            ->andWhere('s.expected_results IS NOT NULL')
            ->setParameter('problem', $problem)
            ->setParameter('contest', $this->dj->getCurrentContest())
            ->getQuery()
            ->getResult();

        foreach ($solutions as $solution) {
            $results = $solution->getExpectedResults();
            // Only support single outcome solutions.
            if (count($results) !== 1) {
                continue;
            }

            $result = reset($results);

            $problemResult = null;

            foreach (SubmissionService::PROBLEM_RESULT_REMAP as $key => $val) {
                if (trim(mb_strtoupper($result)) === $val) {
                    $problemResult = mb_strtolower($key);
                }
            }

            if ($problemResult === null) {
                // Unsupported result.
                continue;
            }

            // NOTE: we store *all* submissions inside a subdirectory, also
            // single-file submissions. This is to prevent filename clashes
            // since we can't change the filename to something unique, since
            // that could break e.g. Java sources, even if _we_ support this
            // by default.
            $directory = sprintf('submissions/%s/s%d/', $problemResult, $solution->getSubmitid());
            /** @var SubmissionFile $source */
            foreach ($solution->getFiles() as $source) {
                $zip->addFromString($directory . $source->getFilename(), $source->getSourcecode());
            }
        }

        $zip->close();

        if ($contestProblem && $contestProblem->getShortname()) {
            $zipFilename = sprintf('%s.zip', $contestProblem->getShortname());
        } else {
            $zipFilename = sprintf('p%d.zip', $problem->getProbid());
        }

        $response = new StreamedResponse();
        $response->setCallback(function () use ($tempFilename) {
            $fp = fopen($tempFilename, 'rb');
            fpassthru($fp);
            unlink($tempFilename);
        });
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $zipFilename . '"');
        $response->headers->set('Content-Length', filesize($tempFilename));
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        $response->headers->set('Connection', 'Keep-Alive');
        $response->headers->set('Accept-Ranges', 'bytes');

        return $response;
    }

    /**
     * @Route("/{probId<\d+>}", name="jury_problem")
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function viewAction(Request $request, SubmissionService $submissionService, int $probId): Response
    {
        /** @var Problem $problem */
        $problem = $this->em->getRepository(Problem::class)->find($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        $lockedProblem = false;
        foreach ($problem->getContestProblems() as $contestProblem) {
            /** @var ContestProblem $contestProblem */
            if ($contestProblem->getContest()->isLocked()) {
                $this->addFlash('warning', 'Cannot edit problem, it belongs to locked contest c' . $contestProblem->getContest()->getCid());
                $lockedProblem = true;
            }
        }

        $problemAttachmentForm = $this->createForm(ProblemAttachmentType::class);
        $problemAttachmentForm->handleRequest($request);
        if ($this->isGranted('ROLE_ADMIN') && $problemAttachmentForm->isSubmitted() && $problemAttachmentForm->isValid() && !$lockedProblem) {
            /** @var UploadedFile $file */
            $file = $problemAttachmentForm->get('content')->getData();

            if (!$file->isValid()) {
                $this->addFlash('danger', sprintf('File upload error: %s. No changes made.', $file->getErrorMessage()));
                return $this->redirectToRoute('jury_problem', ['probId' => $probId]);
            }

            $name = $file->getClientOriginalName();
            $fileParts = explode('.', $name);
            if (count($fileParts) > 0) {
                $type = $fileParts[count($fileParts) - 1];
            } else {
                $type = 'txt';
            }
            $content = file_get_contents($file->getRealPath());

            $attachmentContent = new ProblemAttachmentContent();
            $attachmentContent->setContent($content);

            $attachment = new ProblemAttachment();
            $attachment
                ->setProblem($problem)
                ->setName($name)
                ->setType($type)
                ->setContent($attachmentContent);

            $this->em->persist($attachment);
            $this->em->flush();

            return $this->redirectToRoute('jury_problem', ['probId' => $probId]);
        }

        $restrictions = ['probid' => $problem->getProbid()];
        /** @var Submission[] $submissions */
        [$submissions, $submissionCounts] = $submissionService->getSubmissionList(
            $this->dj->getCurrentContests(),
            $restrictions
        );

        $data = [
            'problem' => $problem,
            'problemAttachmentForm' => $problemAttachmentForm->createView(),
            'submissions' => $submissions,
            'submissionCounts' => $submissionCounts,
            'defaultMemoryLimit' => (int)$this->config->get('memory_limit'),
            'defaultOutputLimit' => (int)$this->config->get('output_limit'),
            'defaultRunExecutable' => (string)$this->config->get('default_run'),
            'defaultCompareExecutable' => (string)$this->config->get('default_compare'),
            'showContest' => count($this->dj->getCurrentContests()) > 1,
            'showExternalResult' => $this->config->get('data_source') ===
                DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL,
            'lockedProblem' => $lockedProblem,
            'refresh' => [
                'after' => 15,
                'url' => $this->generateUrl('jury_problem', ['probId' => $problem->getProbid()]),
                'ajax' => true,
            ],
        ];

        // For ajax requests, only return the submission list partial.
        if ($request->isXmlHttpRequest()) {
            $data['showTestcases'] = false;
            return $this->render('jury/partials/submission_list.html.twig', $data);
        }

        return $this->render('jury/problem.html.twig', $data);
    }

    /**
     * @Route("/{probId<\d+>}/text", name="jury_problem_text")
     */
    public function viewTextAction(int $probId): StreamedResponse
    {
        /** @var Problem $problem */
        $problem = $this->em->getRepository(Problem::class)->find($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        return $problem->getProblemTextStreamedResponse();
    }

    /**
     * @Route("/{probId<\d+>}/testcases", name="jury_problem_testcases")
     */
    public function testcasesAction(Request $request, int $probId): Response
    {
        /** @var Problem $problem */
        $problem = $this->em->getRepository(Problem::class)->find($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        $lockedContest = false;
        foreach ($problem->getContestProblems() as $contestproblem) {
            /** @var contestproblem $contestproblem */
            if ($contestproblem->getcontest()->isLocked()) {
                $lockedContest = true;
                break;
            }
        }

        $testcaseData = $this->em->createQueryBuilder()
            ->from(Testcase::class, 'tc', 'tc.ranknumber')
            ->join('tc.content', 'content')
            ->select('tc', 'LENGTH(content.input) AS input_size', 'LENGTH(content.output) AS output_size',
                     'LENGTH(content.image) AS image_size', 'tc.image_type')
            ->andWhere('tc.problem = :problem')
            ->setParameter('problem', $problem)
            ->orderBy('tc.ranknumber')
            ->getQuery()
            ->getResult();

        /** @var Testcase[] $testcases */
        $testcases = array_map(fn($data) => $data[0], $testcaseData);

        if ($request->isMethod('POST')) {
            if ($lockedContest) {
                $this->addFlash('danger', 'Cannot edit problem / testcases, it belongs to locked contest c' . $contestProblem->getContest()->getCid());
                return $this->redirectToRoute('jury_problem', ['probId' => $problem->getProbid()]);
            }
            $messages      = [];
            $maxrank       = 0;
            $outputLimit   = $this->config->get('output_limit');
            $thumbnailSize = $this->config->get('thumbnail_size');
            foreach ($testcases as $rank => $testcase) {
                $newSample = isset($request->request->get('sample')[$rank]);
                if ($newSample !== $testcase->getSample()) {
                    $testcase->setSample($newSample);
                    $messages[] = sprintf('Set testcase %d to %sbe a sample testcase', $rank, $newSample ? '' : 'not ');
                }

                $newDescription = $request->request->get('description')[$rank];
                if ($newDescription !== $testcase->getDescription(true)) {
                    $testcase->setDescription($newDescription);
                    $messages[] = sprintf('Updated description of testcase %d ', $rank);
                }

                foreach (['input', 'output', 'image'] as $type) {
                    /** @var UploadedFile $file */
                    if ($file = $request->files->get('update_' . $type)[$rank]) {
                        if (!$file->isValid()) {
                            $this->addFlash('danger', sprintf('File upload error %s %s: %s. No changes made.', $type, $rank, $file->getErrorMessage()));
                            return $this->redirectToRoute('jury_problem_testcases', ['probId' => $probId]);
                        }
                        $content = file_get_contents($file->getRealPath());
                        if ($type === 'image') {
                            $imageType = Utils::getImageType($content, $error);
                            if ($imageType === false) {
                                $this->addFlash('danger', sprintf('image: %s', $error));
                                return $this->redirectToRoute('jury_problem_testcases', ['probId' => $probId]);
                            }
                            $thumb = Utils::getImageThumb($content, $thumbnailSize,
                                                          $this->dj->getDomjudgeTmpDir(), $error);
                            if ($thumb === false) {
                                $this->addFlash('danger', sprintf('image: %s', $error));
                                return $this->redirectToRoute('jury_problem_testcases', ['probId' => $probId]);
                            }

                            $testcase->setImageType($imageType);
                            $testcase->getContent()
                                ->setImageThumb($thumb)
                                ->setimage($content);
                        } else {
                            $contentMethod = sprintf('set%s', ucfirst($type));
                            $md5Method     = sprintf('setMd5sum%s', ucfirst($type));
                            $testcase->getContent()->{$contentMethod}($content);
                            $testcase->{$md5Method}(md5($content));
                            if ($type === 'input') {
                                $testcase->setOrigInputFilename(basename($file->getClientOriginalName(), '.in'));
                            }
                        }

                        $this->dj->auditlog('testcase', $probId, 'updated',
                                            sprintf('%s rank %d', $type, $rank));

                        $message = sprintf('Updated %s for testcase %d with file %s (%s)',
                                           $type, $rank,
                                           $file->getClientOriginalName(),
                                           Utils::printsize($file->getSize()));

                        if ($type === 'output' && $file->getSize() > $outputLimit * 1024) {
                            $message .= sprintf(
                                "\nWarning: file size exceeds output_limit " .
                                'of %s kB. This will always result in wrong answers!',
                                $outputLimit
                            );
                        }

                        $messages[] = $message;
                    }
                }

                if ($rank > $maxrank) {
                    $maxrank = $rank;
                }
            }

            $maxrank++;

            $allOk = true;
            $inputOrOutputSpecified = false;
            foreach (['input', 'output'] as $type) {
                if ($request->files->get('add_' . $type)) {
                    $inputOrOutputSpecified = true;
                }
            }
            if ($inputOrOutputSpecified) {
                foreach (['input', 'output'] as $type) {
                    if (!$file = $request->files->get('add_' . $type)) {
                        $messages[] = sprintf(
                            'Warning: new %s file was not selected, not adding new testcase',
                            $type
                        );
                        $allOk = false;
                    } elseif (!$file->isValid()) {
                        $this->addFlash('danger', sprintf(
                            'File upload error new %s: %s. No changes made.',
                            $type, $file->getErrorMessage()
                        ));
                        return $this->redirectToRoute('jury_problem_testcases', ['probId' => $probId]);
                    }
                }
            }

            $haswarnings = false;
            if ($inputOrOutputSpecified && $allOk) {
                $newTestcase        = new Testcase();
                $newTestcaseContent = new TestcaseContent();
                $newTestcase
                    ->setContent($newTestcaseContent)
                    ->setRank($maxrank)
                    ->setProblem($problem)
                    ->setDescription($request->request->get('add_desc'))
                    ->setSample($request->request->has('add_sample'));
                foreach (['input', 'output'] as $type) {
                    $file          = $request->files->get('add_' . $type);
                    $content       = file_get_contents($file->getRealPath());
                    $contentMethod = sprintf('set%s', ucfirst($type));
                    $md5Method     = sprintf('setMd5sum%s', ucfirst($type));
                    $newTestcaseContent->{$contentMethod}($content);
                    $newTestcase->{$md5Method}(md5($content));
                    if ($type === 'input') {
                        $newTestcase->setOrigInputFilename(basename($file->getClientOriginalName(), '.in'));
                    }
                }

                if ($imageFile = $request->files->get('add_image')) {
                    if (!$imageFile->isValid()) {
                        $this->addFlash('danger', sprintf(
                            'File upload error new image: %s',
                            $imageFile->getErrorMessage()
                        ));
                        return $this->redirectToRoute('jury_problem_testcases', ['probId' => $probId]);
                    }
                    $content   = file_get_contents($imageFile->getRealPath());
                    $imageType = Utils::getImageType($content, $error);
                    if ($imageType === false) {
                        $this->addFlash('danger', sprintf('image: %s', $error));
                        return $this->redirectToRoute('jury_problem_testcases', ['probId' => $probId]);
                    }
                    $thumb = Utils::getImageThumb($content, $thumbnailSize,
                                                  $this->dj->getDomjudgeTmpDir(), $error);
                    if ($thumb === false) {
                        $this->addFlash('danger', sprintf('image: %s', $error));
                        return $this->redirectToRoute('jury_problem_testcases', ['probId' => $probId]);
                    }

                    $newTestcase->setImageType($imageType);
                    $newTestcaseContent
                        ->setImageThumb($thumb)
                        ->setimage($content);
                }

                $this->em->persist($newTestcase);
                $this->dj->auditlog('testcase', $probId, 'added', sprintf("rank %d", $maxrank));

                $inFile  = $request->files->get('add_input');
                $outFile = $request->files->get('add_output');
                $message = sprintf(
                    'Added new testcase %d from files %s (%s) and %s (%s)', $maxrank,
                    $inFile->getClientOriginalName(), Utils::printsize($inFile->getSize()),
                    $outFile->getClientOriginalName(), Utils::printsize($outFile->getSize())
                );

                if (strlen($newTestcaseContent->getOutput()) > $outputLimit * 1024) {
                    $message .= sprintf(
                        "\nWarning: file size exceeds output_limit " .
                        'of %s kB. This will always result in wrong answers!',
                        $outputLimit
                    );
                    $haswarnings = true;
                }

                if (empty($newTestcaseContent->getInput()) ||
                    empty($newTestcaseContent->getOutput())) {
                    $message .= "\nWarning: empty testcase file(s)!\n";
                    $haswarnings = true;
                }

                $messages[] = $message;
            }

            $this->em->flush();

            if (!empty($messages)) {
                $this->addFlash($haswarnings ? 'warning' : 'info', implode("\n", $messages));
            }
            return $this->redirectToRoute('jury_problem_testcases', ['probId' => $probId]);
        }

        $known_md5s = [];
        foreach ($testcases as $rank => $testcase) {
            $input_md5 = $testcase->getMd5sumInput();
            if (isset($known_md5s[$input_md5])) {
                $this->addFlash('warning',
                    "Testcase #" . $rank . " has identical input to testcase #" . $known_md5s[$input_md5] . '.');
            }
            $known_md5s[$input_md5] = $rank;
        }

        if ($lockedContest) {
            $this->addFlash('warning',
                'Problem belongs to a locked contest, disallowing editing.');
        }
        $data = [
            'problem' => $problem,
            'testcases' => $testcases,
            'testcaseData' => $testcaseData,
            'extensionMapping' => Testcase::EXTENSION_MAPPING,
            'allowEdit' => $this->isGranted('ROLE_ADMIN') && !$lockedContest,
        ];

        return $this->render('jury/problem_testcases.html.twig', $data);
    }

    /**
     * @Route(
     *     "/{probId<\d+>}/testcases/{rank<\d+>}/move/{direction<up|down>}",
     *     name="jury_problem_testcase_move"
     *     )
     * @IsGranted("ROLE_ADMIN")
     */
    public function moveTestcaseAction(int $probId, int $rank, string $direction): Response
    {
        /** @var Problem $problem */
        $problem = $this->em->getRepository(Problem::class)->find($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        foreach ($problem->getContestProblems() as $contestProblem) {
            /** @var ContestProblem $contestProblem */
            if ($contestProblem->getContest()->isLocked()) {
                $this->addFlash('danger', 'Cannot edit problem, it belongs to locked contest c' . $contestProblem->getContest()->getCid());
                return $this->redirectToRoute('jury_problem', ['probId' => $problem->getProbid()]);
            }
        }

        /** @var Testcase[] $testcases */
        $testcases = $this->em->createQueryBuilder()
            ->from(Testcase::class, 'tc', 'tc.ranknumber')
            ->select('tc')
            ->andWhere('tc.problem = :problem')
            ->setParameter('problem', $problem)
            ->orderBy('tc.ranknumber')
            ->getQuery()
            ->getResult();

        // First find testcase to switch with.
        /** @var Testcase|null $last */
        $last = null;
        /** @var Testcase|null $other */
        $other = null;
        /** @var Testcase|null $current */
        $current = null;

        $numTestcases = count($testcases);

        foreach ($testcases as $testcaseRank => $testcase) {
            if ($testcaseRank === $rank) {
                $current = $testcase;
            }
            if ($testcaseRank === $rank && $direction === 'up') {
                $other = $last;
                break;
            }
            if ($last !== null && $rank === $last->getRank() && $direction === 'down') {
                $other = $testcase;
                break;
            }
            $last = $testcase;
        }

        if ($current !== null && $other !== null) {
            // (probid, rank) is a unique key, so we must switch via a temporary rank, and use a transaction.
            $this->em->wrapInTransaction(function () use ($current, $other, $numTestcases) {
                $otherRank   = $other->getRank();
                $currentRank = $current->getRank();
                $other->setRank($numTestcases + 1);
                $current->setRank($numTestcases + 2);
                $this->em->flush();
                $current->setRank($otherRank);
                $other->setRank($currentRank);
            });

            $this->dj->auditlog('testcase', $probId, 'switch rank',
                                             sprintf("%d <=> %d", $current->getRank(), $other->getRank()));
        }

        return $this->redirectToRoute('jury_problem_testcases', ['probId' => $probId]);
    }

    /**
     * @Route(
     *     "/{probId<\d+>}/testcases/{rank<\d+>}/fetch/{type<input|output|image>}",
     *     name="jury_problem_testcase_fetch"
     *     )
     * @throws NonUniqueResultException
     */
    public function fetchTestcaseAction(int $probId, int $rank, string $type): Response
    {
        /** @var Testcase $testcase */
        $testcase = $this->em->createQueryBuilder()
            ->from(Testcase::class, 'tc')
            ->join('tc.content', 'tcc')
            ->select('tc', 'tcc')
            ->andWhere('tc.problem = :problem')
            ->andWhere('tc.ranknumber = :ranknumber')
            ->setParameter('problem', $probId)
            ->setParameter('ranknumber', $rank)
            ->getQuery()
            ->getOneOrNullResult();
        if (!$testcase) {
            throw new NotFoundHttpException(sprintf('Testcase with rank %d for problem %d not found', $rank, $probId));
        }

        if ($type === 'image') {
            $extension = $testcase->getImageType();
            $mimetype  = sprintf('image/%s', $extension);
            $filename  = sprintf('p%d.t%d.%s', $probId, $rank, $extension);
        } else {
            $extension = Testcase::EXTENSION_MAPPING[$type];
            $mimetype  = 'text/plain';
            $filename  = sprintf('%s.%s', $testcase->getDownloadName(), $extension);
        }

        $content  = null;

        switch ($type) {
            case 'input':
                $content = $testcase->getContent()->getInput();
                break;
            case 'output':
                $content = $testcase->getContent()->getOutput();
                break;
            case 'image':
                $content = $testcase->getContent()->getImage();
                break;
        }

        $response = new StreamedResponse();
        $response->setCallback(function () use ($content) {
            echo $content;
        });
        $response->headers->set('Content-Type', sprintf('%s; name="%s', $mimetype, $filename));
        $response->headers->set('Content-Disposition', sprintf('inline; filename="%s"', $filename));
        $response->headers->set('Content-Length', strlen($content));

        return $response;
    }

    /**
     * @Route("/{probId<\d+>}/edit", name="jury_problem_edit")
     * @IsGranted("ROLE_ADMIN")
     */
    public function editAction(Request $request, int $probId): Response
    {
        /** @var Problem $problem */
        $problem = $this->em->getRepository(Problem::class)->find($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        foreach ($problem->getContestProblems() as $contestProblem) {
            /** @var ContestProblem $contestProblem */
            if ($contestProblem->getContest()->isLocked()) {
                $this->addFlash('danger', 'Cannot edit problem, it belongs to locked contest c' . $contestProblem->getContest()->getCid());
                return $this->redirectToRoute('jury_problem', ['probId' => $problem->getProbid()]);
            }
        }

        $form = $this->createForm(ProblemType::class, $problem);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->saveEntity($this->em, $this->eventLogService, $this->dj, $problem,
                              $problem->getProbid(), false);
            return $this->redirectToRoute('jury_problem', ['probId' => $problem->getProbid()]);
        }

        $data       = [];
        $uploadForm = $this->createForm(ProblemUploadType::class, $data);
        $uploadForm->handleRequest($request);

        if ($uploadForm->isSubmitted() && $uploadForm->isValid()) {
            $data = $uploadForm->getData();
            /** @var UploadedFile $archive */
            $archive  = $data['archive'];
            $messages = [];

            /** @var Contest|null $contest */
            $contest = $data['contest'] ?? null;
            /** @var ContestProblem $contestProblem */
            foreach ($problem->getContestProblems() as $contestProblem) {
                if (($currentContest = $this->dj->getCurrentContest()) !== null &&
                    $contestProblem->getCid() === $currentContest->getCid()) {
                    $contest = $currentContest;
                    break;
                }
            }
            try {
                $zip        = $this->dj->openZipFile($archive->getRealPath());
                $clientName = $archive->getClientOriginalName();
                if ($this->importProblemService->importZippedProblem(
                    $zip, $clientName, $problem, $contest, $messages
                )) {
                    $this->dj->auditlog('problem', $problem->getProbid(), 'upload zip', $clientName);
                } else {
                    $this->addFlash('danger', implode("\n", $messages));
                    return $this->redirectToRoute('jury_problem', ['probId' => $problem->getProbid()]);
                }
            } catch (Exception $e) {
                $messages['danger'][] = $e->getMessage();
            } finally {
                if (isset($zip)) {
                    $zip->close();
                }
            }

            foreach (['info', 'warning', 'danger'] as $type) {
                if (!empty($messages[$type])) {
                    $this->addFlash($type, implode("\n", $messages[$type]));
                }
            }

            return $this->redirectToRoute('jury_problem', ['probId' => $problem->getProbid()]);
        }

        return $this->render('jury/problem_edit.html.twig', [
            'problem' => $problem,
            'form' => $form->createView(),
            'uploadForm' => $uploadForm->createView(),
        ]);
    }

    /**
     * @Route("/{probId<\d+>}/delete", name="jury_problem_delete")
     * @IsGranted("ROLE_ADMIN")
     */
    public function deleteAction(Request $request, int $probId): Response
    {
        /** @var Problem $problem */
        $problem = $this->em->getRepository(Problem::class)->find($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        foreach ($problem->getContestProblems() as $contestProblem) {
            /** @var ContestProblem $contestProblem */
            if ($contestProblem->getContest()->isLocked()) {
                $this->addFlash('danger', 'Cannot delete problem, it belongs to locked contest c' . $contestProblem->getContest()->getCid());
                return $this->redirectToRoute('jury_problem', ['probId' => $probId]);
            }
        }

        return $this->deleteEntities($request, $this->em, $this->dj, $this->eventLogService, $this->kernel,
                                     [$problem], $this->generateUrl('jury_problems'));
    }

    /**
     * @Route("/attachments/{attachmentId<\d+>}", name="jury_attachment_fetch")
     */
    public function fetchAttachmentAction(int $attachmentId): StreamedResponse
    {
        /** @var ProblemAttachment $attachment */
        $attachment = $this->em->getRepository(ProblemAttachment::class)->find($attachmentId);
        if (!$attachment) {
            throw new NotFoundHttpException(sprintf('Attachment with ID %s not found',
                $attachmentId));
        }

        return $attachment->getStreamedResponse();
    }

    /**
     * @Route("/attachments/{attachmentId<\d+>}/delete", name="jury_attachment_delete")
     * @IsGranted("ROLE_ADMIN")
     */
    public function deleteAttachmentAction(Request $request, int $attachmentId): Response
    {
        /** @var ProblemAttachment $attachment */
        $attachment = $this->em->getRepository(ProblemAttachment::class)->find($attachmentId);
        if (!$attachment) {
            throw new NotFoundHttpException(sprintf('Attachment with ID %s not found', $attachmentId));
        }

        $problem = $attachment->getProblem();
        $probId = $problem->getProbid();

        foreach ($problem->getContestProblems() as $contestProblem) {
            /** @var ContestProblem $contestProblem */
            if ($contestProblem->getContest()->isLocked()) {
                $this->addFlash('danger', 'Cannot edit problem, it belongs to locked contest c' . $contestProblem->getContest()->getCid());
                return $this->redirectToRoute('jury_problem', ['probId' => $probId]);
            }
        }

        return $this->deleteEntities($request, $this->em, $this->dj, $this->eventLogService, $this->kernel,
                                     [$attachment], $this->generateUrl('jury_problem', ['probId' => $probId]));
    }

    /**
     * @Route("/{testcaseId<\d+>}/delete_testcase", name="jury_testcase_delete")
     * @IsGranted("ROLE_ADMIN")
     */
    public function deleteTestcaseAction(Request $request, int $testcaseId): Response
    {
        /** @var Testcase $testcase */
        $testcase = $this->em->getRepository(Testcase::class)->find($testcaseId);
        if (!$testcase) {
            throw new NotFoundHttpException(sprintf('Testcase with ID %s not found', $testcaseId));
        }
        $problem = $testcase->getProblem();
        foreach ($problem->getContestProblems() as $contestProblem) {
            /** @var ContestProblem $contestProblem */
            if ($contestProblem->getContest()->isLocked()) {
                $this->addFlash('danger', 'Cannot edit problem, it belongs to locked contest c' . $contestProblem->getContest()->getCid());
                return $this->redirectToRoute('jury_problem', ['probId' => $problem->getProbid()]);
            }
        }
        $testcase->setDeleted(true);
        $testcase->setProblem(null);
        $oldRank = $testcase->getRank();

        /** @var Testcase[] $testcases */
        $testcases = $this->em->getRepository(Testcase::class)
            ->findBy(['problem' => $problem], ['ranknumber' => 'ASC']);
        foreach ($testcases as $testcase) {
            if ($testcase->getRank() > $oldRank) {
                $testcase->setRank($testcase->getRank() - 1);
            }
        }
        $this->em->flush();
        $this->addFlash('danger', sprintf('Testcase %d removed from problem %s. Consider rejudging the problem.', $testcaseId, $problem->getProbid()));
        return $this->redirectToRoute('jury_problem_testcases', ['probId' => $problem->getProbid()]);
    }

    /**
     * @Route("/add", name="jury_problem_add")
     * @IsGranted("ROLE_ADMIN")
     */
    public function addAction(Request $request): Response
    {
        $problem = new Problem();

        $form = $this->createForm(ProblemType::class, $problem);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($problem);
            $this->saveEntity($this->em, $this->eventLogService, $this->dj, $problem, null, true);
            return $this->redirect($this->generateUrl(
                'jury_problem',
                ['probId' => $problem->getProbid()]
            ));
        }

        return $this->render('jury/problem_add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    private function addTestcasesToZip(array $testcases, ZipArchive $zip, bool $isSample): void
    {
        $formatString = sprintf('data/%%s/%%0%dd', ceil(log10(count($testcases) + 1)));
        $rankInGroup = 0;
        foreach ($testcases as $testcase) {
            $rankInGroup++;
            $filename = sprintf($formatString, $isSample ? 'sample' : 'secret', $rankInGroup);
            $zip->addFromString($filename . '.in', $testcase->getContent()->getInput());
            $zip->addFromString($filename . '.ans', $testcase->getContent()->getOutput());

            if (!empty($testcase->getDescription(true))) {
                $description = $testcase->getDescription(true);
                if (strstr($description, "\n") === false) {
                    $description .= "\n";
                }
                $zip->addFromString($filename . '.desc', $description);
            }

            if (!empty($testcase->getImageType())) {
                $zip->addFromString($filename . '.' . $testcase->getImageType(),
                                    $testcase->getContent()->getImage());
            }
        }
    }

    /**
     * @Route("/{probId<\d+>}/request-remaining", name="jury_problem_request_remaining")
     */
    public function requestRemainingRunsWholeProblemAction(string $probId): RedirectResponse
    {
        /** @var Problem $problem */
        $problem = $this->em->getRepository(Problem::class)->find($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }
        $contestId = $this->dj->getCurrentContest()->getCid();
        $query = $this->em->createQueryBuilder()
                          ->from(Judging::class, 'j')
                          ->select('j')
                          ->join('j.submission', 's')
                          ->join('s.team', 't')
                          ->andWhere('j.valid = true')
                          ->andWhere('j.result != :compiler_error')
                          ->andWhere('s.problem = :probId')
                          ->setParameter('compiler_error', 'compiler-error')
                          ->setParameter('probId', $probId);
        if ($contestId > -1) {
            $query->andWhere('s.contest = :contestId')
                  ->setParameter('contestId', $contestId);
        }
        $judgings = $query->getQuery()
                          ->getResult();
        $this->judgeRemaining($judgings);
        return $this->redirect($this->generateUrl('jury_problem', ['probId' => $probId]));
    }
}
