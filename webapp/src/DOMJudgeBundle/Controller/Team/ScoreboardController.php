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
    protected $DOMJudgeService;

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
     * @param DOMJudgeService        $DOMJudgeService
     * @param ScoreboardService      $scoreboardService
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        DOMJudgeService $DOMJudgeService,
        ScoreboardService $scoreboardService,
        EntityManagerInterface $entityManager
    ) {
        $this->DOMJudgeService   = $DOMJudgeService;
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
        $user       = $this->DOMJudgeService->getUser();
        $response   = new Response();
        $contest    = $this->DOMJudgeService->getCurrentContest($user->getTeamid());
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
     * @param int $teamId
     * @return Response
     * @throws \Exception
     */
    public function teamAction(int $teamId)
    {
        $team             = $this->entityManager->getRepository(Team::class)->find($teamId);
        $showFlags        = (bool)$this->DOMJudgeService->dbconfig_get('show_flags', true);
        $showAffiliations = (bool)$this->DOMJudgeService->dbconfig_get('show_affiliations', true);

        return $this->render('@DOMJudge/team/team.html.twig', [
            'team' => $team,
            'showFlags' => $showFlags,
            'showAffiliations' => $showAffiliations,
        ]);
    }
}
