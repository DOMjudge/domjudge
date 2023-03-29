<?php declare(strict_types=1);

namespace App\Controller\Team;

use App\Controller\BaseController;
use App\Entity\Team;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\ScoreboardService;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ScoreboardController
 *
 *
 * @package App\Controller\Team
 */
#[IsGranted('ROLE_TEAM')]
#[Security('user.getTeam() !== null', message: 'You do not have a team associated with your account.')]
/**
 * Class ScoreboardController
 *
 *
 * @package App\Controller\Team
 */
#[Route(path: '/team')]
class ScoreboardController extends BaseController
{
    public function __construct(
        protected readonly DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly ScoreboardService $scoreboardService,
        protected readonly EntityManagerInterface $em
    ) {}

    #[Route(path: '/scoreboard', name: 'team_scoreboard')]
    public function scoreboardAction(Request $request): Response
    {
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

    #[Route(path: '/team/{teamId<\d+>}', name: 'team_team')]
    public function teamAction(Request $request, int $teamId): Response
    {
        /** @var Team|null $team */
        $team             = $this->em->getRepository(Team::class)->find($teamId);
        if ($team && $team->getCategory() && !$team->getCategory()->getVisible() && $teamId !== $this->dj->getUser()->getTeamId()) {
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
