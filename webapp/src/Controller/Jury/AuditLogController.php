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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route(path: '/jury/auditlog')]
class AuditLogController extends AbstractController
{
    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly EventLogService $eventLogService
    ) {}

    #[Route(path: '', name: 'jury_auditlog')]
    public function indexAction(
        #[MapQueryParameter]
        bool $showAll = false,
        #[MapQueryParameter]
        int $page = 1,
    ): Response {
        $timeFormat = (string)$this->config->get('time_format');

        $limit = 1000;

        $em = $this->em;
        $query = $em->createQueryBuilder()
                    ->select('a')
                    ->from(AuditLog::class, 'a')
                    ->orderBy('a.logid', 'DESC');

        $paginator = new Paginator($query);
        if (!$showAll) {
            $paginator->getQuery()
                      ->setFirstResult($limit * ($page - 1))
                      ->setMaxResults($limit);
        }
        $auditlog_table= [];
        foreach ($paginator as $logline) {
            $data = [];
            $data['id']['value'] = $logline->getLogId();

            $time = $logline->getLogTime();
            $data['when']['value'] = Utils::printtime($time, $timeFormat);
            $data['when']['title'] = Utils::printtime($time, "Y-m-d H:i:s (T)");
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
            if ($cid) {
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
            'table_options' => ['ordering' => 'false', 'searching' => 'false', 'full_clickable' => false],
            'maxPages' => $maxPages,
            'thisPage' => $thisPage,
            'showAll' => $showAll,
        ]);
    }

    private function generateDatatypeUrl(string $type, int|string|null $id): ?string
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
                if ($id) {
                    return $this->generateUrl('jury_judgehost', ['judgehostid' => $id]);
                }
                return $this->generateUrl('jury_judgehosts');
            case 'judgehosts':
                return $this->generateUrl('jury_judgehosts');
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
                // Pre 6.1, usernames were stored instead of numeric IDs.
                if (!is_numeric($id)) {
                    $user = $this->em->getRepository(User::class)->findOneBy(['username'=>$id]);
                    $id = $user->getUserId();
                }
                return $this->generateUrl('jury_user', ['userId' => $id]);
            case 'testcase':
                $testcase = $this->em->getRepository(Testcase::class)->find($id);
                if ($testcase && $testcase->getProblem()) {
                    return $this->generateUrl('jury_problem_testcases', ['probId' => $testcase->getProblem()->getProbid()]);
                }
                break;
        }
        return null;
    }
}
