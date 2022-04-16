<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Contest;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ScoreboardService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Rest\Route("/contests/{cid}/awards")
 * @OA\Tag(name="Awards")
 * @OA\Parameter(ref="#/components/parameters/cid")
 * @OA\Response(response="404", ref="#/components/responses/NotFound")
 * @OA\Response(response="401", ref="#/components/responses/Unauthorized")
 * @OA\Response(response="400", ref="#/components/responses/InvalidResponse")
 */
class AwardsController extends AbstractRestController
{
    protected ScoreboardService $scoreboardService;

    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        ConfigurationService $config,
        EventLogService $eventLogService,
        ScoreboardService $scoreboardService
    ) {
        parent::__construct($entityManager, $DOMJudgeService, $config, $eventLogService);
        $this->scoreboardService = $scoreboardService;
    }

    /**
     * Get all the awards standings for this contest.
     * @Rest\Get("")
     * @OA\Response(
     *     response="200",
     *     description="Returns the current teams qualifying for each award",
     *     @OA\JsonContent(
     *         type="array",
     *         @OA\Items(ref="#/components/schemas/Award")
     *     )
     * )
     * @OA\Parameter(ref="#/components/parameters/strict")
     * @throws Exception
     */
    public function listAction(Request $request): ?array
    {
        return $this->getAwardsData($request);
    }

    /**
     * Get the specific award for this contest.
     * @Rest\Get("/{id}")
     * @OA\Response(
     *     response="200",
     *     description="Returns the award for this contest",
     *     @OA\JsonContent(ref="#/components/schemas/Award")
     * )
     * @OA\Parameter(ref="#/components/parameters/id")
     * @OA\Parameter(ref="#/components/parameters/strict")
     * @throws Exception
     */
    public function singleAction(Request $request, string $id): array
    {
        $award = $this->getAwardsData($request, $id);

        if ($award === null) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $id));
        }

        return $award;
    }

    /**
     * Get the awards data for the given request and optional award ID.
     */
    protected function getAwardsData(Request $request, string $requestedType = null): ?array
    {
        // TODO: move this to a service so the scoreboard can use its logic.
        // Probably best to do it when we implement https://github.com/DOMjudge/domjudge/issues/1079

        $public = !$this->dj->checkrole('api_reader');
        if ($this->dj->checkrole('api_reader') && $request->query->has('public')) {
            $public = $request->query->getBoolean('public');
        }
        /** @var Contest $contest */
        $contest       = $this->em->getRepository(Contest::class)->find($this->getContestId($request));
        $isJury        = $this->dj->checkrole('api_reader');
        $accessAllowed = ($isJury && $contest->getEnabled()) || (!$isJury && $contest->isActive());
        if (!$accessAllowed) {
            throw new AccessDeniedHttpException();
        }
        $additionalBronzeMedals = $contest->getB() ?? 0;
        $scoreboard = $this->scoreboardService->getScoreboard($contest, !$public, null, true);
        $group_winners = $problem_winners = [];
        $groups = [];
        foreach ($scoreboard->getTeams() as $team) {
            $teamid = $team->getApiId($this->eventLogService);
            if ($scoreboard->isBestInCategory($team)) {
                $group_winners[$team->getCategory()->getCategoryId()][] = $teamid;
                $groups[$team->getCategory()->getCategoryid()] = $team->getCategory()->getName();
            }
            foreach($scoreboard->getProblems() as $problem) {
                $probid = $problem->getApiId($this->eventLogService);
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

        // Can we assume this is ordered just walk the first 12+B entries?
        foreach ($scoreboard->getScores() as $teamScore) {
            $rank = $teamScore->rank;
            $teamid = $teamScore->team->getApiId($this->eventLogService);
            if ($rank === 1) {
                $overall_winners[] = $teamid;
            }
            if ($contest->getMedalsEnabled() && $contest->getMedalCategories()->contains($teamScore->team->getCategory())) {
                if ($rank <= $contest->getGoldMedals()) {
                    $medal_winners['gold'][] = $teamid;
                } elseif ($rank <= $contest->getGoldMedals() + $contest->getSilverMedals()) {
                    $medal_winners['silver'][] = $teamid;
                } elseif ($rank <= $contest->getGoldMedals() + $contest->getSilverMedals() + $contest->getBronzeMedals() + $additionalBronzeMedals) {
                    $medal_winners['bronze'][] = $teamid;
                }
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

    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        throw new Exception('Not implemented');
    }

    protected function getIdField(): string
    {
        throw new Exception('Not implemented');
    }
}
