<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Rejudging;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Utils\Utils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
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
            'rejudgingid' => ['title' => 'ID', 'sort' => true],
            'reason' => ['title' => 'reason', 'sort' => true],
            'startuser' => ['title' => 'startuser', 'sort' => true],
            'finishuser' => ['title' => 'finishuser', 'sort' => true],
            'starttime' => ['title' => 'starttime', 'sort' => true],
            'endtime' => ['title' => 'finishtime', 'sort' => true],
            'status' => ['title' => 'status', 'sort' => true],
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
                'link' => $this->generateUrl('legacy.jury_rejudging', ['id' => $rejudging->getRejudgingid()]),
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
}
