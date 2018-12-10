<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use DOMJudgeBundle\Entity\AuditLog;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use DOMJudgeBundle\Utils\Utils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury")
 * @Security("has_role('ROLE_ADMIN')")
 */
class AuditLogController extends Controller
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
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * AuditLogController constructor.
     * @param EntityManagerInterface $entityManager
     * @param DOMJudgeService        $DOMJudgeService
     * @param EventLogService        $eventLogService
     */
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
     * @Route("/auditlog/", name="jury_auditlog")
     */
    public function indexAction(Request $request, Packages $assetPackage, KernelInterface $kernel)
    {
        $timeFormat = (string)$this->DOMJudgeService->dbconfig_get('time_format', '%H:%M');

        $page = $request->query->get('page', 1);
        $limit = 1000;

        $em = $this->entityManager;
        $query = $em->createQueryBuilder()
                    ->select('a')
                    ->from('DOMJudgeBundle:AuditLog', 'a')
                    ->orderBy('a.logid', 'DESC');

        $paginator = new Paginator($query);
        $paginator->getQuery()
                  ->setFirstResult($limit * ($page - 1))
                  ->setMaxResults($limit);

        $auditlog_table= [];
        foreach($paginator as $logline) {

            $data = [];
            $data['id']['value'] = $logline->getLogId();

            $time = $logline->getLogTime();
            $data['when']['value'] = Utils::printtime($time, $timeFormat);
            $data['when']['title'] = Utils::printtime($time, "%Y-%m-%d %H:%M%S (%Z)");
            $data['when']['sortvalue'] = $time;

            $data['who']['value'] = $logline->getUser();

            $datatype = $logline->getDatatype();
            $dataid = $logline->getDataId();
            $data['what']['value'] = $datatype . " " . $dataid . " " .
                            $logline->getAction() . " " .
                            $logline->getExtraInfo();
            $dataurl = $this->generateDatatypeUrl($datatype, $dataid);
            if ( $dataurl ) {
                $data['what']['link'] = $dataurl;
            }

            $cid = $logline->getCid();
            if ( $cid ) {
                    $data['where']['value'] = "c" . $cid;
                    $data['where']['sortvalue'] = $cid;
                    $data['where']['link'] = $this->generateUrl('legacy.jury_contest', ['id' => $cid]);
            } else {
                    $data['where']['value'] = '';
            }

            $auditlog_table[] = [ 'data' => $data, 'actions' => [] ];
        }
        $table_fields = [
            'id' => ['title' => 'ID', 'sort' => false],
            'when' => ['title' => 'time', 'sort' => false],
            'who' => ['title' => 'user', 'sort' => false],
            'where' => ['title' => 'contest', 'sort' => false],
            'what' => ['title' => 'action', 'sort' => false],
        ];

        $maxPages = ceil($paginator->count() / $limit);
        $thisPage = $page;

        return $this->render('@DOMJudge/jury/auditlog.html.twig', [
            'auditlog' => $auditlog_table,
            'table_fields' => $table_fields,
            'table_options' => ['ordering' => 'false', 'searching' => 'false'],
            'maxPages' => $maxPages,
            'thisPage' => $thisPage
        ]);
    }

    private function generateDatatypeUrl(string $type, $id)
    {
        switch($type) {
        case 'balloon': return $this->generateUrl('jury_balloons');
        case 'clarification': return $this->generateUrl('legacy.jury_clarification', ['id' => $id]);
        case 'configuration': return $this->generateUrl('jury_config');
        case 'contest': return $this->generateUrl('legacy.jury_contest', ['id' => $id]);
        case 'executable': return $this->generateUrl('jury_executable', ['execId' => $id]);
        case 'internal_error': return $this->generateUrl('jury_internal_error', ['errorId' => $id]);
        case 'judgehost': return $this->generateUrl('jury_judgehost', ['hostname' => $id]);
        case 'judgehosts': return $this->generateUrl('jury_judgehosts');
        case 'judgehost_restriction': return $this->generateUrl('jury_judgehost_restriction', ['restrictionId' => $id]);
        case 'judging': return $this->generateUrl('jury_submission_by_judging', ['jid' => $id]);
        case 'language': return $this->generateUrl('legacy.jury_language', ['id' => $id]);
        case 'problem': return $this->generateUrl('legacy.jury_problem', ['id' => $id]);
        case 'submission': return $this->generateUrl('jury_submission', ['submitId' => $id]);
        case 'team': return $this->generateUrl('legacy.jury_team', ['id' => $id]);
        case 'team_affiliation': return $this->generateUrl('legacy.jury_team_affiliation', ['id' => $id]);
        case 'team_category': return $this->generateUrl('legacy.jury_team_category', ['id' => $id]);
        case 'user': return $this->generateUrl('legacy.jury_user', ['id' => $id]);
        }
        return null;
    }
}
