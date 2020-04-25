<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\SubmissionFile;
use App\Entity\Testcase;
use App\Entity\TestcaseContent;
use App\Form\Type\ProblemType;
use App\Form\Type\ProblemUploadMultipleType;
use App\Form\Type\ProblemUploadType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ImportProblemService;
use App\Service\SubmissionService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var ConfigurationService
     */
    protected $config;

    /**
     * @var KernelInterface
     */
    protected $kernel;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * @var SubmissionService
     */
    protected $submissionService;

    /**
     * @var ImportProblemService
     */
    protected $importProblemService;

    /**
     * ProblemController constructor.
     *
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param ConfigurationService   $config
     * @param KernelInterface        $kernel
     * @param EventLogService        $eventLogService
     * @param SubmissionService      $submissionService
     * @param ImportProblemService   $importProblemService
     */
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
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws Exception
     */
    public function indexAction(Request $request)
    {
        $formData = [
            'contest' => $this->dj->getCurrentContest(),
        ];
        $form     = $this->createForm(ProblemUploadMultipleType::class, $formData);
        $form->handleRequest($request);

        if ($this->isGranted('ROLE_ADMIN') && $form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();

            /** @var UploadedFile[] $archives */
            $archives = $formData['archives'];
            /** @var Problem|null $newProblem */
            $newProblem = null;
            /** @var Contest|null $contest */
            $contest = $formData['contest'] ?? null;
            if ($contest === null) {
                $contestId = null;
            } else {
                $contestId = $contest->getCid();
            }
            $allMessages = [];
            foreach ($archives as $archive) {
                try {
                    $zip        = $this->dj->openZipFile($archive->getRealPath());
                    $clientName = $archive->getClientOriginalName();
                    $messages   = [];
                    if ($contestId === null) {
                        $contest = null;
                    } else {
                        $contest = $this->em->getRepository(Contest::class)->find($contestId);
                    }
                    $newProblem = $this->importProblemService->importZippedProblem(
                        $zip, $clientName, null, $contest, $messages
                    );
                    $allMessages = array_merge($allMessages, $messages);
                    if ($newProblem) {
                        $this->dj->auditlog('problem', $newProblem->getProbid(), 'upload zip',
                                            $clientName);
                    } else {
                        $message = '<ul>' . implode('', array_map(function (string $message) {
                                return sprintf('<li>%s</li>', $message);
                            }, $allMessages)) . '</ul>';
                        $this->addFlash('danger', $message);
                        return $this->redirectToRoute('jury_problems');
                    }
                } catch (Exception $e) {
                    $allMessages[] = $e->getMessage();
                } finally {
                    if (isset($zip)) {
                        $zip->close();
                    }
                }
            }

            if (!empty($allMessages)) {
                $message = '<ul>' . implode('', array_map(function (string $message) {
                        return sprintf('<li>%s</li>', $message);
                    }, $allMessages)) . '</ul>';

                $this->addFlash('info', $message);
            }

            if (count($archives) === 1 && $newProblem !== null) {
                return $this->redirectToRoute('jury_problem', ['probId' => $newProblem->getProbid()]);
            } else {
                return $this->redirectToRoute('jury_problems');
            }
        }

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

        // Insert external ID field when configured to use it
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
            // Get whatever fields we can from the problem object itself
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

            if ($this->isGranted('ROLE_ADMIN')) {
                $problemactions[] = [
                    'icon' => 'save',
                    'title' => 'export problem as zip-file',
                    'link' => $this->generateUrl('jury_export_problem', [
                        'problemId' => $p->getProbid(),
                    ])
                ];
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

            // Add formatted {mem,output}limit row data for the table
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
            'num_actions' => $this->isGranted('ROLE_ADMIN') ? 4 : 1,
            'form' => $form->createView(),
        ];

        if ($this->isGranted('ROLE_ADMIN')) {
            /** @var Contest[] $contests */
            $contests                = $this->em->getRepository(Contest::class)->findAll();
            $data['contests']        = $contests;
            $data['current_contest'] = $this->dj->getCurrentContest();
        }

        return $this->render('jury/problems.html.twig', $data);
    }

    /**
     * @Route("/{problemId<\d+>}/export", name="jury_export_problem")
     * @IsGranted("ROLE_ADMIN")
     * @param int $problemId
     * @return StreamedResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function exportAction(int $problemId)
    {
        // This might take a while
        ini_set('max_execution_time', '300');
        /** @var Problem $problem */
        $problem = $this->em->createQueryBuilder()
            ->from(Problem::class, 'p')
            ->leftJoin('p.contest_problems', 'cp', Join::WITH, 'cp.contest = :contest')
            ->select('p', 'cp')
            ->andWhere('p.probid = :problemId')
            ->setParameter(':problemId', $problemId)
            ->setParameter(':contest', $this->dj->getCurrentContest())
            ->getQuery()
            ->getOneOrNullResult();

        /** @var ContestProblem|null $contestProblem */
        $contestProblem = $problem->getContestProblems()->first();

        // Build up INI
        $iniData = [
            'timelimit' => $problem->getTimelimit(),
            'special_run' => $problem->getSpecialRun(),
            'special_compare' => $problem->getSpecialCompare(),
            'color' => $contestProblem ? $contestProblem->getColor() : null,
        ];

        $iniString = "";
        foreach ($iniData as $key => $value) {
            if (!empty($value)) {
                $iniString .= $key . "='" . $value . "'\n";
            }
        }

        // Build up YAML
        $yaml = ['name' => $problem->getName()];
        if (!empty($problem->getSpecialCompare())) {
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
                ->setParameter(':problem', $problem)
                ->setParameter(':sample', $isSample)
                ->orderBy('t.rank')
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
            ->setParameter(':problem', $problem)
            ->setParameter(':contest', $this->dj->getCurrentContest())
            ->getQuery()
            ->getResult();

        foreach ($solutions as $solution) {
            $results = $solution->getExpectedResults();
            // Only support single outcome solutions
            if (count($results) !== 1) {
                continue;
            }

            $result = reset($results);

            $problemResult = null;

            foreach (SubmissionService::PROBLEM_RESULT_REMAP as $key => $val) {
                if (trim(mb_strtoupper($result)) == $val) {
                    $problemResult = mb_strtolower($key);
                }
            }

            if ($problemResult === null) {
                // unsupported result
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
     * @param Request           $request
     * @param SubmissionService $submissionService
     * @param int               $probId
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws Exception
     */
    public function viewAction(Request $request, SubmissionService $submissionService, int $probId)
    {
        /** @var Problem $problem */
        $problem = $this->em->getRepository(Problem::class)->find($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        $restrictions = ['probid' => $problem->getProbid()];
        /** @var Submission[] $submissions */
        list($submissions, $submissionCounts) = $submissionService->getSubmissionList(
            $this->dj->getCurrentContests(),
            $restrictions
        );

        $data = [
            'problem' => $problem,
            'submissions' => $submissions,
            'submissionCounts' => $submissionCounts,
            'defaultMemoryLimit' => (int)$this->config->get('memory_limit'),
            'defaultOutputLimit' => (int)$this->config->get('output_limit'),
            'defaultRunExecutable' => (string)$this->config->get('default_run'),
            'defaultCompareExecutable' => (string)$this->config->get('default_compare'),
            'showContest' => count($this->dj->getCurrentContests()) > 1,
            'showExternalResult' => $this->config->get('data_source') ==
                DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL,
            'refresh' => [
                'after' => 15,
                'url' => $this->generateUrl('jury_problem', ['probId' => $problem->getProbid()]),
                'ajax' => true,
            ],
        ];

        // For ajax requests, only return the submission list partial
        if ($request->isXmlHttpRequest()) {
            $data['showTestcases'] = false;
            return $this->render('jury/partials/submission_list.html.twig', $data);
        }

        return $this->render('jury/problem.html.twig', $data);
    }

    /**
     * @Route("/{probId<\d+>}/text", name="jury_problem_text")
     * @param int $probId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function viewTextAction(int $probId)
    {
        /** @var Problem $problem */
        $problem = $this->em->getRepository(Problem::class)->find($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        switch ($problem->getProblemtextType()) {
            case 'pdf':
                $mimetype = 'application/pdf';
                break;
            case 'html':
                $mimetype = 'text/html';
                break;
            case 'txt':
                $mimetype = 'text/plain';
                break;
            default:
                throw new BadRequestHttpException(sprintf('Problem p%d text has unknown type', $probId));
        }

        $filename    = sprintf('prob-%s.%s', $problem->getName(), $problem->getProblemtextType());
        $problemText = stream_get_contents($problem->getProblemtext());

        $response = new StreamedResponse();
        $response->setCallback(function () use ($problemText) {
            echo $problemText;
        });
        $response->headers->set('Content-Type', sprintf('%s; name="%s', $mimetype, $filename));
        $response->headers->set('Content-Disposition', sprintf('inline; filename="%s"', $filename));
        $response->headers->set('Content-Length', strlen($problemText));

        return $response;
    }

    /**
     * @Route("/{probId<\d+>}/testcases", name="jury_problem_testcases")
     * @param Request $request
     * @param int     $probId
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws Exception
     */
    public function testcasesAction(Request $request, int $probId)
    {
        /** @var Problem $problem */
        $problem = $this->em->getRepository(Problem::class)->find($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        $testcaseData = $this->em->createQueryBuilder()
            ->from(Testcase::class, 'tc', 'tc.rank')
            ->join('tc.content', 'content')
            ->select('tc', 'LENGTH(content.input) AS input_size', 'LENGTH(content.output) AS output_size',
                     'LENGTH(content.image) AS image_size', 'tc.image_type')
            ->andWhere('tc.problem = :problem')
            ->setParameter(':problem', $problem)
            ->orderBy('tc.rank')
            ->getQuery()
            ->getResult();

        /** @var Testcase[] $testcases */
        $testcases = array_map(function ($data) {
            return $data[0];
        }, $testcaseData);

        if ($request->isMethod('POST')) {
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
                                $thumb = null;
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
                            if ($type == 'input') {
                                $testcase->setOrigInputFilename(basename($file->getClientOriginalName(), '.in'));
                            }
                        }

                        $this->dj->auditlog('testcase', $probId, 'updated',
                                            sprintf('%s rank %d', $type, $rank));

                        $message = sprintf('Updated %s for testcase %d with file %s (%s)',
                                           $type, $rank,
                                           $file->getClientOriginalName(),
                                           Utils::printsize($file->getSize()));

                        if ($type == 'output' && $file->getSize() > $outputLimit * 1024) {
                            $message .= sprintf(
                                '<br><b>Warning: file size exceeds <code>output_limit</code> ' .
                                'of %s kB. This will always result in wrong answers!</b>',
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
                if ($file = $request->files->get('add_' . $type)) {
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
                    if ($type == 'input') {
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
                        $thumb = null;
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
                    $inFile->getClientOriginalName(),  Utils::printsize($inFile->getSize()),
                    $outFile->getClientOriginalName(), Utils::printsize($outFile->getSize())
                );

                if (strlen($newTestcaseContent->getOutput()) > $outputLimit * 1024) {
                    $message .= sprintf(
                        '<br><b>Warning: file size exceeds <code>output_limit</code> ' .
                        'of %s kB. This will always result in wrong answers!</b>',
                        $outputLimit
                    );
                    $haswarnings = true;
                }

                if (empty($newTestcaseContent->getInput()) ||
                    empty($newTestcaseContent->getOutput())) {
                    $message .= '<br /><b>Warning: empty testcase file(s)!</b>';
                    $haswarnings = true;
                }

                $messages[] = $message;
            }

            $this->em->flush();

            if (!empty($messages)) {
                $message = '<ul>' . implode('', array_map(function (string $message) {
                        return sprintf('<li>%s</li>', $message);
                    }, $messages)) . '</ul>';

                $this->addFlash($haswarnings ? 'warning' : 'info', $message);
            }
            return $this->redirectToRoute('jury_problem_testcases', ['probId' => $probId]);
        }

        $data = [
            'problem' => $problem,
            'testcases' => $testcases,
            'testcaseData' => $testcaseData,
        ];

        return $this->render('jury/problem_testcases.html.twig', $data);
    }

    /**
     * @Route(
     *     "/{probId<\d+>}/testcases/{rank<\d+>}/move/{direction<up|down>}",
     *     name="jury_problem_testcase_move"
     *     )
     * @param int    $probId
     * @param int    $rank
     * @param string $direction
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function moveTestcaseAction(int $probId, int $rank, string $direction)
    {
        /** @var Problem $problem */
        $problem = $this->em->getRepository(Problem::class)->find($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        /** @var Testcase[] $testcases */
        $testcases = $this->em->createQueryBuilder()
            ->from(Testcase::class, 'tc', 'tc.rank')
            ->select('tc')
            ->andWhere('tc.problem = :problem')
            ->setParameter(':problem', $problem)
            ->orderBy('tc.rank')
            ->getQuery()
            ->getResult();

        // First find testcase to switch with
        /** @var Testcase|null $last */
        $last = null;
        /** @var Testcase|null $other */
        $other = null;
        /** @var Testcase|null $current */
        $current = null;

        $numTestcases = count($testcases);

        foreach ($testcases as $testcaseRank => $testcase) {
            if ($testcaseRank == $rank) {
                $current = $testcase;
            }
            if ($testcaseRank == $rank && $direction == 'up') {
                $other = $last;
                break;
            }
            if ($last !== null && $rank == $last->getRank() && $direction == 'down') {
                $other = $testcase;
                break;
            }
            $last = $testcase;
        }

        if ($current !== null && $other !== null) {
            // (probid, rank) is a unique key, so we must switch via a temporary rank, and use a transaction.
            $this->em->transactional(function () use ($current, $other, $numTestcases) {
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
     * @param int    $probId
     * @param int    $rank
     * @param string $type
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function fetchTestcaseAction(int $probId, int $rank, string $type)
    {
        /** @var Testcase $testcase */
        $testcase = $this->em->createQueryBuilder()
            ->from(Testcase::class, 'tc')
            ->join('tc.content', 'tcc')
            ->select('tc', 'tcc')
            ->andWhere('tc.probid = :problem')
            ->andWhere('tc.rank = :rank')
            ->setParameter(':problem', $probId)
            ->setParameter(':rank', $rank)
            ->getQuery()
            ->getOneOrNullResult();
        if (!$testcase) {
            throw new NotFoundHttpException(sprintf('Testcase with rank %d for problem %d not found', $rank, $probId));
        }

        if ($type === 'image') {
            $extension = $testcase->getImageType();
            $mimetype  = sprintf('image/%s', $extension);
        } else {
            $extension = substr($type, 0, -3);
            $mimetype  = 'text/plain';
        }

        $filename = sprintf('p%d.t%d.%s', $probId, $rank, $extension);
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
     * @param Request $request
     * @param int     $probId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws Exception
     */
    public function editAction(Request $request, int $probId)
    {
        /** @var Problem $problem */
        $problem = $this->em->getRepository(Problem::class)->find($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
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
                    $message = '<ul>' . implode('', array_map(function (string $message) {
                            return sprintf('<li>%s</li>', $message);
                        }, $messages)) . '</ul>';
                    $this->addFlash('danger', $message);
                    return $this->redirectToRoute('jury_problem', ['probId' => $problem->getProbid()]);
                }
            } catch (Exception $e) {
                $messages[] = $e->getMessage();
            } finally {
                if (isset($zip)) {
                    $zip->close();
                }
            }

            if (!empty($messages)) {
                $message = '<ul>' . implode('', array_map(function (string $message) {
                        return sprintf('<li>%s</li>', $message);
                    }, $messages)) . '</ul>';

                $this->addFlash('info', $message);
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
     * @param Request $request
     * @param int     $probId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws Exception
     */
    public function deleteAction(Request $request, int $probId)
    {
        /** @var Problem $problem */
        $problem = $this->em->getRepository(Problem::class)->find($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        return $this->deleteEntity($request, $this->em, $this->dj, $this->eventLogService, $this->kernel,
                                   $problem, $problem->getName(), $this->generateUrl('jury_problems'));
    }

    /**
     * @Route("/{testcaseId<\d+>}/delete_testcase", name="jury_testcase_delete")
     * @IsGranted("ROLE_ADMIN")
     * @param Request $request
     * @param int     $probId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws Exception
     */
    public function deleteTestcaseAction(Request $request, int $testcaseId)
    {
        /** @var Testcase $testcase */
        $testcase = $this->em->getRepository(Testcase::class)->find($testcaseId);
        if (!$testcase) {
            throw new NotFoundHttpException(sprintf('Testcase with ID %s not found', $testcaseId));
        }
        $testcase->setDeleted(true);
        $probId = $testcase->getProbid();
        $testcase->setProbid(null);
        $oldRank = $testcase->getRank();

        /** @var Testcase[] $testcases */
        $testcases = $this->em->getRepository(Testcase::class)
            ->findBy(['probid' => $probId], ['rank' => 'ASC']);
        foreach ($testcases as $testcase) {
            if ($testcase->getRank() > $oldRank) {
                $testcase->setRank($testcase->getRank() - 1);
            }
        }
        $this->em->flush();
        $this->addFlash('danger', sprintf('Testcase %d removed from problem %s. Consider rejudging the problem.', $testcaseId, $probId));
        return $this->redirectToRoute('jury_problem_testcases', ['probId' => $probId]);
    }

    /**
     * @Route("/add", name="jury_problem_add")
     * @IsGranted("ROLE_ADMIN")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws Exception
     */
    public function addAction(Request $request)
    {
        $problem = new Problem();

        $form = $this->createForm(ProblemType::class, $problem);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($problem);
            $this->saveEntity($this->em, $this->eventLogService, $this->dj, $problem,
                              $problem->getProbid(), true);
            return $this->redirect($this->generateUrl(
                'jury_problem',
                ['probId' => $problem->getProbid()]
            ));
        }

        return $this->render('jury/problem_add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param Testcase[] $testcases
     * @param ZipArchive $zip
     */
    public function addTestcasesToZip(array $testcases, ZipArchive $zip, bool $isSample)
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
}
