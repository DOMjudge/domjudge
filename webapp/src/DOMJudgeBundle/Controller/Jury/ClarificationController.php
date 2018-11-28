<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use DOMJudgeBundle\Entity\Clarification;
use DOMJudgeBundle\Service\DOMJudgeService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury")
 * @Security("has_role('ROLE_JURY')")
 */
class ClarificationController extends Controller
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var DOMJudgeService
     */
    protected $DOMJudgeService;

    /**
     * ClarificationController constructor.
     * @param EntityManagerInterface $entityManager
     * @param DOMJudgeService        $DOMJudgeService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService
    ) {
        $this->entityManager   = $entityManager;
        $this->DOMJudgeService = $DOMJudgeService;
    }

    /**
     * @Route("/clarifications/", name="jury_clarifications")
     * @throws \Exception
     */
    public function indexAction(Request $request)
    {
        $contestIds = array_keys($this->DOMJudgeService->getCurrentContests());
        // cid -1 will never happen, but otherwise the array is empty and that is not supported
        if (empty($contestIds)) {
            $contestIds = [-1];
        }

        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Clarification', 'clar')
            ->leftJoin('clar.problem', 'p')
            ->leftJoin('p.contest_problems', 'cp', Join::WITH, 'cp.contest = clar.contest')
            ->select('clar', 'p', 'cp')
            ->andWhere('clar.cid in (:contestIds)')
            ->setParameter(':contestIds', $contestIds)
            ->orderBy('clar.submittime', 'DESC')
            ->addOrderBy('clar.clarid', 'DESC');

        /**
         * @var Clarification[] $newClarifications
         * @var Clarification[] $oldClarifications
         * @var Clarification[] $generalClarifications
         */
        $newClarifications = $oldClarifications = $generalClarifications = [];
        $wheres            = [
            'new' => 'clar.sender IS NOT NULL AND clar.answered = 0',
            'old' => 'clar.sender IS NOT NULL AND clar.answered != 0',
            'general' => 'clar.sender IS NULL AND (clar.respid IS NULL OR clar.recipient IS NULL)',
        ];
        foreach ($wheres as $type => $where) {
            $clarifications = (clone $queryBuilder)
                ->andWhere($where)
                ->getQuery()
                ->getResult();

            switch ($type) {
                case 'new':
                    $newClarifications = $clarifications;
                    break;
                case 'old':
                    $oldClarifications = $clarifications;
                    break;
                case 'general':
                    $generalClarifications = $clarifications;
                    break;
            }
        }

        $queues     = $this->DOMJudgeService->dbconfig_get('clar_queues');

        return $this->render('@DOMJudge/jury/clarifications.html.twig', [
            'newClarifications' => $newClarifications,
            'oldClarifications' => $oldClarifications,
            'generalClarifications' => $generalClarifications,
            'queues' => $queues,
        ]);
    }

    /**
     * @Route("/clarification", name="jury_clarification_new")
     * @throws \Exception
     */
    public function sendClarificationAction(Request $request)
    {
        // TODO: use proper Symfony form for this
        $em = $this->getDoctrine()->getManager();
        $teams = $em->getRepository('DOMJudgeBundle:Team')->findAll();
        foreach ($teams as $team) {
            $teamlist[$team->getTeamid()] = sprintf("%s (t%s)", $team->getName(), $team->getTeamid());
        }
        asort($teamlist, SORT_STRING | SORT_FLAG_CASE);
        $teamlist = ['domjudge-must-select' => '(select...)', '' => 'ALL'] + $teamlist;

        $data= ['teams' => $teamlist ];

        $subject_options = [];

        $categories = $this->DOMJudgeService->dbconfig_get('clar_categories');
        $contests = $this->DOMJudgeService->getCurrentContests();
        foreach($contests as $cid => $cdata) {
            $cshort = $cdata->getShortName();
            foreach($categories as $name => $desc) {
                $subject_options[$cshort]["$cid-$name"] = "$cshort - $desc";
            }

            $queryBuilder = $this->entityManager->createQueryBuilder()
                ->from('DOMJudgeBundle:ContestProblem', 'cp', 'cp.probid')
                ->select('cp, p')
                ->innerJoin('cp.problem', 'p')
                ->where('cp.cid = :cid')
                ->setParameter(':cid', $cdata->getCid())
                ->orderBy('cp.shortname');

            $contestproblems = $queryBuilder->getQuery()->getResult();
            foreach($contestproblems as $cp) {
                $subject_options[$cshort]["$cid-" . $cp->getProbid() ] = $cshort . ' - ' .$cp->getShortname() . ': ' . $cp->getProblem()->getName();
            }
        }

        $data['subjects'] = $subject_options;

        if ( $toteam = $request->query->get('teamto') ) {
            $data['toteam'] = $toteam;
        }

        return $this->render('@DOMJudge/jury/clarification.html.twig', $data);
    }
}
