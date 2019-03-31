<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use DOMJudgeBundle\Controller\BaseController;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\ContestProblem;
use DOMJudgeBundle\Entity\Problem;
use DOMJudgeBundle\Entity\Submission;
use DOMJudgeBundle\Entity\SubmissionFileWithSourceCode;
use DOMJudgeBundle\Entity\Testcase;
use DOMJudgeBundle\Entity\TestcaseWithContent;
use DOMJudgeBundle\Form\Type\ProblemType;
use DOMJudgeBundle\Form\Type\ProblemUploadMultipleType;
use DOMJudgeBundle\Form\Type\ProblemUploadType;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use DOMJudgeBundle\Service\ImportProblemService;
use DOMJudgeBundle\Service\SubmissionService;
use DOMJudgeBundle\Utils\Utils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;


/**
 * @Route("/jury/problems")
 * @Security("has_role('ROLE_JURY')")
 */
class ProblemController extends BaseController
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var DOMJudgeService
     */
    private $DOMJudgeService;

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

    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        EventLogService $eventLogService,
        SubmissionService $submissionService,
        ImportProblemService $importProblemService
    ) {
        $this->entityManager        = $entityManager;
        $this->DOMJudgeService      = $DOMJudgeService;
        $this->eventLogService      = $eventLogService;
        $this->submissionService    = $submissionService;
        $this->importProblemService = $importProblemService;
    }

    /**
     * @Route("", name="jury_problems")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function indexAction(Request $request)
    {
        $formData = [
            'contest' => $this->DOMJudgeService->getCurrentContest(),
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
                    $zip        = $this->DOMJudgeService->openZipFile($archive->getRealPath());
                    $clientName = $archive->getClientOriginalName();
                    $messages   = [];
                    if ($contestId === null) {
                        $contest = null;
                    } else {
                        $contest = $this->entityManager->getRepository(Contest::class)->find($contestId);
                    }
                    $newProblem  = $this->importProblemService->importZippedProblem($zip, $clientName, null, $contest,
                                                                                    $messages, $errorMessage);
                    $allMessages = array_merge($allMessages, $messages);
                    if ($newProblem) {
                        $this->DOMJudgeService->auditlog('problem', $newProblem->getProbid(), 'upload zip',
                                                         $clientName);
                    } else {
                        $this->addFlash('danger', $errorMessage);
                        return $this->redirectToRoute('jury_problems');
                    }
                } finally {
                    $zip->close();
                }
            }

            if (!empty($messages)) {
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

        $problems = $this->entityManager->createQueryBuilder()
            ->select('p', 'COUNT(tc.testcaseid) AS testdatacount')
            ->from('DOMJudgeBundle:Problem', 'p')
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
                'num_contests' => ['value' => (int)($p->getContestProblems()->count())],
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
            $contests                = $this->entityManager->getRepository(Contest::class)->findAll();
            $data['contests']        = $contests;
            $data['current_contest'] = $this->DOMJudgeService->getCurrentContest();
        }

        return $this->render('@DOMJudge/jury/problems.html.twig', $data);
    }

    /**
     * @Route("/{problemId}/export", name="jury_export_problem")
     * @Security("has_role('ROLE_ADMIN')")
     * @param int $problemId
     * @return StreamedResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function exportAction(int $problemId)
    {
        // This might take a while
        ini_set('max_execution_time', '300');
        /** @var Problem $problem */
        $problem = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Problem', 'p')
            ->leftJoin('p.contest_problems', 'cp', Join::WITH, 'cp.contest = :contest')
            ->select('p', 'cp')
            ->andWhere('p.probid = :problemId')
            ->setParameter(':problemId', $problemId)
            ->setParameter(':contest', $this->DOMJudgeService->getCurrentContest())
            ->getQuery()
            ->getOneOrNullResult();

        /** @var ContestProblem|null $contestProblem */
        $contestProblem = $problem->getContestProblems()->first();

        // Build up INI
        $iniData = [
            'probid' => $contestProblem ? $contestProblem->getShortname() : null,
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
        if (!($tempFilename = tempnam($this->DOMJudgeService->getDomjudgeTmpDir(), "export-"))) {
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

        /** @var TestcaseWithContent[] $testcases */
        $testcases = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:TestcaseWithContent', 't')
            ->select('t')
            ->andWhere('t.problem = :problem')
            ->setParameter(':problem', $problem)
            ->orderBy('t.rank')
            ->getQuery()
            ->getResult();

        foreach ($testcases as $testcase) {
            $filename = sprintf('data/%s/%d', $testcase->getSample() ? 'sample' : 'secret', $testcase->getRank());
            $zip->addFromString($filename . '.in', $testcase->getInput());
            $zip->addFromString($filename . '.ans', $testcase->getOutput());

            if (!empty($testcase->getDescription(true))) {
                $description = $testcase->getDescription(true);
                if (strstr($description, "\n") === false) {
                    $description .= "\n";
                }
                $zip->addFromString($filename . '.desc', $description);
            }

            if (!empty($testcase->getImageType())) {
                $zip->addFromString($filename . '.' . $testcase->getImageType(), $testcase->getImage());
            }
        }

        /** @var Submission[] $solutions */
        $solutions = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Submission', 's')
            ->select('s')
            ->andWhere('s.problem = :problem')
            ->andWhere('s.contest = :contest')
            ->andWhere('s.expected_results IS NOT NULL')
            ->setParameter(':problem', $problem)
            ->setParameter(':contest', $this->DOMJudgeService->getCurrentContest())
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
            /** @var SubmissionFileWithSourceCode $source */
            foreach ($solution->getFilesWithSourceCode() as $source) {
                $zip->addFromString($directory . $source->getFilename(), $source->getSourcecode());
            }
        }

        $zip->close();

        if ($contestProblem && $contestProblem->getShortname()) {
            $zipFilename = sprintf('p%d-%s.zip', $problem->getProbid(), $contestProblem->getShortname());
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
     * @Route("/{probId}", name="jury_problem", requirements={"probId": "\d+"})
     * @param Request           $request
     * @param SubmissionService $submissionService
     * @param int               $probId
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function viewAction(Request $request, SubmissionService $submissionService, int $probId)
    {
        /** @var Problem $problem */
        $problem = $this->entityManager->getRepository(Problem::class)->find($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        $restrictions = ['probid' => $problem->getProbid()];
        /** @var Submission[] $submissions */
        list($submissions, $submissionCounts) = $submissionService->getSubmissionList(
            $this->DOMJudgeService->getCurrentContests(),
            $restrictions
        );

        $data = [
            'problem' => $problem,
            'submissions' => $submissions,
            'submissionCounts' => $submissionCounts,
            'defaultMemoryLimit' => (int)$this->DOMJudgeService->dbconfig_get('memory_limit'),
            'defaultOutputLimit' => (int)$this->DOMJudgeService->dbconfig_get('output_limit'),
            'defaultRunExecutable' => (string)$this->DOMJudgeService->dbconfig_get('default_run'),
            'defaultCompareExecutable' => (string)$this->DOMJudgeService->dbconfig_get('default_compare'),
            'showContest' => count($this->DOMJudgeService->getCurrentContests()) > 1,
            'refresh' => [
                'after' => 15,
                'url' => $this->generateUrl('jury_problem', ['probId' => $problem->getProbid()]),
                'ajax' => true,
            ],
        ];

        // For ajax requests, only return the submission list partial
        if ($request->isXmlHttpRequest()) {
            $data['showTestcases'] = false;
            return $this->render('@DOMJudge/jury/partials/submission_list.html.twig', $data);
        }

        return $this->render('@DOMJudge/jury/problem.html.twig', $data);
    }

    /**
     * @Route("/{probId}/text", name="jury_problem_text", requirements={"probId": "\d+"})
     * @param int $probId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function viewTextAction(int $probId)
    {
        /** @var Problem $problem */
        $problem = $this->entityManager->getRepository(Problem::class)->find($probId);
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
     * @Route("/{probId}/testcases", name="jury_problem_testcases", requirements={"probId": "\d+"})
     * @param Request $request
     * @param int     $probId
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function testcasesAction(Request $request, int $probId)
    {
        /** @var Problem $problem */
        $problem = $this->entityManager->getRepository(Problem::class)->find($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        $testcaseData = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Testcase', 'tc', 'tc.rank')
            ->join('tc.testcase_content', 'content')
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
            $outputLimit   = $this->DOMJudgeService->dbconfig_get('output_limit');
            $thumbnailSize = $this->DOMJudgeService->dbconfig_get('thumbnail_size', 128);
            foreach ($testcases as $rank => $testcase) {
                $newSample = isset($request->request->get('sample')[$rank]);
                if ($newSample !== $testcase->getSample()) {
                    $testcase->setSample($newSample);
                    $messages[] = sprintf('Set testcase %d to %sbe a sample testcase', $rank, $newSample ? '' : 'not ');
                }

                foreach (['input', 'output', 'image'] as $type) {
                    /** @var UploadedFile $file */
                    if ($file = $request->files->get('update_' . $type)[$rank]) {
                        $content = file_get_contents($file->getRealPath());
                        if ($type === 'image') {
                            $imageType = Utils::getImageType($content, $error);
                            if ($imageType === false) {
                                $this->addFlash('danger', sprintf('image: %s', $error));
                                return $this->redirectToRoute('jury_problem_testcases', ['probId' => $probId]);
                            }
                            $thumb = Utils::getImageThumb($content, $thumbnailSize,
                                                          $this->DOMJudgeService->getDomjudgeTmpDir(), $error);
                            if ($thumb === false) {
                                $thumb = null;
                                $this->addFlash('danger', sprintf('image: %s', $error));
                                return $this->redirectToRoute('jury_problem_testcases', ['probId' => $probId]);
                            }

                            $testcase->getTestcaseContent()
                                ->setImageThumb($thumb)
                                ->setimage($content)
                                ->setImageType($imageType);
                        } else {
                            $contentMethod = sprintf('set%s', ucfirst($type));
                            $md5Method     = sprintf('setMd5sum%s', ucfirst($type));
                            $testcase->getTestcaseContent()->{$contentMethod}($content);
                            $testcase->getTestcaseContent()->{$md5Method}(md5($content));
                        }

                        $this->DOMJudgeService->auditlog('testcase', $probId, 'updated',
                                                         sprintf('%s rank %d', $type, $rank));

                        $message = sprintf('Updated %s for testcase %d with file %s (%s)', $type, $rank,
                                           $file->getClientOriginalName(), Utils::printsize($file->getSize()));

                        if ($type == 'output' && $file->getSize() > $outputLimit * 1024) {
                            $message .= sprintf('<br><b>Warning: file size exceeds <code>output_limit</code> of %s kB. This will always result in wrong answers!</b>',
                                                $outputLimit);
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
            foreach (['input', 'output'] as $type) {
                if (!$request->files->get('add_' . $type)) {
                    $messages[] = sprintf('<b>Warning: new %s file was not selected, not adding new testcase</b>',
                                          $type);
                    $allOk      = false;
                }
            }

            if ($allOk) {
                $newTestcase = new TestcaseWithContent();
                $newTestcase
                    ->setRank($maxrank)
                    ->setProblem($problem)
                    ->setDescription($request->request->get('add_desc'))
                    ->setSample($request->request->has('add_sample'));
                foreach (['input', 'output'] as $type) {
                    $file          = $request->files->get('add_' . $type);
                    $content       = file_get_contents($file->getRealPath());
                    $contentMethod = sprintf('set%s', ucfirst($type));
                    $md5Method     = sprintf('setMd5sum%s', ucfirst($type));
                    $newTestcase->{$contentMethod}($content);
                    $newTestcase->{$md5Method}(md5($content));
                }

                if ($imageFile = $request->files->get('add_image')) {
                    $content   = file_get_contents($imageFile->getRealPath());
                    $imageType = Utils::getImageType($content, $error);
                    if ($imageType === false) {
                        $this->addFlash('danger', sprintf('image: %s', $error));
                        return $this->redirectToRoute('jury_problem_testcases', ['probId' => $probId]);
                    }
                    $thumb = Utils::getImageThumb($content, $thumbnailSize,
                                                  $this->DOMJudgeService->getDomjudgeTmpDir(), $error);
                    if ($thumb === false) {
                        $thumb = null;
                        $this->addFlash('danger', sprintf('image: %s', $error));
                        return $this->redirectToRoute('jury_problem_testcases', ['probId' => $probId]);
                    }

                    $newTestcase
                        ->setImageThumb($thumb)
                        ->setimage($content)
                        ->setImageType($imageType);
                }

                $this->entityManager->persist($newTestcase);
                $this->DOMJudgeService->auditlog('testcase', $probId, 'added', sprintf("rank %d", $maxrank));

                $inFile  = $request->files->get('add_input');
                $outFile = $request->files->get('add_output');
                $message = sprintf('Added new testcase %d from files %s (%s) and %s (%s)', $maxrank,
                                   $inFile->getClientOriginalName(), Utils::printsize($inFile->getSize()),
                                   $outFile->getClientOriginalName(), Utils::printsize($outFile->getSize()));

                if ($newTestcase->getOutput() > $outputLimit * 1024) {
                    $message .= sprintf('<br><b>Warning: file size exceeds <code>output_limit</code> of %s kB. This will always result in wrong answers!</b>',
                                        $outputLimit);
                }

                if (empty($newTestcase->getInput()) || empty($newTestcase->getOutput())) {
                    $message .= '<br /><b>Warning: empty testcase file(s)!</b>';
                }

                $messages[] = $message;
            }

            $this->entityManager->flush();

            if (!empty($messages)) {
                $message = '<ul>' . implode('', array_map(function (string $message) {
                        return sprintf('<li>%s</li>', $message);
                    }, $messages)) . '</ul>';

                $this->addFlash('info', $message);
            }
            return $this->redirectToRoute('jury_problem_testcases', ['probId' => $probId]);
        }

        $data = [
            'problem' => $problem,
            'testcases' => $testcases,
            'testcaseData' => $testcaseData,
        ];

        return $this->render('@DOMJudge/jury/problem_testcases.html.twig', $data);
    }

    /**
     * @Route(
     *     "/{probId}/testcases/{rank}/move/{direction}",
     *     name="jury_problem_testcase_move",
     *     requirements={"probId": "\d+", "rank": "\d+", "direction": "up|down"}
     *     )
     * @param int    $probId
     * @param int    $rank
     * @param string $direction
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function moveTestcaseAction(int $probId, int $rank, string $direction)
    {
        /** @var Problem $problem */
        $problem = $this->entityManager->getRepository(Problem::class)->find($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        /** @var Testcase[] $testcases */
        $testcases = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Testcase', 'tc', 'tc.rank')
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
            $this->entityManager->transactional(function () use ($current, $other) {
                $otherRank   = $other->getRank();
                $currentRank = $current->getRank();
                $other->setRank(-1);
                $current->setRank(-2);
                $this->entityManager->flush();
                $current->setRank($otherRank);
                $other->setRank($currentRank);
            });

            $this->DOMJudgeService->auditlog('testcase', $probId, 'switch rank',
                                             sprintf("%d <=> %d", $current->getRank(), $other->getRank()));
        }

        return $this->redirectToRoute('jury_problem_testcases', ['probId' => $probId]);
    }

    /**
     * @Route(
     *     "/{probId}/testcases/{rank}/fetch/{type}",
     *     name="jury_problem_testcase_fetch",
     *     requirements={"probId": "\d+", "rank": "\d+", "type": "input|output|image"}
     *     )
     * @param int    $probId
     * @param int    $rank
     * @param string $type
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function fetchTestcaseAction(int $probId, int $rank, string $type)
    {
        /** @var TestcaseWithContent $testcase */
        $testcase = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:TestcaseWithContent', 'tc')
            ->select('tc')
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
                $content = $testcase->getInput();
                break;
            case 'output':
                $content = $testcase->getOutput();
                break;
            case 'image':
                $content = $testcase->getImage();
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
     * @Route("/{probId}/edit", name="jury_problem_edit", requirements={"probId": "\d+"})
     * @Security("has_role('ROLE_ADMIN')")
     * @param Request $request
     * @param int     $probId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function editAction(Request $request, int $probId)
    {
        /** @var Problem $problem */
        $problem = $this->entityManager->getRepository(Problem::class)->find($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        $form = $this->createForm(ProblemType::class, $problem);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->saveEntity($this->entityManager, $this->eventLogService, $this->DOMJudgeService, $problem,
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
                if (($currentContest = $this->DOMJudgeService->getCurrentContest()) !== null &&
                    $contestProblem->getCid() === $currentContest->getCid()) {
                    $contest = $currentContest;
                    break;
                }
            }
            try {
                $zip        = $this->DOMJudgeService->openZipFile($archive->getRealPath());
                $clientName = $archive->getClientOriginalName();
                if ($this->importProblemService->importZippedProblem($zip, $clientName, $problem, $contest, $messages,
                                                                     $errorMessage)) {
                    $this->DOMJudgeService->auditlog('problem', $problem->getProbid(), 'upload zip', $clientName);
                } else {
                    $this->addFlash('danger', $errorMessage);
                    return $this->redirectToRoute('jury_problem', ['probId' => $problem->getProbid()]);
                }
            } finally {
                $zip->close();
            }

            if (!empty($messages)) {
                $message = '<ul>' . implode('', array_map(function (string $message) {
                        return sprintf('<li>%s</li>', $message);
                    }, $messages)) . '</ul>';

                $this->addFlash('info', $message);
            }

            return $this->redirectToRoute('jury_problem', ['probId' => $problem->getProbid()]);
        }

        return $this->render('@DOMJudge/jury/problem_edit.html.twig', [
            'problem' => $problem,
            'form' => $form->createView(),
            'uploadForm' => $uploadForm->createView(),
        ]);
    }

    /**
     * @Route("/{probId}/delete", name="jury_problem_delete", requirements={"probId": "\d+"})
     * @Security("has_role('ROLE_ADMIN')")
     * @param Request $request
     * @param int     $probId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function deleteAction(Request $request, int $probId)
    {
        /** @var Problem $problem */
        $problem = $this->entityManager->getRepository(Problem::class)->find($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        return $this->deleteEntity($request, $this->entityManager, $this->DOMJudgeService, $problem,
                                   $problem->getName(), $this->generateUrl('jury_problems'));
    }

    /**
     * @Route("/add", name="jury_problem_add")
     * @Security("has_role('ROLE_ADMIN')")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function addAction(Request $request)
    {
        $problem = new Problem();

        $form = $this->createForm(ProblemType::class, $problem);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($problem);
            $this->saveEntity($this->entityManager, $this->eventLogService, $this->DOMJudgeService, $problem,
                              $problem->getProbid(), true);
            return $this->redirect($this->generateUrl('jury_problem',
                                                      ['probId' => $problem->getProbid()]));
        }

        return $this->render('@DOMJudge/jury/problem_add.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
