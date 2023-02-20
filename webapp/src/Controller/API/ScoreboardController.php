<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Contest;
use App\Entity\Event;
use App\Entity\TeamCategory;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ScoreboardService;
use App\Utils\Scoreboard\Filter;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Rest\Route("/contests/{cid}/scoreboard")
 * @OA\Tag(name="Scoreboard")
 * @OA\Parameter(ref="#/components/parameters/cid")
 * @OA\Parameter(ref="#/components/parameters/strict")
 * @OA\Response(response="400", ref="#/components/responses/InvalidResponse")
 * @OA\Response(response="401", ref="#/components/responses/Unauthenticated")
 * @OA\Response(response="403", ref="#/components/responses/Unauthorized")
 * @OA\Response(response="404", ref="#/components/responses/NotFound")
 */
class ScoreboardController extends AbstractRestController
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
     * Get the scoreboard for this contest.
     * @Rest\Get("")
     * @OA\Response(
     *     response="200",
     *     description="Returns the scoreboard",
     *     @OA\JsonContent(ref="#/components/schemas/Scoreboard")
     * )
     * @OA\Parameter(
     *     name="allteams",
     *     in="query",
     *     description="Also show invisible teams. Requires jury privileges",
     *     @OA\Schema(type="boolean")
     * )
     * @OA\Parameter(
     *     name="category",
     *     in="query",
     *     description="Get the scoreboard for only this category",
     *     @OA\Schema(type="integer")
     * )
     * @OA\Parameter(
     *     name="country",
     *     in="query",
     *     description="Get the scoreboard for only this country (in ISO 3166-1 alpha-3 format)",
     *     @OA\Schema(type="string")
     * )
     * @OA\Parameter(
     *     name="affiliation",
     *     in="query",
     *     description="Get the scoreboard for only this affiliation",
     *     @OA\Schema(type="integer")
     * )
     * @OA\Parameter(
     *     name="public",
     *     in="query",
     *     description="Show publicly visible scoreboard, even for users with more permissions",
     *     @OA\Schema(type="boolean")
     * )
     * @OA\Parameter(
     *     name="sortorder",
     *     in="query",
     *     description="The sort order to get the scoreboard for. If not given, uses the lowest sortorder",
     *     @OA\Schema(type="integer")
     * )
     * @throws NonUniqueResultException
     */
    public function getScoreboardAction(Request $request): array
    {
        $filter = new Filter();
        if ($request->query->has('category')) {
            $filter->categories = [ $request->query->get('category') ];
        }
        if ($request->query->has('country')) {
            $filter->countries = [ $request->query->get('country') ];
        }
        if ($request->query->has('affiliation')) {
            $filter->affiliations = [ $request->query->get('affiliation') ];
        }
        $allTeams = $request->query->getBoolean('allteams', false);
        $public   = !$this->dj->checkrole('api_reader');
        if ($this->dj->checkrole('api_reader') && $request->query->has('public')) {
            $public = $request->query->getBoolean('public');
        }
        if ($request->query->has('sortorder')) {
            $sortorder = $request->query->getInt('sortorder');
        } else {
            // Get the lowest available sortorder.
            $queryBuilder = $this->em->createQueryBuilder()
                ->from(TeamCategory::class, 'c')
                ->select('MIN(c.sortorder)');
            if ($public) {
                $queryBuilder->andWhere('c.visible = 1');
            }
            $sortorder = (int)$queryBuilder->getQuery()->getSingleScalarResult();
        }

        /** @var Contest $contest */
        // Also checks access of user to the contest via getContestQueryBuilder() from superclass.
        $contest = $this->em->getRepository(Contest::class)->find($this->getContestId($request));

        // Get the event for this scoreboard.
        // TODO: Add support for after_event_id.
        /** @var Event $event */
        $event = $this->em->createQueryBuilder()
            ->from(Event::class, 'e')
            ->select('e')
            ->orderBy('e.eventid', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $scoreboard = $this->scoreboardService->getScoreboard($contest, !$public, $filter, !$allTeams);

        $results = [];
        if ($event) {
            // Build up scoreboard results.
            $results = [
                'event_id' => (string)$event->getEventid(),
                'time' => Utils::absTime($event->getEventtime()),
                'contest_time' => Utils::relTime($event->getEventtime() - $contest->getStarttime()),
                'state' => $contest->getState(),
                'rows' => [],
            ];
        }

        // Return early if there's nothing to display yet.
        if (!$scoreboard) {
            return $results;
        }

        $scoreIsInSeconds = (bool)$this->config->get('score_in_seconds');

        foreach ($scoreboard->getScores() as $teamScore) {
            if ($teamScore->team->getCategory()->getSortorder() !== $sortorder) {
                continue;
            }
            $row = [
                'rank' => $teamScore->rank,
                'team_id' => $teamScore->team->getApiId($this->eventLogService),
                'score' => [
                    'num_solved' => $teamScore->numPoints,
                    'total_time' => $teamScore->totalTime,
                ],
                'problems' => [],
            ];

            foreach ($scoreboard->getMatrix()[$teamScore->team->getTeamid()] as $problemId => $matrixItem) {
                $contestProblem = $scoreboard->getProblems()[$problemId];
                $problem        = [
                    'label' => $contestProblem->getShortname(),
                    'problem_id' => $contestProblem->getApiId($this->eventLogService),
                    'num_judged' => $matrixItem->numSubmissions,
                    'num_pending' => $matrixItem->numSubmissionsPending,
                    'solved' => $matrixItem->isCorrect,
                    'first_to_solve' => $matrixItem->isCorrect && $scoreboard->solvedFirst($teamScore->team, $contestProblem),
                ];

                if ($matrixItem->isCorrect) {
                    $problem['time'] = Utils::scoretime($matrixItem->time, $scoreIsInSeconds);
                }

                $row['problems'][] = $problem;
            }

            usort($row['problems'], fn($a, $b) => $a['label'] <=> $b['label']);

            if ($request->query->getBoolean('strict')) {
                foreach ($row['problems'] as $key => $data) {
                    unset($row['problems'][$key]['label']);
                    unset($row['problems'][$key]['first_to_solve']);
                }
            }

            $results['rows'][] = $row;
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
