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
 * @Route("/jury")
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
     * @Route("/scoreboard/", name="jury_scoreboard")
     * @throws \Exception
     */
    public function scoreboardAction(Request $request)
    {
        $response = new Response();
        $data     = [];
        if ($contest = $this->DOMJudgeService->getCurrentContest()) {
            $data['refresh'] = [
                'after' => 30,
                'url' => $this->generateUrl('jury_scoreboard'),
                'ajax' => true,
            ];

            $scoreFilter = $this->scoreboardService->initializeScoreboardFilter($request, $response);
            $scoreboard  = $this->scoreboardService->getScoreboard($contest, true, $scoreFilter);

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
            $data['jury'] = true;
            return $this->render('@DOMJudge/partials/scoreboard.html.twig', $data, $response);
        }
        return $this->render('@DOMJudge/jury/scoreboard.html.twig', $data, $response);
    }
}
