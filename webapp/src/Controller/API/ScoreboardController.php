<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Contest;
use App\Entity\Event;
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
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function getScoreboardAction(Request $request)
    {
        $filter = new Filter();
        if ($request->query->has('category')) {
            $filter->setCategories([$request->query->get('category')]);
        }
        if ($request->query->has('country')) {
            $filter->setCountries([$request->query->get('country')]);
        }
        if ($request->query->has('affiliation')) {
            $filter->setAffiliations([$request->query->get('affiliation')]);
        }
        $allTeams = $request->query->getBoolean('allteams', false);
        $public   = !$this->dj->checkrole('api_reader');
        if ($this->dj->checkrole('api_reader') && $request->query->has('public')) {
            $public = $request->query->getBoolean('public');
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

        $scoreIsInSeconds = (bool)$this->dj->dbconfig_get('score_in_seconds', false);

        foreach ($scoreboard->getScores() as $teamScore) {
            $row = [
                'rank' => $teamScore->getRank(),
                'team_id' => (string)$teamScore->getTeam()->getApiId($this->eventLogService),
                'score' => [
                    'num_solved' => $teamScore->getNumberOfPoints(),
                    'total_time' => $teamScore->getTotalTime(),
                ],
                'problems' => [],
            ];

            /** @var ScoreboardMatrixItem $matrixItem */
            foreach ($scoreboard->getMatrix()[$teamScore->getTeam()->getTeamid()] as $problemId => $matrixItem) {
                $contestProblem = $scoreboard->getProblems()[$problemId];
                $problem        = [
                    'label' => $contestProblem->getShortname(),
                    'problem_id' => (string)$contestProblem->getApiId($this->eventLogService),
                    'num_judged' => $matrixItem->getNumberOfSubmissions(),
                    'num_pending' => $matrixItem->getNumberOfPendingSubmissions(),
                    'solved' => $matrixItem->isCorrect(),
                    'first_to_solve' => $matrixItem->isCorrect() && $scoreboard->solvedFirst($teamScore->getTeam(), $contestProblem),
                ];

                if ($matrixItem->isCorrect()) {
                    $problem['time'] = Utils::scoretime($matrixItem->getTime(), $scoreIsInSeconds);
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
        // Not used for scoreboard endpoint
        return null;
    }

    /**
     * @inheritdoc
     */
    protected function getIdField(): string
    {
        // Not used for scoreboard endpoint
        return '';
    }
}
