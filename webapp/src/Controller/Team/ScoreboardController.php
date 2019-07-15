<?php declare(strict_types=1);

namespace App\Controller\Team;

use App\Controller\BaseController;
use App\Entity\Team;
use App\Service\DOMJudgeService;
use App\Service\ScoreboardService;
use Doctrine\ORM\EntityManagerInterface;
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
 * @package App\Controller\Team
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
    protected $em;

    /**
     * ScoreboardController constructor.
     * @param DOMJudgeService        $dj
     * @param ScoreboardService      $scoreboardService
     * @param EntityManagerInterface $em
     */
    public function __construct(
        DOMJudgeService $dj,
        ScoreboardService $scoreboardService,
        EntityManagerInterface $em
    ) {
        $this->dj                = $dj;
        $this->scoreboardService = $scoreboardService;
        $this->em                = $em;
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
        $data['myTeamId'] = $user->getTeamid();

        if ($request->isXmlHttpRequest()) {
            return $this->render('partials/scoreboard.html.twig', $data, $response);
        }
        return $this->render('team/scoreboard.html.twig', $data, $response);
    }

    /**
     * @Route("/team/{teamId}", name="team_team", requirements={"teamId": "\d+"})
     * @param Request $request
     * @param int     $teamId
     * @return Response
     * @throws \Exception
     */
    public function teamAction(Request $request, int $teamId)
    {
        $team             = $this->em->getRepository(Team::class)->find($teamId);
        $showFlags        = (bool)$this->dj->dbconfig_get('show_flags', true);
        $showAffiliations = (bool)$this->dj->dbconfig_get('show_affiliations', true);
        $data             = [
            'team' => $team,
            'showFlags' => $showFlags,
            'showAffiliations' => $showAffiliations,
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('team/team_modal.html.twig', $data);
        } else {
            return $this->render('team/team.html.twig', $data);
        }
    }
}
