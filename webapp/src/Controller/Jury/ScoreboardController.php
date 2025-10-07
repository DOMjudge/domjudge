<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Service\DOMJudgeService;
use App\Service\ScoreboardService;
use App\Twig\Attribute\AjaxTemplate;
use App\Twig\EventListener\CustomResponseListener;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(new Expression("is_granted('ROLE_JURY') or is_granted('ROLE_BALLOON')"))]
#[Route(path: '/jury/scoreboard')]
class ScoreboardController extends AbstractController
{
    public function __construct(protected readonly DOMJudgeService $dj, protected readonly ScoreboardService $scoreboardService)
    {
    }

    /**
     * @return array<string, mixed>
     */
    #[Route(path: '', name: 'jury_scoreboard')]
    #[AjaxTemplate(normalTemplate: 'jury/scoreboard.html.twig', ajaxTemplate: 'partials/scoreboard.html.twig')]
    public function scoreboardAction(Request $request, CustomResponseListener $customResponseListener): array
    {
        $response   = new Response();
        $refreshUrl = $this->generateUrl('jury_scoreboard');
        $contest    = $this->dj->getCurrentContest();
        $data       = $this->scoreboardService->getScoreboardTwigData(
            $request, $response, $refreshUrl, $this->isGranted('ROLE_JURY'), false, false, $contest
        );

        $customResponseListener->setCustomResponse($response);

        if ($request->isXmlHttpRequest()) {
            $data['current_contest'] = $contest;
        }
        return $data;
    }
}
