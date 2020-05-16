<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Entity\AuditLog;
use App\Entity\Testcase;
use App\Entity\User;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury/auditlog")
 * @IsGranted("ROLE_ADMIN")
 */
class AuditLogController extends AbstractController
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
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * AuditLogController constructor.
     *
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param ConfigurationService   $config
     * @param EventLogService        $eventLogService
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService
    ) {
        $this->em              = $em;
        $this->dj              = $dj;
        $this->config          = $config;
        $this->eventLogService = $eventLogService;
    }

    /**
     * @Route("", name="jury_auditlog")
     */
    public function indexAction(Request $request, Packages $assetPackage, KernelInterface $kernel)
    {
        $timeFormat = (string)$this->config->get('time_format');

        $showAll = $request->query->get('showAll', false);
        $page = $request->query->get('page', 1);
        $limit = 1000;

        $em = $this->em;
        $query = $em->createQueryBuilder()
                    ->select('a')
                    ->from(AuditLog::class, 'a')
                    ->orderBy('a.logid', 'DESC');

        $paginator = new Paginator($query);
        if ($showAll) {
            $paginator->getQuery();
        } else {
            $paginator->getQuery()
                      ->setFirstResult($limit * ($page - 1))
                      ->setMaxResults($limit);
        }
        $auditlog_table= [];
        foreach($paginator as $logline) {

            $data = [];
            $data['id']['value'] = $logline->getLogId();

            $time = $logline->getLogTime();
            $data['when']['value'] = Utils::printtime($time, $timeFormat);
            $data['when']['title'] = Utils::printtime($time, "%Y-%m-%d %H:%M:%S (%Z)");
            $data['when']['sortvalue'] = $time;

            $data['who']['value'] = $logline->getUser();

            $datatype = $logline->getDatatype();
            $dataid = $logline->getDataId();
            $data['what']['value'] = $datatype . " " . $dataid . " " .
                            $logline->getAction() . " " .
                            $logline->getExtraInfo();
            if (!is_null($dataid)) {
                $dataurl = $this->generateDatatypeUrl($datatype, $dataid);
            } else {
                $dataurl = null;
            }
            if (isset($dataurl)) {
                $data['what']['link'] = $dataurl;
            }

            $cid = $logline->getCid();
            if ( $cid ) {
                    $data['where']['value'] = "c" . $cid;
                    $data['where']['sortvalue'] = $cid;
                    $data['where']['link'] = $this->generateUrl('jury_contest', ['contestId' => $cid]);
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

        return $this->render('jury/auditlog.html.twig', [
            'auditlog' => $auditlog_table,
            'table_fields' => $table_fields,
            'table_options' => ['ordering' => 'false', 'searching' => 'false'],
            'maxPages' => $maxPages,
            'thisPage' => $thisPage,
            'showAll' => $showAll,
        ]);
    }

    private function generateDatatypeUrl(string $type, $id)
    {
        switch ($type) {
            case 'balloon':
                return $this->generateUrl('jury_balloons');
            case 'clarification':
                return $this->generateUrl('jury_clarification', ['id' => $id]);
            case 'configuration':
                return $this->generateUrl('jury_config');
            case 'contest':
                return $this->generateUrl('jury_contest', ['contestId' => $id]);
            case 'executable':
                return $this->generateUrl('jury_executable', ['execId' => $id]);
            case 'internal_error':
                return $this->generateUrl('jury_internal_error', ['errorId' => $id]);
            case 'judgehost':
                return $this->generateUrl('jury_judgehost', ['hostname' => $id]);
            case 'judgehosts':
                return $this->generateUrl('jury_judgehosts');
            case 'judgehost_restriction':
                return $this->generateUrl('jury_judgehost_restriction', ['restrictionId' => $id]);
            case 'judging':
                return $this->generateUrl('jury_submission_by_judging', ['jid' => $id]);
            case 'external_judgement':
                return $this->generateUrl('jury_submission_by_external_judgement', ['externalJudgement' => $id]);
            case 'language':
                return $this->generateUrl('jury_language', ['langId' => $id]);
            case 'problem':
                return $this->generateUrl('jury_problem', ['probId' => $id]);
            case 'submission':
                return $this->generateUrl('jury_submission', ['submitId' => $id]);
            case 'team':
                return $this->generateUrl('jury_team', ['teamId' => $id]);
            case 'team_affiliation':
                return $this->generateUrl('jury_team_affiliation', ['affilId' => $id]);
            case 'team_category':
                return $this->generateUrl('jury_team_category', ['categoryId' => $id]);
            case 'user':
                // Pre 6.1, usernames were stored instead of numeric ids
                if (!is_numeric($id)) {
                    $user = $this->em->getRepository(User::class)->findOneBy(['username'=>$id]);
                    $id = $user->getUserId();
                }
                return $this->generateUrl('jury_user', ['userId' => $id]);
            case 'testcase':
                $testcase = $this->em->getRepository(Testcase::class)->find($id);
                if ($testcase) {
                    return $this->generateUrl('jury_problem_testcases', ['probId' => $testcase->getProbid()]);
                }
                break;
        }
        return null;
    }
}
