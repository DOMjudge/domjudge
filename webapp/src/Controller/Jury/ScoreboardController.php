<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Service\DOMJudgeService;
use App\Service\ScoreboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(new Expression("is_granted('ROLE_JURY') or is_granted('ROLE_BALLOON')"))]
#[Route(path: '/jury/scoreboard')]
class ScoreboardController extends AbstractController
{
    public function __construct(protected readonly DOMJudgeService $dj, protected readonly ScoreboardService $scoreboardService)
    {
    }

    #[Route(path: '', name: 'jury_scoreboard')]
    public function scoreboardAction(Request $request): Response
    {
        $response   = new Response();
        $refreshUrl = $this->generateUrl('jury_scoreboard');
        $contest    = $this->dj->getCurrentContest();
        $data       = $this->scoreboardService->getScoreboardTwigData(
            $request, $response, $refreshUrl, $this->isGranted('ROLE_JURY'), false, false, $contest
        );
        $data['scroll_width'] = true;

        if ($request->isXmlHttpRequest()) {
            $data['current_contest'] = $contest;
            return $this->render('partials/scoreboard.html.twig', $data, $response);
        }
        return $this->render('jury/scoreboard.html.twig', $data, $response);
    }
}
