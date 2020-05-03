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
use App\Utils\Scoreboard\ScoreboardMatrixItem;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Intl\Exception\NotImplementedException;

/**
 * @Rest\Route("/api/v4/contests/{cid}/scoreboard", defaults={ "_format" = "json" })
 * @Rest\Prefix("/api/contests/{cid}/scoreboard")
 * @Rest\NamePrefix("scoreboard_")
 * @SWG\Tag(name="Scoreboard")
 * @SWG\Parameter(ref="#/parameters/cid")
 * @SWG\Response(response="404", ref="#/definitions/NotFound")
 * @SWG\Response(response="401", ref="#/definitions/Unauthorized")
 */
class ScoreboardController extends AbstractRestController
{
    /**
     * @var ScoreboardService
     */
    protected $scoreboardService;

    /**
     * ScoreboardController constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param DOMJudgeService        $DOMJudgeService
     * @param ConfigurationService   $config
     * @param EventLogService        $eventLogService
     * @param ScoreboardService      $scoreboardService
     */
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
     * Get the scoreboard for this contest
     * @param Request $request
     * @return array
     * @Rest\Get("")
     * @SWG\Response(
     *     response="200",
     *     description="Returns the scoreboard",
     *     @SWG\Schema(ref="#/definitions/Scoreboard")
     * )
     * @SWG\Parameter(
     *     name="allteams",
     *     in="query",
     *     type="boolean",
     *     description="Also show invisble teams. Requires jury privileges"
     * )
     * @SWG\Parameter(
     *     name="category",
     *     in="query",
     *     type="integer",
     *     description="Get the scoreboard for only this category"
     * )
     * @SWG\Parameter(
     *     name="country",
     *     in="query",
     *     type="string",
     *     description="Get the scoreboard for only this country (in ISO 3166-1 alpha-3 format)"
     * )
     * @SWG\Parameter(
     *     name="affiliation",
     *     in="query",
     *     type="integer",
     *     description="Get the scoreboard for only this affiliation"
     * )
     * @SWG\Parameter(
     *     name="public",
     *     in="query",
     *     type="boolean",
     *     description="Show publicly visible scoreboard, even for users with more permissions"
     * )
     * @SWG\Parameter(
     *     name="sortorder",
     *     in="query",
     *     type="integer",
     *     description="The sort order to get the scoreboard for. If not given, uses the lowest sortorder"
     * )
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function getScoreboardAction(Request $request)
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
            // Get the lowest available sortorder
            $queryBuilder = $this->em->createQueryBuilder()
                ->from(TeamCategory::class, 'c')
                ->select('MIN(c.sortorder)');
            if ($public) {
                $queryBuilder->andWhere('c.visible = 1');
            }
            $sortorder = (int)$queryBuilder->getQuery()->getSingleScalarResult();
        }

        /** @var Contest $contest */
        $contest         = $this->em->getRepository(Contest::class)->find($this->getContestId($request));
        $inactiveAllowed = $this->isGranted('ROLE_API_READER');
        $accessAllowed   = ($inactiveAllowed && $contest->getEnabled()) || (!$inactiveAllowed && $contest->isActive());
        if (!$accessAllowed) {
            throw new AccessDeniedHttpException();
        }

        // Get the event for this scoreboard
        // TODO: add support for after_event_id
        /** @var Event $event */
        $event = $this->em->createQueryBuilder()
            ->from(Event::class, 'e')
            ->select('e')
            ->orderBy('e.eventid', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $scoreboard = $this->scoreboardService->getScoreboard($contest, !$public, $filter, !$allTeams);

        // Build up scoreboard results
        $results = [
            'event_id' => (string)$event->getEventid(),
            'time' => Utils::absTime($event->getEventtime()),
            'contest_time' => Utils::relTime($event->getEventtime() - $contest->getStarttime()),
            'state' => $contest->getState(),
            'rows' => [],
        ];

        // Return early if there's nothing to display yet.
        if (!$scoreboard) return $results;

        $scoreIsInSeconds = (bool)$this->config->get('score_in_seconds');

        foreach ($scoreboard->getScores() as $teamScore) {
            if ($teamScore->team->getCategory()->getSortorder() !== $sortorder) {
                continue;
            }
            $row = [
                'rank' => $teamScore->rank,
                'team_id' => (string)$teamScore->team->getApiId($this->eventLogService),
                'score' => [
                    'num_solved' => $teamScore->numPoints,
                    'total_time' => $teamScore->totalTime,
                ],
                'problems' => [],
            ];

            /** @var ScoreboardMatrixItem $matrixItem */
            foreach ($scoreboard->getMatrix()[$teamScore->team->getTeamid()] as $problemId => $matrixItem) {
                $contestProblem = $scoreboard->getProblems()[$problemId];
                $problem        = [
                    'label' => $contestProblem->getShortname(),
                    'problem_id' => (string)$contestProblem->getApiId($this->eventLogService),
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

            usort($row['problems'], function ($a, $b) {
                return $a['label'] <=> $b['label'];
            });

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

    /**
     * @inheritdoc
     */
    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritdoc
     */
    protected function getIdField(): string
    {
        throw new NotImplementedException();
    }
}
