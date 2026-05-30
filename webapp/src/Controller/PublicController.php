<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Clarification;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Problem;
use App\Entity\Team;
use App\Entity\TeamCategory;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ScoreboardService;
use App\Service\StatisticsService;
use App\Service\SubmissionService;
use App\Twig\TwigExtension;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    use ScoreboardSubmissionsTrait;
    public function __construct(
        DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly ScoreboardService $scoreboardService,
        protected readonly StatisticsService $stats,
        protected readonly SubmissionService $submissionService,
        protected readonly TwigExtension $twigExtension,
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
                $refreshParams['contest'] = $contest->getExternalid();
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

    #[Route(path: '/scoreboard-category-color.css', name: 'scoreboard_category_color_css')]
    public function scoreboardCategoryColorCss(Request $request): Response {
        $content = $this->renderView('public/scoreboard_category_color.css.twig', $this->dj->getScoreboardCategoryColorCss());
        $response = new Response();
        $response->headers->set('Content-Type', 'text/css');
        // See: https://symfony.com/doc/current/http_cache/validation.html
        $response->setEtag(md5($content));
        $response->setPublic();
        $response->isNotModified($request);
        $response->setContent($content);
        return $response;
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
                    if ($possibleContest->getExternalid() === $contestId) {
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

    #[Route(path: '/change-contest/{contestId}', name: 'public_change_contest')]
    public function changeContestAction(Request $request, RouterInterface $router, string $contestId): Response
    {
        if ($this->isLocalReferer($router, $request)) {
            $response = new RedirectResponse($request->headers->get('referer'));
        } else {
            $response = $this->redirectToRoute('public_index');
        }
        return $this->dj->setCookie('domjudge_cid', $contestId, 0, null, '', false, false,
                                                 $response);
    }

    #[Route(path: '/team/{teamId}', name: 'public_team')]
    public function teamAction(Request $request, string $teamId): Response
    {
        /** @var Team|null $team */
        $team = $this->em->createQueryBuilder()
                         ->from(Team::class, 't')
                         ->innerJoin('t.categories', 'tc')
                         ->select('t, tc')
                         ->andWhere('tc.visible = 1')
                         ->andWhere('t.externalid = :teamId')
                         ->setParameter('teamId', $teamId)
                         ->getQuery()
                         ->getOneOrNullResult();
        if ($team?->getHidden()) {
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

    #[Route(path: '/problems/{probId}/statement', name: 'public_problem_statement')]
    public function problemStatementAction(string $probId): StreamedResponse
    {
        return $this->getBinaryFile($probId, function (
            string $probId,
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
        if (!$contest || !$contest->getFreezeData()->started()) {
            throw new NotFoundHttpException('Contest problemset not found or not available');
        }
        return $contest->getContestProblemsetStreamedResponse();
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route(path: '/{probId}/attachment/{attachmentId<\d+>}', name: 'public_problem_attachment')]
    public function attachmentAction(string $probId, int $attachmentId): StreamedResponse
    {
        return $this->getBinaryFile($probId, fn(
            string $probId,
            Contest $contest,
            ContestProblem $contestProblem
        ) => $this->dj->getAttachmentStreamedResponse($contestProblem, $attachmentId));
    }

    #[Route(path: '/{probId}/samples.zip', name: 'public_problem_sample_zip')]
    public function sampleZipAction(string $probId): StreamedResponse
    {
        return $this->getBinaryFile($probId, function (string $probId, Contest $contest, ContestProblem $contestProblem) {
            return $this->dj->getSamplesZipStreamedResponse($contestProblem);
        });
    }

    /**
     * Get a binary file for the given problem ID using the given callable.
     *
     * Shared code between testcases, problem text and attachments.
     */
    protected function getBinaryFile(string $probId, callable $response): StreamedResponse
    {
        $contest = $this->dj->getCurrentContest(onlyPublic: true);
        if (!$contest || !$contest->getFreezeData()->started()) {
            throw new NotFoundHttpException(sprintf('Problem %s not found or not available', $probId));
        }
        $contestProblem = $this->em->getRepository(ContestProblem::class)->findByProblemAndContest($contest, $probId);
        if (!$contestProblem) {
            throw new NotFoundHttpException(sprintf('Problem %s not found or not available', $probId));
        }

        return $response($probId, $contest, $contestProblem);
    }

    #[Route(path: '/submissions/team/{teamId}/problem/{problemId}', name: 'public_submissions')]
    public function submissionsAction(Request $request, string $teamId, string $problemId): Response
    {
        $contest = $this->dj->getCurrentContest(onlyPublic: true);

        if (!$contest) {
            throw $this->createNotFoundException('No active contest found');
        }

        return $this->getSubmissionsPageResponse($contest, $teamId, $problemId, 'public_submissions_data_cell');
    }

    #[Route(path: '/submissions-data.json', name: 'public_submissions_data')]
    #[Route(path: '/submissions-data/team/{teamId}/problem/{problemId}.json', name: 'public_submissions_data_cell')]
    public function submissionsDataAction(Request $request, ?string $teamId, ?string $problemId): JsonResponse
    {
        /** @var Contest|null $contest */
        $contest = $request->attributes->get('_domjudge_static_scoreboard_contest')
            ?? $this->dj->getCurrentContest(onlyPublic: true);

        if (!$contest) {
            throw $this->createNotFoundException('No active contest found');
        }

        $forceUnfrozen = (bool)$request->attributes->get(
            '_domjudge_static_scoreboard_force_unfrozen',
            false
        );

        return $this->getSubmissionsDataResponse($contest, $teamId, $problemId, $forceUnfrozen);
    }

    #[Route(path: '/clarifications/by-problem/{probId}', name: 'public_clarification_by_prob')]
    public function viewByProblemAction(Request $request, string $probId): Response
    {
        $contest = $this->dj->getCurrentContest();
        if (!$contest) {
            throw new NotFoundHttpException('No active contest');
        }

        $problem = $this->em->getRepository(Problem::class)->findByExternalId($probId);
        if ($problem === null) {
            throw new NotFoundHttpException(sprintf('Problem %s not found', $probId));
        }
        $contestProblem = $problem->getContestProblems();
        $foundProblemInContest = false;
        foreach ($contestProblem as $cp) {
            if ($cp->getContest()->getCid() === $contest->getCid()) {
                $foundProblemInContest = true;
                break;
            }
        }
        if (!$foundProblemInContest) {
            throw new NotFoundHttpException(sprintf('Problem %s not in current contest', $probId));
        }

        /** @var Clarification[] $clarifications */
        $clarifications = [];
        if ($contest->getStartTimeObject()?->getTimestamp() <= time()) {
            $clarifications = $this->em->createQueryBuilder()
                ->from(Clarification::class, 'c')
                ->leftJoin('c.problem', 'p')
                ->leftJoin('c.sender', 's')
                ->leftJoin('c.recipient', 'r')
                ->select('c', 'p')
                ->andWhere('c.contest = :contest')
                ->andWhere('c.sender IS NULL')
                ->andWhere('c.recipient IS NULL')
                ->andWhere('c.problem = :problem')
                ->setParameter('contest', $contest)
                ->setParameter('problem', $problem)
                ->addOrderBy('c.submittime', 'DESC')
                ->addOrderBy('c.clarid', 'DESC')
                ->getQuery()
                ->getResult();
        }

        $data = [
            'clarifications' => $clarifications,
            'problem' => $problem,
        ];
        if ($request->isXmlHttpRequest()) {
            return $this->render('clarifications_by_problem_modal.html.twig', $data);
        } else {
            return $this->render('clarifications_by_problem.html.twig', $data);
        }
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route(path: '/clarifications/{clarId}', name: 'public_clarification')]
    public function viewAction(Request $request, string $clarId): Response
    {
        $categories = $this->config->get('clar_categories');
        $contest    = $this->dj->getCurrentContest();
        /** @var Clarification|null $clarification */
        $clarification = $this->em->createQueryBuilder()
            ->from(Clarification::class, 'c')
            ->leftJoin('c.problem', 'p')
            ->leftJoin('c.contest', 'co')
            ->leftJoin('p.contest_problems', 'cp', Join::WITH, 'cp.contest = :contest')
            ->select('c, p, co')
            ->andWhere('c.contest = :contest')
            ->andWhere('c.externalid = :clarId')
            ->andWhere('c.sender IS NULL')
            ->andWhere('c.recipient IS NULL')
            ->setParameter('contest', $contest)
            ->setParameter('clarId', $clarId)
            ->getQuery()
            ->getOneOrNullResult();
    
        if ($clarification === null) {
            throw new NotFoundHttpException(sprintf('Clarification %s not found', $clarId));
        }
    
        $data = [
            'clarification' => $clarification,
            'categories' => $categories,
        ];
    
        if ($request->isXmlHttpRequest()) {
            return $this->render('clarification_modal.html.twig', $data);
        } else {
            return $this->render('clarification.html.twig', $data);
        }
    }
}
