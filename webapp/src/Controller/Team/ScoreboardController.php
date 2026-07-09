<?php declare(strict_types=1);

namespace App\Controller\Team;

use App\Controller\BaseController;
use App\Controller\ScoreboardSubmissionsTrait;
use App\Entity\Team;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ScoreboardService;
use App\Service\SubmissionService;
use App\Twig\TwigExtension;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TEAM')]
#[IsGranted(
    new Expression('user.getTeam() !== null'),
    message: 'You do not have a team associated with your account.'
)]
#[Route(path: '/team')]
class ScoreboardController extends BaseController
{
    use ScoreboardSubmissionsTrait;

    public function __construct(
        DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly ScoreboardService $scoreboardService,
        protected readonly SubmissionService $submissionService,
        protected readonly TwigExtension $twigExtension,
        EntityManagerInterface $em,
        protected readonly EventLogService $eventLogService,
        KernelInterface $kernel,
    ) {
        parent::__construct($em, $eventLogService, $dj, $kernel);
    }

    #[Route(path: '/scoreboard', name: 'team_scoreboard')]
    public function scoreboardAction(Request $request): Response
    {
        if (!$this->config->get('enable_ranking')) {
            throw new BadRequestHttpException('Scoreboard is not available.');
        }

        $user       = $this->dj->getUser();
        $response   = new Response();
        $contest    = $this->dj->getCurrentContest($user->getTeam()->getTeamid());
        $refreshUrl = $this->generateUrl('team_scoreboard');
        $data       = $this->scoreboardService->getScoreboardTwigData(
            $request, $response, $refreshUrl, false, false, false, $contest
        );
        $data['myTeamId'] = $user->getTeam()->getTeamid();

        if ($request->isXmlHttpRequest()) {
            $data['current_contest'] = $contest;
            return $this->render('partials/scoreboard.html.twig', $data, $response);
        }
        return $this->render('team/scoreboard.html.twig', $data, $response);
    }

    #[Route(path: '/scoreboard/submissions/team/{teamId}/problem/{problemId}', name: 'team_submissions')]
    public function submissionsAction(string $teamId, string $problemId): Response
    {
        $user    = $this->dj->getUser();
        $contest = $this->dj->getCurrentContest($user->getTeam()->getTeamid());

        if (!$contest) {
            throw $this->createNotFoundException('No active contest found');
        }

        return $this->getSubmissionsPageResponse($contest, $teamId, $problemId, 'team_submissions_data_cell', 'team/base.html.twig');
    }

    #[Route(path: '/scoreboard/submissions-data/team/{teamId}/problem/{problemId}.json', name: 'team_submissions_data_cell')]
    public function submissionsDataAction(string $teamId, string $problemId): JsonResponse
    {
        $user    = $this->dj->getUser();
        $contest = $this->dj->getCurrentContest($user->getTeam()->getTeamid());

        if (!$contest) {
            throw $this->createNotFoundException('No active contest found');
        }

        return $this->getSubmissionsDataResponse($contest, $teamId, $problemId);
    }

    #[Route(path: '/team/{teamId}', name: 'team_team')]
    public function teamAction(Request $request, string $teamId): Response
    {
        if (!$this->config->get('enable_ranking')) {
            throw new BadRequestHttpException('Scoreboard is not available.');
        }

        /** @var Team|null $team */
        $team = $this->em->createQueryBuilder()
                         ->from(Team::class, 't')
                         ->innerJoin('t.categories', 'tc')
                         ->select('t, tc')
                         ->andWhere('tc.visible = 1')
                         ->andWhere('t.externalid = :teamId')
                         ->setParameter('teamId', $teamId)
                         ->getQuery()
                         ->getOneOrNullResult();
        if ($team?->getHidden() && $teamId !== $this->dj->getUser()->getTeam()->getExternalid()) {
            $team = null;
        }
        $showFlags        = (bool)$this->config->get('show_flags');
        $showAffiliations = (bool)$this->config->get('show_affiliations');
        $data             = [
            'team' => $team,
            'showFlags' => $showFlags,
            'showAffiliations' => $showAffiliations,
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('team/team_modal.html.twig', $data);
        }

        return $this->render('team/team.html.twig', $data);
    }
}
