<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Service\DOMJudgeService;
use App\Service\ScoreboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * @Route("/jury/scoreboard")
 * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_BALLOON')")
 */
class ScoreboardController extends AbstractController
{
    protected DOMJudgeService $dj;
    protected ScoreboardService $scoreboardService;

    public function __construct(
        DOMJudgeService $dj,
        ScoreboardService $scoreboardService
    ) {
        $this->dj                = $dj;
        $this->scoreboardService = $scoreboardService;
    }

    /**
     * @Route("", name="jury_scoreboard")
     */
    public function scoreboardAction(Request $request): Response
    {
        $response   = new Response();
        $refreshUrl = $this->generateUrl('jury_scoreboard');
        $contest    = $this->dj->getCurrentContest();
        $data       = $this->scoreboardService->getScoreboardTwigData(
            $request, $response, $refreshUrl, $this->isGranted('ROLE_JURY'), false, false, $contest
        );

        if ($request->isXmlHttpRequest()) {
            $data['current_contest'] = $contest;
            return $this->render('partials/scoreboard.html.twig', $data, $response);
        }
        return $this->render('jury/scoreboard.html.twig', $data, $response);
    }
}
