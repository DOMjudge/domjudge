<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Service\DOMJudgeService;
use App\Service\ScoreboardService;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury/scoreboard")
 * @IsGranted("ROLE_JURY")
 */
class ScoreboardController extends AbstractController
{
    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var ScoreboardService
     */
    protected $scoreboardService;

    public function __construct(
        DOMJudgeService $dj,
        ScoreboardService $scoreboardService
    ) {
        $this->dj                = $dj;
        $this->scoreboardService = $scoreboardService;
    }

    /**
     * @Route("", name="jury_scoreboard")
     * @throws Exception
     */
    public function scoreboardAction(Request $request) : Response
    {
        $response   = new Response();
        $refreshUrl = $this->generateUrl('jury_scoreboard');
        $contest    = $this->dj->getCurrentContest();
        $data       = $this->scoreboardService->getScoreboardTwigData(
            $request, $response, $refreshUrl, true, false, false, $contest
        );

        if ($request->isXmlHttpRequest()) {
            $data['current_contest'] = $contest;
            return $this->render('partials/scoreboard.html.twig', $data, $response);
        }
        return $this->render('jury/scoreboard.html.twig', $data, $response);
    }
}
