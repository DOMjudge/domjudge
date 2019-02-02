<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\API;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\Problem;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use DOMJudgeBundle\Service\ScoreboardService;
use FOS\RestBundle\Controller\Annotations as Rest;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @Rest\Route("/api/v4/contests/{cid}/awards", defaults={ "_format" = "json" })
 * @Rest\Prefix("/api/contests/{cid}/awards")
 * @Rest\NamePrefix("scoreboard_")
 * @SWG\Tag(name="Scoreboard")
 * @SWG\Parameter(ref="#/parameters/cid")
 * @SWG\Response(response="404", ref="#/definitions/NotFound")
 * @SWG\Response(response="401", ref="#/definitions/Unauthorized")
 */
class AwardsController extends AbstractRestController
{
    /**
     * @var ScoreboardService
     */
    protected $scoreboardService;

    /**
     * ScoreboardController constructor.
     * @param EntityManagerInterface $entityManager
     * @param DOMJudgeService        $DOMJudgeService
     * @param EventLogService        $eventLogService
     * @param ScoreboardService      $scoreboardService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        EventLogService $eventLogService,
        ScoreboardService $scoreboardService
    ) {
        parent::__construct($entityManager, $DOMJudgeService, $eventLogService);
        $this->scoreboardService = $scoreboardService;
    }

    /**
     * Get the awards standings for this contest
     * @param Request $request
     * @return array
     * @Rest\Get("")
     * @SWG\Response(
     *     response="200",
     *     description="Returns the current teams qualifying for each award"
     * )
     * @throws \Exception
     */
    public function getAwardsAction(Request $request)
    {
        $public   = false;
        if ($this->DOMJudgeService->checkrole('jury') && $request->query->has('public')) {
            $public = $request->query->getBoolean('public');
        }

        $contest       = $this->entityManager->getRepository(Contest::class)->find($this->getContestId($request));
        $isJury        = $this->isGranted('ROLE_JURY');
        $accessAllowed = ($isJury && $contest->getEnabled()) || (!$isJury && $contest->isActive());
        if (!$accessAllowed) {
            throw new AccessDeniedHttpException();
        }

        $probuseextid = !is_null($this->eventLogService->externalIdFieldForEntity(Problem::class));
        $teamuseextid = !is_null($this->eventLogService->externalIdFieldForEntity(Team::class));

        $additionalBronzeMedals = $contest->getB() ?? 0;

        $scoreboard = $this->scoreboardService->getScoreboard($contest, !$public, null, true);

        $group_winners = $problem_winners = [];
        foreach ($scoreboard->getTeams() as $team) {
            $teamid = (string)($teamuseextid ? $team->getExternalid() : $team->getTeamid());
            if ( $scoreboard->isBestInCategory($team) ) {
                $group_winners[$team->getCategoryId()][] = $teamid;
            }
            foreach($scoreboard->getProblems() as $problem) {
                $probid = (string)($probuseextid ? $problem->getExternalid() : $problem->getProbid());
                if ($scoreboard->solvedFirst($team, $problem)) {
                    $problem_winners[$probid][] = $teamid;
                }
            }
        }

        $overall_winners = $medal_winners = [];
        // can we assume this is ordered just walk the first 12+B entries?
        foreach ($scoreboard->getScores() as $teamScore) {
            $rank = $teamScore->getRank();
            $teamid = (string)($teamuseextid ? $teamScore->getTeam()->getExternalid() : $teamScore->getTeam()->getTeamid());

            if ($rank === 1) {
                $overall_winners[] = $teamid;
            }
            if ($rank <= 4 ) {
                $medal_winners['gold'][] = $teamid;
            } elseif ($rank <= 8 ) {
                $medal_winners['silver'][] = $teamid;
            } elseif ($rank <= 12 + $additionalBronzeMedals ) {
                $medal_winners['bronze'][] = $teamid;
            }
        }

        $results = [];
        foreach($group_winners as $id => $team_ids) {
                $results[] = [ 'id' => 'group-winner-' . $id,
                        'citation' => 'Winner(s) of group ' . $id,
                        'team_ids' => $team_ids];
        }
        foreach($problem_winners as $id => $team_ids) {
                $results[] = [ 'id' => 'first-to-solve-' . $id,
                        'citation' => 'First to solve problem ' . $id,
                        'team_ids' => $team_ids];
        }
        if ( count($overall_winners) > 0 ) {
                $results[] = ['id' => 'winner',
                        'citation' => 'Contest winner',
                        'team_ids' => $overall_winners ];
        }
        foreach($medal_winners as $metal => $team_ids) {
                $results[] = ['id' => $metal . '-medal',
                        'citation' => ucfirst($metal) . ' medal winner',
                        'team_ids' => $team_ids ];
        }

        return $results;
    }

    /**
     * @inheritdoc
     */
    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        // Not used for awards endpoint
        return null;
    }

    /**
     * @inheritdoc
     */
    protected function getIdField(): string
    {
        // Not used for awaards endpoint
        return '';
    }
}
