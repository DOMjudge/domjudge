<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Team;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Controller\BaseController;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\ScoreboardService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ScoreboardController
 *
 * @Route("/team")
 * @Security("is_granted('ROLE_TEAM')")
 * @Security("user.getTeam() !== null", message="You do not have a team associated with your account.")
 *
 * @package DOMJudgeBundle\Controller\Team
 */
class ScoreboardController extends BaseController
{
    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var ScoreboardService
     */
    protected $scoreboardService;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * ScoreboardController constructor.
     * @param DOMJudgeService        $dj
     * @param ScoreboardService      $scoreboardService
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        DOMJudgeService $dj,
        ScoreboardService $scoreboardService,
        EntityManagerInterface $entityManager
    ) {
        $this->dj                = $dj;
        $this->scoreboardService = $scoreboardService;
        $this->entityManager     = $entityManager;
    }

    /**
     * @Route("/scoreboard", name="team_scoreboard")
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function scoreboardAction(Request $request)
    {
        $user       = $this->dj->getUser();
        $response   = new Response();
        $contest    = $this->dj->getCurrentContest($user->getTeamid());
        $refreshUrl = $this->generateUrl('team_scoreboard');
        $data       = $this->scoreboardService->getScoreboardTwigData($request, $response, $refreshUrl, false, false,
                                                                      false, $contest);

        if ($request->isXmlHttpRequest()) {
            return $this->render('@DOMJudge/partials/scoreboard.html.twig', $data, $response);
        }
        return $this->render('@DOMJudge/team/scoreboard.html.twig', $data, $response);
    }

    /**
     * @Route("/team/{teamId}", name="team_team")
     * @param Request $request
     * @param int     $teamId
     * @return Response
     * @throws \Exception
     */
    public function teamAction(Request $request, int $teamId)
    {
        $team             = $this->entityManager->getRepository(Team::class)->find($teamId);
        $showFlags        = (bool)$this->dj->dbconfig_get('show_flags', true);
        $showAffiliations = (bool)$this->dj->dbconfig_get('show_affiliations', true);
        $data             = [
            'team' => $team,
            'showFlags' => $showFlags,
            'showAffiliations' => $showAffiliations,
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('@DOMJudge/team/team_modal.html.twig', $data);
        } else {
            return $this->render('@DOMJudge/team/team.html.twig', $data);
        }
    }
}
