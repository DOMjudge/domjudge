<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\Problem;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use DOMJudgeBundle\Utils\Utils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;


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
                    'link' => $this->generateUrl('legacy.jury_export_problem', [
                        'id' => $p->getProbid(),
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
                        'value' => Utils::printsize(1024*$orig_value),
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
}
