<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use DOMJudgeBundle\Controller\BaseController;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\ContestProblem;
use DOMJudgeBundle\Entity\Executable;
use DOMJudgeBundle\Entity\Language;
use DOMJudgeBundle\Entity\Problem;
use DOMJudgeBundle\Entity\Submission;
use DOMJudgeBundle\Entity\SubmissionFileWithSourceCode;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Entity\Testcase;
use DOMJudgeBundle\Entity\TestcaseWithContent;
use DOMJudgeBundle\Form\Type\ProblemType;
use DOMJudgeBundle\Form\Type\ProblemUploadMultipleType;
use DOMJudgeBundle\Form\Type\ProblemUploadType;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
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
 * @Route("/jury")
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

    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        EventLogService $eventLogService,
        SubmissionService $submissionService
    ) {
        $this->entityManager     = $entityManager;
        $this->DOMJudgeService   = $DOMJudgeService;
        $this->eventLogService   = $eventLogService;
        $this->submissionService = $submissionService;
    }

    /**
     * @Route("/problems/", name="jury_problems")
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
            $contest     = $formData['contest'] ?? null;
            $allMessages = [];
            foreach ($archives as $archive) {
                try {
                    $zip         = $this->DOMJudgeService->openZipFile($archive->getRealPath());
                    $clientName  = $archive->getClientOriginalName();
                    $messages    = [];
                    $newProblem  = $this->importZippedProblem($zip, $clientName, null, $contest, $messages);
                    $allMessages = array_merge($allMessages, $messages);
                    $this->DOMJudgeService->auditlog('problem', $newProblem->getProbid(), 'upload zip', $clientName);
                } finally {
                    $zip->close();
                }
            }

            if (!empty($messages)) {
                $message = '<ul>' . implode('', array_map(function (string $message) {
                        return sprintf('<li>%s</li>', $message);
                    }, $messages)) . '</ul>';

                $this->addFlash('problemZip', $message);
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
                    'link' => $this->generateUrl('legacy.jury_delete', [
                        'table' => 'problem',
                        'probid' => $p->getProbid(),
                        'referrer' => 'problems',
                        'desc' => $p->getName(),
                    ])
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
     * @Route("/problems/{problemId}/export", name="jury_export_problem")
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
            $zip->addFromString($filename . '.in', stream_get_contents($testcase->getInput()));
            $zip->addFromString($filename . '.ans', stream_get_contents($testcase->getOutput()));

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
     * @Route("/problems/{probId}", name="jury_problem", requirements={"probId": "\d+"})
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
     * @Route("/problems/{probId}/text", name="jury_problem_text", requirements={"probId": "\d+"})
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
     * @Route("/problems/{probId}/testcases", name="jury_problem_testcases", requirements={"probId": "\d+"})
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
                     'LENGTH(content.image) AS image_size')
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
                                throw new BadRequestHttpException(sprintf('image: %s', $error));
                            }
                            $thumb = Utils::getImageThumb($content, $thumbnailSize,
                                                          $this->DOMJudgeService->getDomjudgeTmpDir(), $error);
                            if ($thumb === false) {
                                $thumb = null;
                                throw new BadRequestHttpException(sprintf('image: %s', $error));
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
                        throw new BadRequestHttpException(sprintf('image: %s', $error));
                    }
                    $thumb = Utils::getImageThumb($content, $thumbnailSize,
                                                  $this->DOMJudgeService->getDomjudgeTmpDir(), $error);
                    if ($thumb === false) {
                        $thumb = null;
                        throw new BadRequestHttpException(sprintf('image: %s', $error));
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

                $this->addFlash('testcases', $message);
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
     *     "/problems/{probId}/testcases/{rank}/move/{direction}",
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
     *     "/problems/{probId}/testcases/{rank}/fetch/{type}",
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
                $content = stream_get_contents($testcase->getInput());
                break;
            case 'output':
                $content = stream_get_contents($testcase->getOutput());
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
     * @Route("/problems/{probId}/edit", name="jury_problem_edit", requirements={"probId": "\d+"})
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
            return $this->redirect($this->generateUrl('jury_problem',
                                                      ['probId' => $problem->getProbid(), 'edited' => true]));
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
            try {
                $zip        = $this->DOMJudgeService->openZipFile($archive->getRealPath());
                $clientName = $archive->getClientOriginalName();
                $this->importZippedProblem($zip, $clientName, $problem, $contest, $messages);
                $this->DOMJudgeService->auditlog('problem', $problem->getProbid(), 'upload zip', $clientName);
            } finally {
                $zip->close();
            }

            if (!empty($messages)) {
                $message = '<ul>' . implode('', array_map(function (string $message) {
                        return sprintf('<li>%s</li>', $message);
                    }, $messages)) . '</ul>';

                $this->addFlash('problemZip', $message);
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
     * @Route("/problems/add", name="jury_problem_add")
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

    /**
     * Import a zipped problem
     * @param ZipArchive   $zip
     * @param              $clientName
     * @param Problem|null $problem
     * @param Contest|null $contest
     * @param array        $messages
     * @return Problem
     * @throws \Exception
     */
    protected function importZippedProblem(
        ZipArchive $zip,
        $clientName,
        Problem $problem = null,
        Contest $contest = null,
        array &$messages = []
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

        if ($contestProblem !== null) {
            foreach ($contestProblemProperties as $key => $value) {
                $propertyAccessor->setValue($contestProblem, $key, $value);
            }
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

                if (isset($yamlData['validation']) && $yamlData['validation'] == 'custom') {
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
                            $tmpzipfiledir = exec("mktemp -d --tmpdir=" . $this->DOMJudgeService->getDomjudgeTmpDir(),
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
                            $executable = new Executable();
                            $executable
                                ->setExecid($outputValidatorName)
                                ->setMd5sum(md5($outputValidatorZip))
                                ->setZipfile($outputValidatorZip)
                                ->setDescription(sprintf('output validator for %s', $problem->getName()))
                                ->setType('compare');
                            $this->entityManager->persist($executable);

                            $problem->setCompareExecutable($executable);

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
                            $thumbnailSize = $this->DOMJudgeService->dbconfig_get('thumbnail_size', 128);
                            $imageThumb    = Utils::getImageThumb($imageFile, $thumbnailSize,
                                                                  $this->DOMJudgeService->getDomjudgeTmpDir(),
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
        } elseif (!$this->DOMJudgeService->getUser()->getTeam()) {
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

                $tmpDir = $this->DOMJudgeService->getDomjudgeTmpDir();

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
                            $results = SubmissionService::getExpectedResults($source);
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
                                              implode(', ', $results), $files[$k]);
                    }
                    if ($totalSize <= $this->DOMJudgeService->dbconfig_get('sourcesize_limit') * 1024) {
                        $contest        = $this->entityManager->getRepository(Contest::class)->find($contest->getCid());
                        $team           = $this->entityManager->getRepository(Team::class)->find($this->DOMJudgeService->getUser()->getTeamid());
                        $contestProblem = $this->entityManager->getRepository(ContestProblem::class)->find([
                                                                                                               'probid' => $problem->getProbid(),
                                                                                                               'cid' => $contest->getCid()
                                                                                                           ]);
                        $submission     = $this->submissionService->submitSolution($team, $contestProblem, $contest,
                                                                                   $languageToUse, $filesToSubmit, null,
                                                                                   '__auto__');
                        $submission     = $this->entityManager->getRepository(Submission::class)->find($submission->getSubmitid());
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
