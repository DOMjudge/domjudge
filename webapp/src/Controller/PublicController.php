<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Team;
use App\Entity\TeamCategory;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ScoreboardService;
use App\Service\StatisticsService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;

#[Route(path: '/public')]
class PublicController extends BaseController
{
    public function __construct(
        DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly ScoreboardService $scoreboardService,
        protected readonly StatisticsService $stats,
        EntityManagerInterface $em,
        EventLogService $eventLog,
        KernelInterface $kernel,
    ) {
        parent::__construct($em, $eventLog, $dj, $kernel);
    }

    #[Route(path: '', name: 'public_index')]
    #[Route(path: '/scoreboard')]
    public function scoreboardAction(
        Request $request,
        #[MapQueryParameter(name: 'contest')]
        ?string $contestId = null,
        #[MapQueryParameter]
        ?bool $static = false,
    ): Response {
        $response         = new Response();
        $refreshUrl       = $this->generateUrl('public_index');
        $contest          = $this->dj->getCurrentContest(onlyPublic: true);
        $nonPublicContest = $this->dj->getCurrentContest(onlyPublic: false);
        if (!$contest && $nonPublicContest && $this->em->getRepository(TeamCategory::class)->count(['allow_self_registration' => 1])) {
            // This leaks a little bit of information about the existence of the non-public contest,
            // but since self registration is enabled, it's not a big deal.
            return $this->redirectToRoute('register');
        }


        if ($static) {
            $refreshParams = [
                'static' => 1,
            ];

            if ($requestedContest = $this->getContestFromRequest($contestId)) {
                $contest                  = $requestedContest;
                $refreshParams['contest'] = $contest->getCid();
            }

            $refreshUrl = sprintf('?%s', http_build_query($refreshParams));
        }

        $data = $this->scoreboardService->getScoreboardTwigData(
            $request, $response, $refreshUrl, false, true, $static, $contest
        );

        if ($static) {
            $data['hide_menu'] = true;
        }

        $data['current_contest'] = $contest;

        if ($request->isXmlHttpRequest()) {
            return $this->render('partials/scoreboard.html.twig', $data, $response);
        }
        return $this->render('public/scoreboard.html.twig', $data, $response);
    }

    #[Route(path: '/scoreboard.zip', name: 'public_scoreboard_data_zip')]
    public function scoreboardDataZipAction(
        RequestStack $requestStack,
        Request $request,
        #[MapQueryParameter(name: 'contest')]
        ?string $contestId = null
    ): Response {
        $contest = $this->getContestFromRequest($contestId) ?? $this->dj->getCurrentContest(onlyPublic: true);
        return $this->dj->getScoreboardZip($request, $requestStack, $contest, $this->scoreboardService);
    }

    /**
     * Get the contest from the request, if any
     */
    protected function getContestFromRequest(?string $contestId = null): ?Contest
    {
        $contest = null;
        // For static scoreboards, allow to pass a contest= param.
        if ($contestId) {
            if ($contestId === 'auto') {
                // Automatically detect the contest that is activated the latest.
                $activateTime = null;
                foreach ($this->dj->getCurrentContests(onlyPublic: true) as $possibleContest) {
                    if (!($possibleContest->getPublic() && $possibleContest->getEnabled())) {
                        continue;
                    }
                    if ($activateTime === null || $activateTime < $possibleContest->getActivatetime()) {
                        $activateTime = $possibleContest->getActivatetime();
                        $contest      = $possibleContest;
                    }
                }
            } else {
                // Find the contest with the given ID.
                foreach ($this->dj->getCurrentContests(onlyPublic: true) as $possibleContest) {
                    if ($possibleContest->getCid() == $contestId || $possibleContest->getExternalid() == $contestId) {
                        $contest = $possibleContest;
                        break;
                    }
                }

                if (!$contest) {
                    throw new NotFoundHttpException('Specified contest not found.');
                }
            }
        }

        return $contest;
    }

    #[Route(path: '/change-contest/{contestId<-?\d+>}', name: 'public_change_contest')]
    public function changeContestAction(Request $request, RouterInterface $router, int $contestId): Response
    {
        if ($this->isLocalReferer($router, $request)) {
            $response = new RedirectResponse($request->headers->get('referer'));
        } else {
            $response = $this->redirectToRoute('public_index');
        }
        return $this->dj->setCookie('domjudge_cid', (string)$contestId, 0, null, '', false, false,
                                                 $response);
    }

    #[Route(path: '/team/{teamId<\d+>}', name: 'public_team')]
    public function teamAction(Request $request, int $teamId): Response
    {
        /** @var Team|null $team */
        $team             = $this->em->getRepository(Team::class)->find($teamId);
        if ($team && $team->getCategory() && !$team->getCategory()->getVisible()) {
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
            return $this->render('public/team_modal.html.twig', $data);
        }

        return $this->render('public/team.html.twig', $data);
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route(path: '/problems', name: 'public_problems')]
    public function problemsAction(): Response
    {
        return $this->render('public/problems.html.twig',
            $this->dj->getTwigDataForProblemsAction($this->stats));
    }

    #[Route(path: '/problems/{probId<\d+>}/statement', name: 'public_problem_statement')]
    public function problemStatementAction(int $probId): StreamedResponse
    {
        return $this->getBinaryFile($probId, function (
            int $probId,
            Contest $contest,
            ContestProblem $contestProblem
        ) {
            $problem = $contestProblem->getProblem();

            try {
                return $problem->getProblemStatementStreamedResponse();
            } catch (BadRequestHttpException $e) {
                $this->addFlash('danger', $e->getMessage());
                return $this->redirectToRoute('public_problems');
            }
        });
    }

    #[Route(path: '/problemset', name: 'public_contest_problemset')]
    public function contestProblemsetAction(): StreamedResponse
    {
        $contest = $this->dj->getCurrentContest(onlyPublic: true);
        if (!$contest->getFreezeData()->started()) {
            throw new NotFoundHttpException('Contest problemset not found or not available');
        }
        return $contest->getContestProblemsetStreamedResponse();
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route(path: '/{probId<\d+>}/attachment/{attachmentId<\d+>}', name: 'public_problem_attachment')]
    public function attachmentAction(int $probId, int $attachmentId): StreamedResponse
    {
        return $this->getBinaryFile($probId, fn(
            int $probId,
            Contest $contest,
            ContestProblem $contestProblem
        ) => $this->dj->getAttachmentStreamedResponse($contestProblem, $attachmentId));
    }

    #[Route(path: '/{probId<\d+>}/samples.zip', name: 'public_problem_sample_zip')]
    public function sampleZipAction(int $probId): StreamedResponse
    {
        return $this->getBinaryFile($probId, function (int $probId, Contest $contest, ContestProblem $contestProblem) {
            return $this->dj->getSamplesZipStreamedResponse($contestProblem);
        });
    }

    /**
     * Get a binary file for the given problem ID using the given callable.
     *
     * Shared code between testcases, problem text and attachments.
     */
    protected function getBinaryFile(int $probId, callable $response): StreamedResponse
    {
        $contest = $this->dj->getCurrentContest(onlyPublic: true);
        if (!$contest || !$contest->getFreezeData()->started()) {
            throw new NotFoundHttpException(sprintf('Problem p%d not found or not available', $probId));
        }
        $contestProblem = $this->em->getRepository(ContestProblem::class)->find([
            'problem' => $probId,
            'contest' => $contest,
        ]);
        if (!$contestProblem) {
            throw new NotFoundHttpException(sprintf('Problem p%d not found or not available', $probId));
        }

        return $response($probId, $contest, $contestProblem);
    }
}
