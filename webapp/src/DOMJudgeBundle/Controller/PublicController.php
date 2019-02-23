<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\ScoreboardService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class PublicController
 *
 * @Route("/public")
 *
 * @package DOMJudgeBundle\Controller
 */
class PublicController extends BaseController
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
     * @var EntityManagerInterface
     */
    protected $entityManager;

    public function __construct(
        DOMJudgeService $DOMJudgeService,
        ScoreboardService $scoreboardService,
        EntityManagerInterface $entityManager
    ) {
        $this->DOMJudgeService   = $DOMJudgeService;
        $this->scoreboardService = $scoreboardService;
        $this->entityManager     = $entityManager;
    }

    /**
     * @Route("", name="public_index")
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function scoreboardAction(Request $request)
    {
        $response   = new Response();
        $static     = $request->query->getBoolean('static');
        $data       = [];
        $refreshUrl = $this->generateUrl('public_index');
        // Determine contest to use
        $contest = $this->DOMJudgeService->getCurrentContest(-1);

        if ($static) {
            $data['hide_menu'] = false;
            $refreshParams     = [
                'static' => 1,
            ];
            // For static scoreboards, allow to pass a contest= param
            if ($contestId = $request->query->get('contest')) {
                if ($contestId === 'auto') {
                    // Automatically detect the contest that is activated the latest
                    $contest      = null;
                    $activateTime = null;
                    foreach ($this->DOMJudgeService->getCurrentContests() as $possibleContest) {
                        if (!$possibleContest->getPublic() || !$possibleContest->getEnabled()) {
                            continue;
                        }
                        if ($activateTime === null || $activateTime < $possibleContest->getActivatetime()) {
                            $activateTime = $possibleContest->getActivatetime();
                            $contest      = $possibleContest;
                        }
                    }
                } else {
                    // Find the contest with the given ID
                    $contest = null;
                    foreach ($this->DOMJudgeService->getCurrentContests() as $possibleContest) {
                        if ($possibleContest->getCid() == $contestId || $possibleContest->getExternalid() == $contestId) {
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

        if ($contest) {
            $data['refresh'] = [
                'after' => 30,
                'url' => $refreshUrl,
                'ajax' => true,
            ];

            $scoreFilter = $this->scoreboardService->initializeScoreboardFilter($request, $response);
            $scoreboard  = $this->scoreboardService->getScoreboard($contest, false, $scoreFilter);

            $data['contest']              = $contest;
            $data['static']               = $static;
            $data['static']               = $static;
            $data['scoreFilter']          = $scoreFilter;
            $data['scoreboard']           = $scoreboard;
            $data['filterValues']         = $this->scoreboardService->getFilterValues($contest, true);
            $data['showFlags']            = $this->DOMJudgeService->dbconfig_get('show_flags', true);
            $data['showAffiliationLogos'] = $this->DOMJudgeService->dbconfig_get('show_affiliation_logos', false);
            $data['showAffiliations']     = $this->DOMJudgeService->dbconfig_get('show_affiliations', true);
            $data['showPending']          = $this->DOMJudgeService->dbconfig_get('show_pending', false);
            $data['showTeamSubmissions']  = $this->DOMJudgeService->dbconfig_get('show_teams_submissions', false);
            $data['scoreInSeconds']       = $this->DOMJudgeService->dbconfig_get('score_in_seconds', false);
        }

        if ($request->isXmlHttpRequest()) {
            $data['jury']   = false;
            $data['public'] = true;
            $data['ajax']   = true;
            return $this->render('@DOMJudge/partials/scoreboard.html.twig', $data, $response);
        }
        return $this->render('@DOMJudge/public/scoreboard.html.twig', $data, $response);
    }

    /**
     * @Route("/change-contest/{contestId}", name="public_change_contest")
     * @param Request         $request
     * @param RouterInterface $router
     * @param int             $contestId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function changeContestAction(Request $request, RouterInterface $router, int $contestId)
    {
        if ($this->isLocalReferrer($router, $request)) {
            $response = new RedirectResponse($request->headers->get('referer'));
        } else {
            $response = $this->redirectToRoute('public_index');
        }
        return $this->DOMJudgeService->setCookie('domjudge_cid', (string)$contestId, 0, null, '', false, false,
                                                 $response);
    }

    /**
     * @Route("/team/{teamId}", name="public_team")
     * @param int $teamId
     * @return Response
     * @throws \Exception
     */
    public function teamAction(int $teamId)
    {
        $team             = $this->entityManager->getRepository(Team::class)->find($teamId);
        $showFlags        = (bool)$this->DOMJudgeService->dbconfig_get('show_flags', true);
        $showAffiliations = (bool)$this->DOMJudgeService->dbconfig_get('show_affiliations', true);

        return $this->render('@DOMJudge/public/team.html.twig', [
            'team' => $team,
            'showFlags' => $showFlags,
            'showAffiliations' => $showAffiliations,
        ]);
    }
}
