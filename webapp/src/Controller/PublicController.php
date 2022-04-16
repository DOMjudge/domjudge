<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Team;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\ScoreboardService;
use App\Service\StatisticsService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class PublicController
 *
 * @Route("/public")
 *
 * @package App\Controller
 */
class PublicController extends BaseController
{
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected ScoreboardService $scoreboardService;
    protected StatisticsService $stats;
    protected EntityManagerInterface $em;

    public function __construct(
        DOMJudgeService $dj,
        ConfigurationService $config,
        ScoreboardService $scoreboardService,
        StatisticsService $stats,
        EntityManagerInterface $em
    ) {
        $this->dj                = $dj;
        $this->config            = $config;
        $this->scoreboardService = $scoreboardService;
        $this->stats             = $stats;
        $this->em                = $em;
    }

    /**
     * @Route("", name="public_index")
     */
    public function scoreboardAction(Request $request): Response
    {
        $response   = new Response();
        $static     = $request->query->getBoolean('static');
        $refreshUrl = $this->generateUrl('public_index');
        // Determine contest to use
        $contest = $this->dj->getCurrentContest(-1);

        if ($static) {
            $refreshParams = [
                'static' => 1,
            ];
            // For static scoreboards, allow to pass a contest= param.
            if ($contestId = $request->query->get('contest')) {
                if ($contestId === 'auto') {
                    // Automatically detect the contest that is activated the latest.
                    $contest      = null;
                    $activateTime = null;
                    foreach ($this->dj->getCurrentContests(-1) as $possibleContest) {
                        if (!$possibleContest->getPublic() || !$possibleContest->getEnabled()) {
                            continue;
                        }
                        if ($activateTime === null || $activateTime < $possibleContest->getActivatetime()) {
                            $activateTime = $possibleContest->getActivatetime();
                            $contest      = $possibleContest;
                        }
                    }
                } else {
                    // Find the contest with the given ID.
                    $contest = null;
                    foreach ($this->dj->getCurrentContests(-1) as $possibleContest) {
                        if ($possibleContest->getCid() === $contestId || $possibleContest->getExternalid() === $contestId) {
                            $contest = $possibleContest;
                            break;
                        }
                    }

                    if ($contest) {
                        $refreshParams['contest'] = $contest->getCid();
                    } else {
                        throw new NotFoundHttpException('Specified contest not found');
                    }
                }
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

    /**
     * @Route("/change-contest/{contestId<-?\d+>}", name="public_change_contest")
     */
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

    /**
     * @Route("/team/{teamId<\d+>}", name="public_team")
     */
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
     * @Route("/problems", name="public_problems")
     * @throws NonUniqueResultException
     */
    public function problemsAction(): Response
    {
        return $this->render('public/problems.html.twig',
            $this->dj->getTwigDataForProblemsAction(-1, $this->stats));
    }

    /**
     * @Route("/problems/{probId<\d+>}/text", name="public_problem_text")
     */
    public function problemTextAction(int $probId): StreamedResponse
    {
        return $this->getBinaryFile($probId, function (
            int $probId,
            Contest $contest,
            ContestProblem $contestProblem
        ) {
            $problem = $contestProblem->getProblem();

            try {
                return $problem->getProblemTextStreamedResponse();
            } catch (BadRequestHttpException $e) {
                $this->addFlash('danger', $e->getMessage());
                return $this->redirectToRoute('public_problems');
            }
        });
    }

    /**
     * @Route(
     *     "/{probId<\d+>}/attachment/{attachmentId<\d+>}",
     *     name="public_problem_attachment"
     *     )
     * @throws NonUniqueResultException
     */
    public function attachmentAction(int $probId, int $attachmentId): StreamedResponse
    {
        return $this->getBinaryFile($probId, function (
            int $probId,
            Contest $contest,
            ContestProblem $contestProblem
        ) use ($attachmentId) {
            return $this->dj->getAttachmentStreamedResponse($contestProblem,
                $attachmentId);
        });
    }

    /**
     * @Route("/{probId<\d+>}/samples.zip", name="public_problem_sample_zip")
     */
    public function sampleZipAction(int $probId): StreamedResponse
    {
        return $this->getBinaryFile($probId, function(int $probId, Contest $contest, ContestProblem $contestProblem) {
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
        $contest = $this->dj->getCurrentContest(-1);
        if (!$contest || !$contest->getFreezeData()->started()) {
            throw new NotFoundHttpException(sprintf('Problem p%d not found or not available', $probId));
        }
        /** @var ContestProblem $contestProblem */
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
