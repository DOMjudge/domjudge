<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Judging;
use DOMJudgeBundle\Entity\Rejudging;
use DOMJudgeBundle\Entity\Submission;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\SubmissionService;
use DOMJudgeBundle\Utils\Utils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury")
 * @Security("has_role('ROLE_JURY')")
 */
class RejudgingController extends Controller
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var DOMJudgeService
     */
    protected $DOMJudgeService;

    public function __construct(EntityManagerInterface $entityManager, DOMJudgeService $DOMJudgeService)
    {
        $this->entityManager   = $entityManager;
        $this->DOMJudgeService = $DOMJudgeService;
    }

    /**
     * @Route("/rejudgings/", name="jury_rejudgings")
     */
    public function indexAction(Request $request)
    {
        /** @var Rejudging[] $rejudgings */
        $rejudgings = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from('DOMJudgeBundle:Rejudging', 'r')
            ->leftJoin('r.start_user', 's')
            ->leftJoin('r.finish_user', 'f')
            ->orderBy('r.valid', 'DESC')
            ->addOrderBy('r.endtime')
            ->addOrderBy('r.rejudgingid')
            ->getQuery()->getResult();

        $table_fields = [
            'rejudgingid' => ['title' => 'ID'],
            'reason' => ['title' => 'reason'],
            'startuser' => ['title' => 'startuser'],
            'finishuser' => ['title' => 'finishuser'],
            'starttime' => ['title' => 'starttime'],
            'endtime' => ['title' => 'finishtime'],
            'status' => ['title' => 'status'],
        ];

        $timeFormat       = (string)$this->DOMJudgeService->dbconfig_get('time_format', '%H:%M');
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $rejudgings_table = [];
        foreach ($rejudgings as $rejudging) {
            $rejudgingdata = [];
            // Get whatever fields we can from the problem object itself
            foreach ($table_fields as $k => $v) {
                if ($propertyAccessor->isReadable($rejudging, $k)) {
                    $rejudgingdata[$k] = ['value' => $propertyAccessor->getValue($rejudging, $k)];
                }
            }

            if ($rejudging->getStartUser()) {
                $rejudgingdata['startuser']['value'] = $rejudging->getStartUser()->getName();
            }
            if ($rejudging->getFinishUser()) {
                $rejudgingdata['finishuser']['value'] = $rejudging->getFinishUser()->getName();
            }

            $todo = $this->entityManager->createQueryBuilder()
                ->from('DOMJudgeBundle:Submission', 's')
                ->select('COUNT(s)')
                ->andWhere('s.rejudging = :rejudging')
                ->setParameter(':rejudging', $rejudging)
                ->getQuery()
                ->getSingleScalarResult();

            $done = $this->entityManager->createQueryBuilder()
                ->from('DOMJudgeBundle:Judging', 'j')
                ->select('COUNT(j)')
                ->andWhere('j.rejudging = :rejudging')
                ->andWhere('j.endtime IS NOT NULL')
                ->setParameter(':rejudging', $rejudging)
                ->getQuery()
                ->getSingleScalarResult();

            $todo -= $done;

            if ($rejudging->getEndtime() !== null) {
                $status = $rejudging->getValid() ? 'applied' : 'canceled';
            } elseif ($todo > 0) {
                $perc   = (int)(100 * ((double)$done / (double)($done + $todo)));
                $status = sprintf("%d%% done", $perc);
            } else {
                $status = 'ready';
            }

            $rejudgingdata['starttime']['value'] = Utils::printtime($rejudging->getStarttime(), $timeFormat);
            $rejudgingdata['endtime']['value']   = Utils::printtime($rejudging->getEndtime(), $timeFormat);
            $rejudgingdata['status']['value']    = $status;

            if ($rejudging->getEndtime() !== null) {
                $class = 'disabled';
            } else {
                $class = $todo > 0 ? '' : 'unseen';
            }

            // Save this to our list of rows
            $rejudgings_table[] = [
                'data' => $rejudgingdata,
                'actions' => [],
                'link' => $this->generateUrl('jury_rejudging', ['rejudgingId' => $rejudging->getRejudgingid()]),
                'cssclass' => $class,
            ];
        }

        $twigData = [
            'rejudgings' => $rejudgings_table,
            'table_fields' => $table_fields,
            'refresh' => [
                'after' => 15,
                'url' => $this->generateUrl('jury_rejudgings'),
            ],
        ];

        return $this->render('@DOMJudge/jury/rejudgings.html.twig', $twigData);
    }

    /**
     * @Route("/rejudgings/{rejudgingId}", name="jury_rejudging")
     * @param Request           $request
     * @param KernelInterface   $kernel
     * @param SubmissionService $submissionService
     * @param int               $rejudgingId
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function viewAction(
        Request $request,
        KernelInterface $kernel,
        SubmissionService $submissionService,
        int $rejudgingId
    ) {
        /** @var Rejudging $rejudging */
        $rejudging = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Rejudging', 'r')
            ->leftJoin('r.start_user', 's')
            ->leftJoin('r.finish_user', 'f')
            ->select('r', 's', 'f')
            ->andWhere('r.rejudgingid = :rejudgingid')
            ->setParameter(':rejudgingid', $rejudgingId)
            ->getQuery()
            ->getOneOrNullResult();

        $todo = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Submission', 's')
            ->select('COUNT(s)')
            ->andWhere('s.rejudging = :rejudging')
            ->setParameter(':rejudging', $rejudging)
            ->getQuery()
            ->getSingleScalarResult();

        $done = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Judging', 'j')
            ->select('COUNT(j)')
            ->andWhere('j.rejudging = :rejudging')
            ->andWhere('j.endtime IS NOT NULL')
            ->setParameter(':rejudging', $rejudging)
            ->getQuery()
            ->getSingleScalarResult();

        $todo -= $done;

        // TODO: use domserver-static.php for this path
        global $VERDICTS;
        $dir          = realpath($kernel->getRootDir() . '/../../etc/');
        $commonConfig = $dir . '/common-config.php';
        require_once $commonConfig;
        /** @var string[] $VERDICTS */

        $used         = [];
        $verdictTable = [];
        // pre-fill $verdictTable to get a consistent ordering
        foreach ($VERDICTS as $verdict => $abbrev) {
            foreach ($VERDICTS as $verdict2 => $abbrev2) {
                $verdictTable[$verdict][$verdict2] = array();
            }
        }

        $expr = $this->entityManager->getExpressionBuilder();

        /** @var Judging[] $originalVerdicts */
        $originalVerdicts = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Judging', 'j', 'j.submitid')
            ->select('j')
            ->where(
                $expr->in('j.judgingid',
                          $this->entityManager->createQueryBuilder()
                              ->from('DOMJudgeBundle:Judging', 'j2')
                              ->select('j2.prevjudgingid')
                              ->andWhere('j2.rejudging = :rejudging')
                              ->andWhere('j2.endtime IS NOT NULL')
                              ->getDQL()
                )
            )
            ->setParameter(':rejudging', $rejudging)
            ->getQuery()
            ->getResult();

        /** @var Judging[] $newVerdicts */
        $newVerdicts = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Judging', 'j', 'j.submitid')
            ->select('j')
            ->andWhere('j.rejudging = :rejudging')
            ->andWhere('j.endtime IS NOT NULL')
            ->setParameter(':rejudging', $rejudging)
            ->getQuery()
            ->getResult();

        // Helper function to add verdicts
        $addVerdict = function ($unknownVerdict) use ($VERDICTS, &$verdictTable) {
            // add column to existing rows
            foreach ($VERDICTS as $verdict => $abbreviation) {
                $verdictTable[$verdict][$unknownVerdict] = [];
            }
            // add verdict to known verdicts
            $verdicts[$unknownVerdict] = $unknownVerdict;
            // add row
            $verdictTable[$unknownVerdict] = [];
            foreach ($VERDICTS as $verdict => $abbreviation) {
                $verdictTable[$unknownVerdict][$verdict] = [];
            }
        };

        // Build up the verdict matrix
        foreach ($newVerdicts as $submitid => $newVerdict) {
            $originalVerdict = $originalVerdicts[$submitid];

            // add verdicts to data structures if they are unknown up to now
            foreach ([$newVerdict, $originalVerdict] as $verdict) {
                if (!array_key_exists($verdict->getResult(), $VERDICTS)) {
                    $addVerdict($verdict->getResult());
                }
            }

            // mark them as used, so we can filter out unused cols/rows later
            $used[$originalVerdict->getResult()] = true;
            $used[$newVerdict->getResult()]      = true;

            // append submitid to list of orig->new verdicts
            $verdictTable[$originalVerdict->getResult()][$newVerdict->getResult()][] = $submitid;
        }

        $viewTypes = [0 => 'newest', 1 => 'unverified', 2 => 'unjudged', 3 => 'diff', 4 => 'all'];
        $view      = 4;
        if ($request->query->has('view')) {
            $index = array_search($request->query->get('view'), $viewTypes);
            if ($index !== false) {
                $view = $index;
            }
        }

        $restrictions = ['rejudgingid' => $rejudgingId];
        if ($viewTypes[$view] == 'unverified') {
            $restrictions['verified'] = 0;
        }
        if ($viewTypes[$view] == 'unjudged') {
            $restrictions['judged'] = 0;
        }
        if ($viewTypes[$view] == 'diff') {
            $restrictions['rejudgingdiff'] = 1;
        }
        if ($request->query->get('oldverdict', 'all') !== 'all') {
            $restrictions['old_result'] = $request->query->get('oldverdict');
        }
        if ($request->query->get('newverdict', 'all') !== 'all') {
            $restrictions['result'] = $request->query->get('newverdict');
        }

        /** @var Submission[] $submissions */
        list($submissions, $submissionCounts) = $submissionService->getSubmissionList(
            $this->DOMJudgeService->getCurrentContests(),
            $restrictions
        );

        return $this->render('@DOMJudge/jury/rejudging.html.twig', [
            'rejudging' => $rejudging,
            'todo' => $todo,
            'verdicts' => $VERDICTS,
            'used' => $used,
            'verdictTable' => $verdictTable,
            'viewTypes' => $viewTypes,
            'view' => $view,
            'submissions' => $submissions,
            'submissionCounts' => $submissionCounts,
            'oldverdict' => $request->query->get('oldverdict', 'all'),
            'newverdict' => $request->query->get('newverdict', 'all'),
            'refresh' => [
                'after' => 15,
                'url' => $request->getRequestUri(),
            ],
        ]);
    }

    /**
     * @Route(
     *     "/rejudgings/{rejudgingId}/{action}",
     *     methods={"POST"},
     *     name="jury_rejudging_cancel_or_apply",
     *     requirements={"action": "cancel|apply"}
     * )
     * @param int    $rejudgingId
     * @param string $action
     * @return void
     */
    public function cancelOrApplyAction(int $rejudgingId, string $action)
    {

    }
}
