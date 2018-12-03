<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\ContestProblem;
use DOMJudgeBundle\Entity\Problem;
use DOMJudgeBundle\Entity\Submission;
use DOMJudgeBundle\Entity\SubmissionFileWithSourceCode;
use DOMJudgeBundle\Entity\Testcase;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use DOMJudgeBundle\Service\SubmissionService;
use DOMJudgeBundle\Utils\Utils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;


/**
 * @Route("/jury")
 * @Security("has_role('ROLE_JURY')")
 */
class ProblemController extends Controller
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

    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        EventLogService $eventLogService
    ) {
        $this->entityManager   = $entityManager;
        $this->DOMJudgeService = $DOMJudgeService;
        $this->eventLogService = $eventLogService;
    }

    /**
     * @Route("/problems/", name="jury_problems")
     */
    public function indexAction(Request $request, Packages $assetPackage)
    {
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
                    'link' => $this->generateUrl('legacy.jury_problem', [
                        'cmd' => 'viewtext',
                        'id' => $p->getProbid(),
                        'referrer' => 'problems'
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
                    'link' => $this->generateUrl('legacy.jury_problem', [
                        'cmd' => 'edit',
                        'id' => $p->getProbid(),
                        'referrer' => 'problems'
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
                'link' => $this->generateUrl('legacy.jury_problem', ['id' => $p->getProbid()]),
            ];
        }
        $data = [
            'problems' => $problems_table,
            'table_fields' => $table_fields,
            'num_actions' => $this->isGranted('ROLE_ADMIN') ? 4 : 1,
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
            'color' => $contestProblem->getColor(),
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
            throw new ServiceUnavailableHttpException('Could not create temporary file.');
        }

        $res = $zip->open($tempFilename, ZipArchive::OVERWRITE);
        if ($res !== true) {
            throw new ServiceUnavailableHttpException('Could not create temporary zip file.');
        }
        $zip->addFromString('domjudge-problem.ini', $iniString);
        $zip->addFromString('problem.yaml', $yamlString);

        if (!empty($problem->getProblemtext())) {
            $zip->addFromString('problem.' . $problem->getProblemtextType(),
                                stream_get_contents($problem->getProblemtext()));
        }

        /** @var Testcase[] $testcases */
        $testcases = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Testcase', 't')
            ->select('t')
            ->andWhere('t.problem = :problem')
            ->setParameter(':problem', $problem)
            ->orderBy('t.rank')
            ->getQuery()
            ->getResult();

        foreach ($testcases as $testcase) {
            $filename = sprintf('data/%s/%d', $testcase->getSample() ? 'sample' : 'secret', $testcase->getRank());
            $content  = $testcase->getTestcaseContent();
            $zip->addFromString($filename . '.in', stream_get_contents($content->getInput()));
            $zip->addFromString($filename . '.ans', stream_get_contents($content->getOutput()));

            if (!empty($testcase->getDescription(true))) {
                $description = $testcase->getDescription(true);
                if (strstr($description, "\n") === false) {
                    $description .= "\n";
                }
                $zip->addFromString($filename . '.desc', $description);
            }

            if (!empty($testcase->getImageType())) {
                $zip->addFromString($filename . '.' . $testcase->getImageType(), $content->getImage());
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
}
