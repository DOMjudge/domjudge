<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\DataTransferObject\SubmissionRestriction;
use App\DataTransferObject\TestcaseViewRow;
use App\Entity\Contest;
use App\Entity\ContestProblem;
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
use App\Service\StatisticsService;
use App\Service\SubmissionService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Exception;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

#[IsGranted('ROLE_JURY')]
#[Route(path: '/jury/problems')]
class ProblemController extends BaseController
{
    use JudgeRemainingTrait;

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        KernelInterface $kernel,
        protected readonly EventLogService $eventLogService,
        protected readonly SubmissionService $submissionService,
        protected readonly ImportProblemService $importProblemService,
    ) {
        parent::__construct($em, $eventLogService, $dj, $kernel);
    }

    #[Route(path: '', name: 'jury_problems')]
    public function indexAction(): Response
    {
        $problems = $this->em->createQueryBuilder()
            ->select('p', 'COUNT(tc.testcaseid) AS testdatacount')
            ->from(Problem::class, 'p')
            ->leftJoin('p.testcases', 'tc')
            ->orderBy('p.probid', 'ASC')
            ->groupBy('p.probid')
            ->getQuery()->getResult();

        $table_fields = [
            'externalid' => ['title' => 'ID', 'sort' => true],
            'name' => ['title' => 'name', 'sort' => true],
            'badges' => ['title' => '', 'sort' => true],
            'num_contests' => ['title' => '# contests', 'sort' => true],
            'timelimit' => ['title' => 'time limit', 'sort' => true],
            'memlimit' => ['title' => 'memory limit', 'sort' => true],
            'outputlimit' => ['title' => 'output limit', 'sort' => true],
            'num_testcases' => ['title' => '# test cases', 'sort' => true],
            'type' => ['title' => 'type', 'sort' => true],
        ];

        if ($this->dj->getCurrentContest() !== null) {
            $table_fields['badges']['default_sort'] = true;
        } else {
            $table_fields['badges']['externalid'] = true;
        }

        $this->addSelectAllCheckbox($table_fields, 'problems');

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
        $problems_table_current = [];
        $problems_table_other   = [];
        foreach ($problems as $row) {
            /** @var Problem $p */
            $p              = $row[0];
            $problemdata    = [];
            $problemactions = [];

            $this->addEntityCheckbox($problemdata, $p, $p->getExternalid(), 'problem-checkbox', fn(Problem $problem) => !$problem->isLocked());

            // Get whatever fields we can from the problem object itself.
            foreach ($table_fields as $k => $v) {
                if ($propertyAccessor->isReadable($p, $k)) {
                    $problemdata[$k] = ['value' => $propertyAccessor->getValue($p, $k)];
                }
            }

            // Create action links
            if ($p->getProblemstatementType()) {
                $problemactions[] = [
                    'icon' => 'file-' . $p->getProblemstatementType(),
                    'title' => 'view problem statement',
                    'link' => $this->generateUrl('jury_problem_statement', [
                        'probId' => $p->getExternalid(),
                    ])
                ];
            } else {
                $problemactions[] = [];
            }
            $problemactions[] = [
                'icon' => 'save',
                'title' => 'export problem as zip-file',
                'link' => $this->generateUrl('jury_export_problem', [
                    'problemId' => $p->getExternalid(),
                ])
            ];

            if ($this->isGranted('ROLE_ADMIN')) {
                $problemactions[] = [
                    'icon' => 'edit',
                    'title' => 'edit this problem',
                    'link' => $this->generateUrl('jury_problem_edit', [
                        'probId' => $p->getExternalid(),
                    ])
                ];

                $problemIsLocked = false;
                foreach ($p->getContestProblems() as $contestProblem) {
                    if ($contestProblem->getContest()->isLocked()) {
                        $problemIsLocked = true;
                    }
                }

                $deleteAction = [
                    'icon' => 'trash-alt',
                    'title' => 'delete this problem',
                    'link' => $this->generateUrl('jury_problem_delete', [
                        'probId' => $p->getExternalid(),
                    ]),
                    'ajaxModal' => true,
                ];
                if ($problemIsLocked) {
                    $deleteAction['title'] .= ' - problem belongs to a locked contest';
                    $deleteAction['disabled'] = true;
                    unset($deleteAction['link']);
                }
                $problemactions[] = $deleteAction;
            }
            $default_memlimit = $this->config->get('memory_limit');
            $default_output_limit = $this->config->get('output_limit');

            // Add formatted {mem,output}limit row data for the table.
            foreach (['memlimit', 'outputlimit'] as $col) {
                $orig_value = @$problemdata[$col]['value'];
                if (!isset($orig_value)) {
                    $value = 'default';
                    if ($col == 'memlimit' && !empty($default_memlimit)) {
                        $value .= ' (' . Utils::printsize(1024 * $default_memlimit) . ')';
                    }
                    if ($col == 'outputlimit' && !empty($default_output_limit)) {
                        $value .= ' (' . Utils::printsize(1024 * $default_output_limit) . ')';
                    }
                    $problemdata[$col] = [
                        'value' => $value,
                        'cssclass' => 'disabled',
                    ];
                } else {
                    $problemdata[$col] = [
                        'value' => Utils::printsize(1024 * $orig_value),
                        'sortvalue' => $orig_value,
                        'cssclass' => 'right',
                    ];
                }
            }
            $problemdata['timelimit']['value'] = @$problemdata['timelimit']['value'] . 's';
            $problemdata['timelimit']['cssclass'] = 'right';

            $contestProblems = $p->getContestProblems()->toArray();
            $badges = [];
            if ($this->dj->getCurrentContest() !== null) {
                $badges = array_filter($contestProblems, fn($cp) => $cp->getCid() === $this->dj->getCurrentContest()->getCid());
            }
            $problemdata['badges'] = [
                'value' => $badges,
                'sortvalue' => implode(', ', array_map(fn(ContestProblem $problem) => $problem->getShortname(), $badges)),
            ];

            // merge in the rest of the data
            $problemdata = array_merge($problemdata, [
                'num_contests' => ['value' => (int)($contestCounts[$p->getProbid()] ?? 0)],
                'num_testcases' => ['value' => (int)$row['testdatacount']],
                'type' => ['value' => $p->getTypesAsString()],
            ]);

            $data_to_add = [
                'data' => $problemdata,
                'actions' => $problemactions,
                'link' => $this->generateUrl('jury_problem', ['probId' => $p->getExternalid()]),
            ];
            if ($badges) {
                $problems_table_current[] = $data_to_add;
            } else {
                $problems_table_other[] = $data_to_add;
            }
        }
        $data = [
            'problems_current' => $problems_table_current,
            'problems_other' => $problems_table_other,
            'table_fields' => $table_fields,
        ];

        return $this->render('jury/problems.html.twig', $data);
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route(path: '/problemset', name: 'jury_problemset')]
    public function problemsetAction(StatisticsService $stats): Response
    {
        $teamId = $this->dj->getUser()->getTeam()?->getTeamid();
        return $this->render('jury/problemset.html.twig',
            $this->dj->getTwigDataForProblemsAction($stats, teamId: $teamId, forJury: true));
    }

    #[Route(path: '/{probId}/samples.zip', name: 'jury_problem_sample_zip')]
    public function sampleZipAction(string $probId): StreamedResponse
    {
        $contest = $this->dj->getCurrentContest();
        $contestProblem = $this->em->getRepository(ContestProblem::class)->findByProblemAndContest($contest, $probId);
        if (!$contestProblem) {
            throw new NotFoundHttpException(sprintf('Problem %s not found or not available', $probId));
        }
        return $this->dj->getSamplesZipStreamedResponse($contestProblem);
    }

    /**
     * @throws NonUniqueResultException
     */
    #[IsGranted('ROLE_JURY')]
    #[Route(path: '/{problemId}/export', name: 'jury_export_problem')]
    public function exportAction(string $problemId): StreamedResponse
    {
        // This might take a while.
        Utils::extendMaxExecutionTime(300);
        /** @var Problem $problem */
        $problem = $this->em->createQueryBuilder()
            ->from(Problem::class, 'p')
            ->leftJoin('p.contest_problems', 'cp', Join::WITH, 'cp.contest = :contest')
            ->leftJoin('p.problemStatementContent', 'content')
            ->select('p', 'cp', 'content')
            ->andWhere('p.externalid = :problemId')
            ->setParameter('problemId', $problemId)
            ->setParameter('contest', $this->dj->getCurrentContest())
            ->getQuery()
            ->getOneOrNullResult();

        /** @var ContestProblem|null $contestProblem */
        $contestProblem = $problem->getContestProblems()->first() ?: null;

        // Build up INI data.
        $iniData = [
            'timelimit' => $problem->getTimelimit(),
            'special_run' => $problem->getRunExecutable()?->getExecid(),
            'special_compare' => $problem->getCompareExecutable()?->getExecid(),
            'color' => $contestProblem?->getColor(),
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
        } elseif ($problem->isInteractiveProblem() && !empty($problem->getRunExecutable())) {
            $yaml['validation'] = 'custom interactive';
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

        if (!empty($problem->getProblemstatement())) {
            $zip->addFromString('problem.' . $problem->getProblemstatementType(),
                $problem->getProblemstatement());
        }

        $compareExecutable = null;
        if ($problem->getCompareExecutable()) {
            $compareExecutable = $problem->getCompareExecutable();
        } elseif ($problem->isInteractiveProblem()) {
            $compareExecutable = $problem->getRunExecutable();
        }
        if ($compareExecutable) {
            foreach ($compareExecutable->getImmutableExecutable()->getFiles() as $file) {
                $filename = sprintf('output_validators/%s/%s', $compareExecutable->getExecid(), $file->getFilename());
                $zip->addFromString($filename, $file->getFileContent());
                if ($file->isExecutable()) {
                    // 100755 = regular file, executable
                    $zip->setExternalAttributesName(
                        $filename,
                        ZipArchive::OPSYS_UNIX,
                        octdec('100755') << 16
                    );
                }
            }
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
            $results = $solution->getExpectedResults() ?? [];
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
            $directory = sprintf('submissions/%s/%s/', $problemResult, $solution->getExternalid());
            /** @var SubmissionFile $source */
            foreach ($solution->getFiles() as $source) {
                $zip->addFromString($directory . $source->getFilename(), $source->getSourcecode());
            }
        }
        $zip->close();

        if ($contestProblem && $contestProblem->getShortname()) {
            $zipFilename = sprintf('%s.zip', $contestProblem->getShortname());
        } else {
            $zipFilename = sprintf('%s.zip', $problem->getExternalid());
        }

        return Utils::streamZipFile($tempFilename, $zipFilename);
    }

    /**
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    #[Route(path: '/{probId}', name: 'jury_problem')]
    public function viewAction(Request $request, SubmissionService $submissionService, string $probId): Response
    {
        $problem = $this->em->getRepository(Problem::class)->findByExternalId($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        $lockedProblem = false;
        foreach ($problem->getContestProblems() as $contestProblem) {
            /** @var ContestProblem $contestProblem */
            if ($contestProblem->getContest()->isLocked()) {
                if (!$request->isXmlHttpRequest()) {
                    $this->addFlash('warning', 'Cannot edit problem, it belongs to locked contest ' . $contestProblem->getContest()->getExternalid());
                }
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
            if (count($fileParts) > 1) {
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
                ->setMimeType(mime_content_type($file->getRealPath()))
                ->setContent($attachmentContent);

            $this->em->persist($attachment);
            $this->em->flush();

            return $this->redirectToRoute('jury_problem', ['probId' => $probId]);
        }

        /** @var PaginationInterface<int, Submission> $submissions */
        [$submissions, $submissionCounts] = $submissionService->getSubmissionList(
            $this->dj->getCurrentContests(honorCookie: true),
            new SubmissionRestriction(problemId: $problem->getProbid()),
            page: $request->query->getInt('page', 1),
        );

        $data = [
            'problem' => $problem,
            'previousNext' => $this->getPreviousAndNextObjectIds(
                Problem::class,
                $problem->getExternalid(),
            ),
            'problemAttachmentForm' => $problemAttachmentForm->createView(),
            'submissions' => $submissions,
            'submissionCounts' => $submissionCounts,
            'defaultMemoryLimit' => (int)$this->config->get('memory_limit'),
            'defaultOutputLimit' => (int)$this->config->get('output_limit'),
            'defaultRunExecutable' => (string)$this->config->get('default_run'),
            'defaultCompareExecutable' => (string)$this->config->get('default_compare'),
            'type' => $problem->getTypesAsString(),
            'showContest' => count($this->dj->getCurrentContests(honorCookie: true)) > 1,
            'showExternalResult' => $this->dj->shadowMode(),
            'lockedProblem' => $lockedProblem,
            'refresh' => [
                'after' => 15,
                'url' => $this->generateUrl('jury_problem', ['probId' => $problem->getExternalid()]),
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

    #[Route(path: '/{probId}/statement', name: 'jury_problem_statement')]
    public function viewTextAction(string $probId): StreamedResponse
    {
        $problem = $this->em->getRepository(Problem::class)->findByExternalId($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        return $problem->getProblemStatementStreamedResponse();
    }

    #[Route(path: '/{probId}/testcases', name: 'jury_problem_testcases')]
    public function testcasesAction(Request $request, string $probId): Response
    {
        $problem = $this->em->getRepository(Problem::class)->findByExternalId($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        $lockedContests = [];
        foreach ($problem->getContestProblems() as $contestproblem) {
            /** @var ContestProblem $contestproblem */
            if ($contestproblem->getContest()->isLocked()) {
                $lockedContests[] = $contestproblem->getContest()->getExternalid();
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

        $rows        = [];
        $lastLineage = [];
        foreach ($testcaseData as $data) {
            /** @var Testcase $testcase */
            $testcase = $data[0];
            $lineage  = $testcase->getTestcaseGroup() ? $testcase->getTestcaseGroup()->getLineage() : [];

            $commonPrefixLength = 0;
            $foundDiff          = false;
            foreach ($lineage as $i => $group) {
                if (!$foundDiff && isset($lastLineage[$i]) && $lastLineage[$i]->getTestcaseGroupId() === $group->getTestcaseGroupId()) {
                    $commonPrefixLength = $i + 1;
                } else {
                    $foundDiff = true;
                    $rows[]    = new TestcaseViewRow(
                        type: TestcaseViewRow::TYPE_GROUP,
                        group: $group,
                        level: $i
                    );
                }
            }

            if (empty($lineage) && !empty($lastLineage)) {
                $rows[] = new TestcaseViewRow(type: TestcaseViewRow::TYPE_NO_GROUP);
            }

            $rows[] = new TestcaseViewRow(
                type: TestcaseViewRow::TYPE_TESTCASE,
                testcase: $testcase,
                inputSize: (int)$data['input_size'],
                outputSize: (int)$data['output_size'],
                imageSize: (int)$data['image_size'],
            );

            $lastLineage = $lineage;
        }

        if ($request->isMethod('POST')) {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
            if (!empty($lockedContests)) {
                $this->addFlash('danger', 'Cannot edit problem / testcases, it belongs to locked contest(s) '
                    . join(', ', $lockedContests));
                return $this->redirectToRoute('jury_problem', ['probId' => $probId]);
            }
            $messages      = [];
            $maxrank       = 0;
            $outputLimit   = $this->config->get('output_limit');
            $thumbnailSize = $this->config->get('thumbnail_size');
            foreach ($testcases as $rank => $testcase) {
                $newSample = isset($request->request->all('sample')[$rank]);
                if ($newSample !== $testcase->getSample()) {
                    $testcase->setSample($newSample);
                    $messages[] = sprintf('Set testcase %d to %sbe a sample testcase', $rank, $newSample ? '' : 'not ');
                }

                $newDescription = $request->request->all('description')[$rank];
                if ($newDescription !== $testcase->getDescription(true)) {
                    $testcase->setDescription($newDescription);
                    $messages[] = sprintf('Updated description of testcase %d ', $rank);
                }

                foreach (['input', 'output', 'image'] as $type) {
                    /** @var UploadedFile $file */
                    if ($file = $request->files->all('update_' . $type)[$rank]) {
                        if (!$file->isValid()) {
                            $this->addFlash('danger', sprintf('File upload error %s %s: %s. No changes made.', $type, $rank, $file->getErrorMessage()));
                            return $this->redirectToRoute('jury_problem_testcases', ['probId' => $probId]);
                        }
                        $content = file_get_contents($file->getRealPath());
                        if ($type === 'image') {
                            if (mime_content_type($file->getRealPath()) === 'image/svg+xml') {
                                $originalContent = $content;
                                $content = Utils::sanitizeSvg($content);
                                if ($content === false) {
                                    $imageType = Utils::getImageType($originalContent, $error);
                                    $this->addFlash('danger', sprintf('image: %s', $error));
                                    return $this->redirectToRoute('jury_problem_testcases', ['probId' => $probId]);
                                }
                                $thumb = $content;
                                $imageType = 'svg';
                            } else {
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

                        $this->dj->auditlog('testcase', $problem->getExternalid(), 'updated',
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

                        if ($type !== 'image') {
                            foreach (Utils::detectTestcaseEncoding($content, $file->getClientOriginalName()) as $encodingWarning) {
                                $message .= "\nWarning: " . $encodingWarning;
                            }
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
                $this->dj->auditlog('testcase', $problem->getExternalid(), 'added', sprintf("rank %d", $maxrank));

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

                foreach (['input', 'output'] as $tcType) {
                    $tcFile = $request->files->get('add_' . $tcType);
                    $tcContent = $tcType === 'input'
                        ? $newTestcaseContent->getInput()
                        : $newTestcaseContent->getOutput();
                    foreach (Utils::detectTestcaseEncoding($tcContent, $tcFile->getClientOriginalName()) as $encodingWarning) {
                        $message .= "\nWarning: " . $encodingWarning;
                        $haswarnings = true;
                    }
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

        if (!empty($lockedContests)) {
            $this->addFlash('warning',
                'Problem belongs to locked contest ('
                . join($lockedContests)
                . ', disallowing editing.');
        }
        $data = [
            'problem' => $problem,
            'testcases' => $testcases,
            'testcaseData' => $testcaseData,
            'rows' => $rows,
            'extensionMapping' => Testcase::EXTENSION_MAPPING,
            'allowEdit' => $this->isGranted('ROLE_ADMIN') && empty($lockedContests),
        ];

        return $this->render('jury/problem_testcases.html.twig', $data);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/{probId}/testcases/{rank<\d+>}/move/{direction<up|down>}', name: 'jury_problem_testcase_move')]
    public function moveTestcaseAction(string $probId, int $rank, string $direction): Response
    {
        $problem = $this->em->getRepository(Problem::class)->findByExternalId($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        foreach ($problem->getContestProblems() as $contestProblem) {
            /** @var ContestProblem $contestProblem */
            if ($contestProblem->getContest()->isLocked()) {
                $this->addFlash('danger', 'Cannot edit problem, it belongs to locked contest c' . $contestProblem->getContest()->getCid());
                return $this->redirectToRoute('jury_problem', ['probId' => $probId]);
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
            $this->em->wrapInTransaction(function () use ($current, $other, $numTestcases): void {
                $otherRank   = $other->getRank();
                $currentRank = $current->getRank();
                $other->setRank($numTestcases + 1);
                $current->setRank($numTestcases + 2);
                $this->em->flush();
                $current->setRank($otherRank);
                $other->setRank($currentRank);
            });

            $this->dj->auditlog('testcase', $problem->getExternalid(), 'switch rank',
                                             sprintf("%d <=> %d", $current->getRank(), $other->getRank()));
        }

        return $this->redirectToRoute('jury_problem_testcases', ['probId' => $probId]);
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route(path: '/{probId}/testcases/{rank<\d+>}/fetch/{type<input|output|image>}', name: 'jury_problem_testcase_fetch')]
    public function fetchTestcaseAction(string $probId, int $rank, string $type): Response
    {
        /** @var Testcase|null $testcase */
        $testcase = $this->em->createQueryBuilder()
            ->from(Testcase::class, 'tc')
            ->join('tc.content', 'tcc')
            ->join('tc.problem', 'p')
            ->select('tc', 'tcc')
            ->andWhere('p.externalid = :problem')
            ->andWhere('tc.ranknumber = :ranknumber')
            ->setParameter('problem', $probId)
            ->setParameter('ranknumber', $rank)
            ->getQuery()
            ->getOneOrNullResult();
        if (!$testcase) {
            throw new NotFoundHttpException(sprintf('Testcase with rank %d for problem %s not found', $rank, $probId));
        }

        if ($type === 'image') {
            $extension = $testcase->getImageType();
            $mimetype  = sprintf('image/%s', $extension);
            $filename  = sprintf('%s.t%d.%s', $probId, $rank, $extension);
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
        $response->setCallback(function () use ($content): void {
            echo $content;
        });
        $response->headers->set('Content-Type', sprintf('%s; name="%s', $mimetype, $filename));
        $response->headers->set('Content-Disposition', sprintf('inline; filename="%s"', $filename));
        $response->headers->set('Content-Length', (string)strlen($content));

        return $response;
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/{probId}/edit', name: 'jury_problem_edit')]
    public function editAction(Request $request, string $probId): Response
    {
        $problem = $this->em->getRepository(Problem::class)->findByExternalId($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        foreach ($problem->getContestProblems() as $contestProblem) {
            /** @var ContestProblem $contestProblem */
            if ($contestProblem->getContest()->isLocked()) {
                $this->addFlash('danger', 'Cannot edit problem, it belongs to locked contest ' . $contestProblem->getContest()->getExternalid());
                return $this->redirectToRoute('jury_problem', ['probId' => $probId]);
            }
        }

        $form = $this->createForm(ProblemType::class, $problem);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->saveEntity($problem, $problem->getProbid(), false);
            return $this->redirectToRoute('jury_problem', ['probId' => $probId]);
        }

        $data       = [];
        $uploadForm = $this->createForm(ProblemUploadType::class, $data);
        $uploadForm->handleRequest($request);

        if ($uploadForm->isSubmitted() && $uploadForm->isValid()) {
            $data = $uploadForm->getData();
            /** @var UploadedFile $archive */
            $archive  = $data['archive'];
            /** @var array<string, string[]> $messages */
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
                    $this->dj->auditlog('problem', $problem->getExternalid(), 'upload zip', $clientName);
                } else {
                    $this->postMessages($messages);
                    return $this->redirectToRoute('jury_problem', ['probId' => $probId]);
                }
            } catch (Exception $e) {
                $messages['danger'][] = $e->getMessage();
            } finally {
                if (isset($zip)) {
                    $zip->close();
                }
            }
            $this->postMessages($messages);

            return $this->redirectToRoute('jury_problem', ['probId' => $probId]);
        }

        return $this->render('jury/problem_edit.html.twig', [
            'problem' => $problem,
            'form' => $form,
            'uploadForm' => $uploadForm,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/delete-multiple', name: 'jury_problem_delete_multiple', methods: ['GET', 'POST'], priority: 1)]
    public function deleteMultipleAction(Request $request): Response
    {
        return $this->deleteMultiple(
            $request,
            Problem::class,
            'externalid',
            'jury_problems',
            'No problems could be deleted (they might be locked).',
            fn(Problem $problem) => !$problem->isLocked()
        );
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/{probId}/delete', name: 'jury_problem_delete')]
    public function deleteAction(Request $request, string $probId): Response
    {
        $problem = $this->em->getRepository(Problem::class)->findByExternalId($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        foreach ($problem->getContestProblems() as $contestProblem) {
            /** @var ContestProblem $contestProblem */
            if ($contestProblem->getContest()->isLocked()) {
                $this->addFlash('danger', 'Cannot delete problem, it belongs to locked contest ' . $contestProblem->getContest()->getExternalid());
                return $this->redirectToRoute('jury_problem', ['probId' => $probId]);
            }
        }

        return $this->deleteEntities($request, [$problem], $this->generateUrl('jury_problems'));
    }

    #[Route(path: '/attachments/{attachmentId<\d+>}', name: 'jury_attachment_fetch')]
    public function fetchAttachmentAction(int $attachmentId): StreamedResponse
    {
        $attachment = $this->em->getRepository(ProblemAttachment::class)->find($attachmentId);
        if (!$attachment) {
            throw new NotFoundHttpException(sprintf('Attachment with ID %s not found',
                $attachmentId));
        }

        return $attachment->getStreamedResponse();
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/attachments/{attachmentId<\d+>}/delete', name: 'jury_attachment_delete')]
    public function deleteAttachmentAction(Request $request, int $attachmentId): Response
    {
        $attachment = $this->em->getRepository(ProblemAttachment::class)->find($attachmentId);
        if (!$attachment) {
            throw new NotFoundHttpException(sprintf('Attachment with ID %s not found', $attachmentId));
        }

        $problem = $attachment->getProblem();
        $probId = $problem->getExternalid();

        foreach ($problem->getContestProblems() as $contestProblem) {
            /** @var ContestProblem $contestProblem */
            if ($contestProblem->getContest()->isLocked()) {
                $this->addFlash('danger', 'Cannot edit problem, it belongs to locked contest c' . $contestProblem->getContest()->getCid());
                return $this->redirectToRoute('jury_problem', ['probId' => $probId]);
            }
        }

        return $this->deleteEntities($request, [$attachment], $this->generateUrl('jury_problem', ['probId' => $probId]));
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/{testcaseId<\d+>}/delete_testcase', name: 'jury_testcase_delete')]
    public function deleteTestcaseAction(Request $request, int $testcaseId): Response
    {
        $testcase = $this->em->getRepository(Testcase::class)->find($testcaseId);
        if (!$testcase) {
            throw new NotFoundHttpException(sprintf('Testcase with ID %s not found', $testcaseId));
        }
        $problem = $testcase->getProblem();
        foreach ($problem->getContestProblems() as $contestProblem) {
            /** @var ContestProblem $contestProblem */
            if ($contestProblem->getContest()->isLocked()) {
                $this->addFlash('danger', 'Cannot edit problem, it belongs to locked contest ' . $contestProblem->getContest()->getExternalid());
                return $this->redirectToRoute('jury_problem', ['probId' => $problem->getExternalid()]);
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
        $this->addFlash('danger', sprintf('Testcase %d removed from problem %s. Consider rejudging the problem.', $testcaseId, $problem->getExternalid()));
        return $this->redirectToRoute('jury_problem_testcases', ['probId' => $problem->getExternalid()]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/add', name: 'jury_problem_add', priority: 1)]
    public function addAction(Request $request): Response
    {
        $problem = new Problem();

        $form = $this->createForm(ProblemType::class, $problem);

        $form->handleRequest($request);

        if ($response = $this->processAddFormForExternalIdEntity(
            $form, $problem,
            fn() => $this->generateUrl('jury_problem', ['probId' => $problem->getExternalid()])
        )) {
            return $response;
        }

        return $this->render('jury/problem_add.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * @param Testcase[] $testcases
     *
     * Assumes testcases are in order of their rank.
     */
    private function addTestcasesToZip(array $testcases, ZipArchive $zip, bool $isSample): void
    {

        // Verify whether order of original filenames matches order of testcases by rank.
        // If so, prefer their original name, otherwise replace the name with the rank to ensure same order.
        $prev = null;
        $isStillSorted = true;
        foreach ($testcases as $testcase) {
            if ($prev !== null && $prev >= $testcase->getOrigInputFilename()) {
                $isStillSorted = false;
                break;
            }
            $prev = $testcase->getOrigInputFilename();
        }

        $formatString = sprintf('data/%%s/%%0%dd', ceil(log10(count($testcases) + 1)));
        $rankInGroup = 0;
        foreach ($testcases as $testcase) {
            $rankInGroup++;
            if ($isStillSorted) {
                $filenamePrefix = sprintf("data/%s/%s", $isSample ? 'sample' : 'secret', $testcase->getOrigInputFilename());
            } else {
                $filenamePrefix = sprintf($formatString, $isSample ? 'sample' : 'secret', $rankInGroup);
            }
            $zip->addFromString($filenamePrefix . '.in', $testcase->getContent()->getInput());
            $zip->addFromString($filenamePrefix . '.ans', $testcase->getContent()->getOutput());

            if (!empty($testcase->getDescription(true))) {
                $description = $testcase->getDescription(true);
                if (!str_contains($description, "\n")) {
                    $description .= "\n";
                }
                $zip->addFromString($filenamePrefix . '.desc', $description);
            }

            if (!empty($testcase->getImageType())) {
                $zip->addFromString($filenamePrefix . '.' . $testcase->getImageType(),
                                    $testcase->getContent()->getImage());
            }
        }
    }

    #[Route(path: '/{probId}/request-remaining', name: 'jury_problem_request_remaining')]
    public function requestRemainingRunsWholeProblemAction(string $probId): RedirectResponse
    {
        $problem = $this->em->getRepository(Problem::class)->findByExternalId($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }
        $contestId = $this->dj->getCurrentContest()->getExternalid();
        $this->judgeRemaining(contestId: $contestId, probId: $probId);
        return $this->redirectToRoute('jury_problem', ['probId' => $probId]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/{contestId}/{probId}/toggle/{type<judge|submit>}', name: 'jury_problem_toggle')]
    public function toggleSubmitAction(
        RouterInterface $router,
        Request $request,
        string $contestId,
        string $probId,
        string $type
    ): Response {
        $contestProblem = $this->em->getRepository(ContestProblem::class)->findByProblemAndContest($contestId, $probId);
        if (!$contestProblem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found for contest %s', $probId, $contestId));
        }

        $value = $request->request->getBoolean('value');

        switch ($type) {
            case 'judge':
                $contestProblem->setAllowJudge($value);
                $label = 'set allow judge';
                break;
            case 'submit':
                $contestProblem->setAllowSubmit($value);
                $label = 'set allow submit';
                break;
            default:
                throw new BadRequestHttpException('Unknown toggle type');
        }
        $this->em->flush();

        $id = [$contestProblem->getExternalId(), $contestProblem->getExternalId()];
        $this->dj->auditlog('contest_problem', implode(', ', $id), $label, $value ? 'yes' : 'no');
        return $this->redirectToLocalReferrer($router, $request, $this->generateUrl('jury_problems'));
    }

    /**
     * @param array<string, string[]> $allMessages
     */
    private function postMessages(array $allMessages): void
    {
        foreach (['info', 'warning', 'danger'] as $type) {
            if (!empty($allMessages[$type])) {
                $this->addFlash($type, implode("\n", $allMessages[$type]));
            }
        }
    }
}
