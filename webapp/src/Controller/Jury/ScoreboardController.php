<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\ScoreboardSubmissionsTrait;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\ScoreboardService;
use App\Service\SubmissionService;
use App\Twig\TwigExtension;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(new Expression("is_granted('ROLE_JURY') or is_granted('ROLE_BALLOON')"))]
#[Route(path: '/jury/scoreboard')]
class ScoreboardController extends AbstractController
{
    use ScoreboardSubmissionsTrait;

    public function __construct(
        protected readonly DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly ScoreboardService $scoreboardService,
        protected readonly SubmissionService $submissionService,
        protected readonly TwigExtension $twigExtension,
        protected readonly EntityManagerInterface $em,
    ) {
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

        if ($request->isXmlHttpRequest()) {
            $data['current_contest'] = $contest;
            return $this->render('partials/scoreboard.html.twig', $data, $response);
        }
        return $this->render('jury/scoreboard.html.twig', $data, $response);
    }

    #[Route(path: '/submissions/team/{teamId}/problem/{problemId}', name: 'jury_balloon_submissions')]
    #[IsGranted('ROLE_BALLOON')]
    public function submissionsAction(string $teamId, string $problemId): Response
    {
        $contest = $this->dj->getCurrentContest();

        if (!$contest) {
            throw $this->createNotFoundException('No active contest found');
        }

        return $this->getSubmissionsPageResponse($contest, $teamId, $problemId, 'jury_balloon_submissions_data_cell', 'jury/base.html.twig');
    }

    #[Route(path: '/submissions-data/team/{teamId}/problem/{problemId}.json', name: 'jury_balloon_submissions_data_cell')]
    #[IsGranted('ROLE_BALLOON')]
    public function submissionsDataAction(string $teamId, string $problemId): JsonResponse
    {
        $contest = $this->dj->getCurrentContest();

        if (!$contest) {
            throw $this->createNotFoundException('No active contest found');
        }

        return $this->getSubmissionsDataResponse($contest, $teamId, $problemId);
    }
}
