<?php declare(strict_types=1);

namespace App\Controller\Team;

use App\Controller\BaseController;
use App\DataTransferObject\SubmissionRestriction;
use App\Entity\Clarification;
use App\Form\Type\PrintType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ScoreboardService;
use App\Service\SubmissionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TEAM')]
#[IsGranted(
    new Expression('user.getTeam() !== null'),
    message: 'You do not have a team associated with your account.'
)]
#[Route(path: '/team')]
class MiscController extends BaseController
{
    public function __construct(
        DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        EntityManagerInterface $em,
        protected readonly ScoreboardService $scoreboardService,
        protected readonly SubmissionService $submissionService,
        protected readonly EventLogService $eventLogService,
        KernelInterface $kernel,
    ) {
        parent::__construct($em, $eventLogService, $dj, $kernel);
    }

    /**
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    #[Route(path: '', name: 'team_index')]
    public function homeAction(Request $request): Response
    {
        $user    = $this->dj->getUser();
        $team    = $user->getTeam();
        $teamId  = $team->getTeamid();
        $contest = $this->dj->getCurrentContest($teamId);

        $data = [
            'team' => $team,
            'contest' => $contest,
            'refresh' => [
                'after' => 30,
                'url' => $this->generateUrl('team_index'),
                'ajax' => true,
            ],
            'maxWidth' => $this->config->get('team_column_width'),
        ];
        if ($contest) {
            $scoreboard = $this->scoreboardService
                ->getTeamScoreboard($contest, $team->getExternalid(), false);
            $data = array_merge(
                $data,
                $this->scoreboardService->getScoreboardTwigData(
                    $request, null, '', true, false, false,
                    $contest, $scoreboard
                )
            );
            $data['limitToTeams'] = [$team];
            $data['verificationRequired'] = $this->config->get('verification_required');
            // We need to clear the entity manager, because loading the team scoreboard seems to break getting submission
            // contestproblems for the contest we get the scoreboard for.
            $this->em->clear();
            $data['submissions'] = $this->submissionService->getSubmissionList(
                [$contest->getCid() => $contest],
                new SubmissionRestriction(teamId: $teamId),
                paginated: false
            )[0];

            $qb = $this->em->createQueryBuilder()
                ->from(Clarification::class, 'c')
                ->leftJoin('c.problem', 'p')
                ->leftJoin('c.sender', 's')
                ->leftJoin('c.recipient', 'r')
                ->select('c', 'p')
                ->andWhere('c.contest = :contest')
                ->andWhere('c.sender IS NULL')
                ->andWhere('c.recipient = :teamId OR c.recipient IS NULL')
                ->andWhere('c.submittime <= :time')
                ->setParameter('contest', $contest)
                ->setParameter('teamId', $teamId)
                ->setparameter('time', time())
                ->addOrderBy('c.submittime', 'DESC')
                ->addOrderBy('c.clarid', 'DESC');
            if ($contest->getStartTimeObject()?->getTimestamp() > time()) {
                $qb->andWhere('c.problem IS NULL');
            }

            /** @var Clarification[] $clarifications */
            $clarifications = $qb->getQuery()->getResult();

            /** @var Clarification[] $clarificationRequests */
            $clarificationRequests = $this->em->createQueryBuilder()
                ->from(Clarification::class, 'c')
                ->leftJoin('c.problem', 'p')
                ->leftJoin('c.sender', 's')
                ->leftJoin('c.recipient', 'r')
                ->select('c', 'p')
                ->andWhere('c.contest = :contest')
                ->andWhere('c.sender = :teamId')
                ->setParameter('contest', $contest)
                ->setParameter('teamId', $teamId)
                ->addOrderBy('c.submittime', 'DESC')
                ->addOrderBy('c.clarid', 'DESC')
                ->getQuery()
                ->getResult();

            $data['clarifications']        = $clarifications;
            $data['clarificationRequests'] = $clarificationRequests;
            $data['categories']            = $this->config->get('clar_categories');
            $data['allowDownload']         = (bool)$this->config->get('allow_team_submission_download');
            $data['showTooLateResult']     = $this->config->get('show_too_late_result');
        }

        if ($request->isXmlHttpRequest()) {
            $data['ajax'] = true;
            return $this->render('team/partials/index_content.html.twig', $data);
        }

        return $this->render('team/index.html.twig', $data);
    }

    #[Route(path: '/updates', name: 'team_ajax_updates', methods: ['GET'])]
    public function updatesAction(): JsonResponse
    {
        return $this->json(['unread_clarifications' => $this->dj->getUnreadClarifications()]);
    }

    #[Route(path: '/change-contest/{contestId}', name: 'team_change_contest')]
    public function changeContestAction(Request $request, RouterInterface $router, string $contestId): Response
    {
        if ($this->isLocalReferer($router, $request)) {
            $response = new RedirectResponse($request->headers->get('referer'));
        } else {
            $response = $this->redirectToRoute('team_index');
        }
        return $this->dj->setCookie('domjudge_cid', $contestId, 0, null, '', false, false,
                                                 $response);
    }

    #[Route(path: '/print', name: 'team_print')]
    public function printAction(Request $request): Response
    {
        if (!$this->config->get('print_command')) {
            throw new AccessDeniedHttpException("Printing disabled in config");
        }

        $form = $this->createForm(PrintType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            /** @var UploadedFile $file */
            $file             = $data['code'];
            $realfile         = $file->getRealPath();
            $originalfilename = $file->getClientOriginalName();

            $langid = $data['langid'];
            $ret = $this->dj->printUserFile(
                $realfile,
                $originalfilename,
                $langid,
                true
            );

            return $this->render('team/print_result.html.twig', [
                'success' => $ret[0],
                'output' => $ret[1],
            ]);
        }

        return $this->render('team/print.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route(path: '/docs', name: 'team_docs')]
    public function docsAction(): Response
    {
        return $this->render('team/docs.html.twig');
    }

    #[Route(path: '/problemset', name: 'team_contest_problemset')]
    public function contestProblemsetAction(): StreamedResponse
    {
        $user    = $this->dj->getUser();
        $contest = $this->dj->getCurrentContest($user->getTeam()->getTeamid());
        if (!$contest->getFreezeData()->started()) {
            throw new NotFoundHttpException('Contest text not found or not available');
        }
        return $contest->getContestProblemsetStreamedResponse();
    }
}
