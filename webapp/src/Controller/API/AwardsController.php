<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Contest;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ScoreboardService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
     * Get all the awards standings for this contest
     * @param Request $request
     * @return array
     * @Rest\Get("")
     * @IsGranted({"ROLE_JURY", "ROLE_API_READER"})
     * @SWG\Response(
     *     response="200",
     *     description="Returns the current teams qualifying for each award",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(ref="#/definitions/Award")
     *     )
     * )
     * @throws \Exception
     */
    public function listAction(Request $request)
    {
        return $this->getAwardsData($request);
    }

    /**
     * Get the given clarifications for this contest
     * @param Request $request
     * @param string  $id
     * @return array
     * @Rest\Get("/{id}")
     * @SWG\Response(
     *     response="200",
     *     description="Returns the award for this contest",
     *     @SWG\Schema(ref="#/definitions/Award")
     * )
     * @SWG\Parameter(ref="#/parameters/id")
     * @throws \Exception
     */
    public function singleAction(Request $request, string $id)
    {
        $award = $this->getAwardsData($request, $id);

        if ($award === null) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $id));
        }

        return $award;
    }

    /**
     * Get the awards data for the given request and optional award ID
     * @param Request     $request
     * @param string|null $requestedType
     * @return array
     * @throws \Exception
     */
    protected function getAwardsData(Request $request, string $requestedType = null)
    {
        $public   = false;
        if ($this->dj->checkrole('jury') && $request->query->has('public')) {
            $public = $request->query->getBoolean('public');
        }
        /** @var Contest $contest */
        $contest       = $this->em->getRepository(Contest::class)->find($this->getContestId($request));
        $isJury        = $this->isGranted('ROLE_JURY');
        $accessAllowed = ($isJury && $contest->getEnabled()) || (!$isJury && $contest->isActive());
        if (!$accessAllowed) {
            throw new AccessDeniedHttpException();
        }
        $additionalBronzeMedals = $contest->getB() ?? 0;
        $scoreboard = $this->scoreboardService->getScoreboard($contest, !$public, null, true);
        $group_winners = $problem_winners = [];
        $groups = [];
        foreach ($scoreboard->getTeams() as $team) {
            $teamid = (string)$team->getApiId($this->eventLogService);
            if ($scoreboard->isBestInCategory($team)) {
                $group_winners[$team->getCategoryId()][] = $teamid;
                $groups[$team->getCategoryid()] = $team->getCategory()->getName();
            }
            foreach($scoreboard->getProblems() as $problem) {
                $probid = (string)$problem->getApiId($this->eventLogService);
                if ($scoreboard->solvedFirst($team, $problem)) {
                    $problem_winners[$probid][] = $teamid;
                }
            }
        }
        $results = [];
        foreach ($group_winners as $id => $team_ids) {
            $type = 'group-winner-' . $id;
            $result = [ 'id' => $type,
                'citation' => 'Winner(s) of group ' . $groups[$id],
                'team_ids' => $team_ids];
            if ($requestedType === $type) {
                return $result;
            }
            $results[] = $result;
        }
        foreach ($problem_winners as $id => $team_ids) {
            $type = 'first-to-solve-' . $id;
            $result = [ 'id' => $type,
                'citation' => 'First to solve problem ' . $id,
                'team_ids' => $team_ids];
            if ($requestedType === $type) {
                return $result;
            }
            $results[] = $result;
        }
        $overall_winners = $medal_winners = [];
        // can we assume this is ordered just walk the first 12+B entries?
        foreach ($scoreboard->getScores() as $teamScore) {
            $rank = $teamScore->getRank();
            $teamid = (string)$teamScore->getTeam()->getApiId($this->eventLogService);
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
        if (count($overall_winners) > 0) {
            $type = 'winner';
            $result = ['id' => $type,
                'citation' => 'Contest winner',
                'team_ids' => $overall_winners ];
            if ($requestedType === $type) {
                return $result;
            }
            $results[] = $result;
        }
        foreach ($medal_winners as $metal => $team_ids) {
            $type = $metal . '-medal';
            $result = ['id' => $metal . '-medal',
                'citation' => ucfirst($metal) . ' medal winner',
                'team_ids' => $team_ids ];
            if ($requestedType === $type) {
                return $result;
            }
            $results[] = $result;
        }

        // Specific type was requested, but not found above.
        if (!is_null($requestedType)) {
            return null;
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
