<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\ScoreboardService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury/scoreboard")
 * @Security("has_role('ROLE_JURY')")
 */
class ScoreboardController extends Controller
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
     * ScoreboardController constructor.
     * @param DOMJudgeService   $DOMJudgeService
     * @param ScoreboardService $scoreboardService
     */
    public function __construct(
        DOMJudgeService $DOMJudgeService,
        ScoreboardService $scoreboardService
    ) {
        $this->DOMJudgeService   = $DOMJudgeService;
        $this->scoreboardService = $scoreboardService;
    }

    /**
     * @Route("", name="jury_scoreboard")
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function scoreboardAction(Request $request)
    {
        $response   = new Response();
        $refreshUrl = $this->generateUrl('jury_scoreboard');
        $contest    = $this->DOMJudgeService->getCurrentContest();
        $data       = $this->scoreboardService->getScoreboardTwigData($request, $response, $refreshUrl, true, false,
                                                                      false, $contest);

        if ($request->isXmlHttpRequest()) {
            return $this->render('@DOMJudge/partials/scoreboard.html.twig', $data, $response);
        }
        return $this->render('@DOMJudge/jury/scoreboard.html.twig', $data, $response);
    }
}
