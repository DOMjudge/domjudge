<?php declare(strict_types=1);

namespace App\Controller\API;

use App\DataTransferObject\Scoreboard\Problem;
use App\DataTransferObject\Scoreboard\Row;
use App\DataTransferObject\Scoreboard\Score;
use App\DataTransferObject\Scoreboard\Scoreboard;
use App\Entity\Contest;
use App\Entity\Event;
use App\Entity\TeamCategory;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ScoreboardService;
use App\Utils\CcsApiVersion;
use App\Utils\Scoreboard\Filter;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[Rest\Route('/contests/{cid}/scoreboard')]
#[OA\Tag(name: 'Scoreboard')]
#[OA\Parameter(ref: '#/components/parameters/cid')]
#[OA\Parameter(ref: '#/components/parameters/strict')]
#[OA\Response(ref: '#/components/responses/InvalidResponse', response: 400)]
#[OA\Response(ref: '#/components/responses/Unauthenticated', response: 401)]
#[OA\Response(ref: '#/components/responses/Unauthorized', response: 403)]
#[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
class ScoreboardController extends AbstractApiController
{
    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        ConfigurationService $config,
        EventLogService $eventLogService,
        protected readonly ScoreboardService $scoreboardService,
    ) {
        parent::__construct($entityManager, $DOMJudgeService, $config, $eventLogService);
    }

    /**
     * Get the scoreboard for this contest.
     * @throws NonUniqueResultException
     */
    #[Rest\Get('')]
    #[OA\Response(
        response: 200,
        description: 'Returns the scoreboard',
        content: new OA\JsonContent(ref: new Model(type: Scoreboard::class))
    )]
    #[OA\Parameter(
        name: 'allteams',
        description: 'Also show invisible teams. Requires jury privileges',
        in: 'query',
        schema: new OA\Schema(type: 'boolean')
    )]
    #[OA\Parameter(
        name: 'category',
        description: 'Get the scoreboard for only this category',
        in: 'query',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'country',
        description: 'Get the scoreboard for only this country (in ISO 3166-1 alpha-3 format)',
        in: 'query',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'affiliation',
        description: 'Get the scoreboard for only this affiliation',
        in: 'query',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'public',
        description: 'Show publicly visible scoreboard, even for users with more permissions',
        in: 'query',
        schema: new OA\Schema(type: 'boolean')
    )]
    #[OA\Parameter(
        name: 'sortorder',
        description: 'The sort order to get the scoreboard for. If not given, uses the lowest sortorder',
        in: 'query',
        schema: new OA\Schema(type: 'integer')
    )]
    public function getScoreboardAction(
        Request $request,
        #[MapQueryParameter]
        ?int $category = null,
        #[MapQueryParameter]
        ?string $country = null,
        #[MapQueryParameter]
        ?int $affiliation = null,
        #[MapQueryParameter(name: 'allteams')]
        bool $allTeams = false,
        #[MapQueryParameter(name: 'public')]
        ?bool $publicInRequest = null,
        #[MapQueryParameter]
        ?int $sortorder = null,
        #[MapQueryParameter]
        bool $strict = false,
    ): Scoreboard {
        if (!$this->config->get('enable_ranking') && !$this->dj->checkrole('jury')) {
            throw new BadRequestHttpException('Scoreboard is not available.');
        }

        $filter = new Filter();
        if ($category) {
            $filter->categories = [$category];
        }
        if ($country) {
            $filter->countries = [$country];
        }
        if ($affiliation) {
            $filter->affiliations = [$affiliation];
        }
        $public   = !$this->dj->checkrole('api_reader');
        if ($this->dj->checkrole('api_reader') && $publicInRequest !== null) {
            $public = $publicInRequest;
        }
        if ($sortorder === null) {
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
        /** @var Event|null $event */
        $event = $this->em->createQueryBuilder()
            ->from(Event::class, 'e')
            ->select('e')
            ->orderBy('e.eventid', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $scoreboard = $this->scoreboardService->getScoreboard($contest, !$public, $filter, !$allTeams);

        $results = new Scoreboard();
        if ($event) {
            // Build up scoreboard results.
            $results = new Scoreboard(
                eventId: (string)$event->getEventid(),
                time: Utils::absTime($event->getEventtime()),
                contestTime: Utils::relTime($event->getEventtime() - $contest->getStarttime()),
                state: $contest->getState(),
            );
        }

        // Return early if there's nothing to display yet.
        if (!$scoreboard) {
            return $results;
        }

        $scoreIsInSeconds = (bool)$this->config->get('score_in_seconds');
        /** @var CcsApiVersion $ccsApiVersion */
        $ccsApiVersion = $this->config->get('ccs_api_version');

        foreach ($scoreboard->getScores() as $teamScore) {
            if ($teamScore->team->getCategory()->getSortorder() !== $sortorder) {
                continue;
            }

            if ($contest->getRuntimeAsScoreTiebreaker()) {
                $score = new Score(
                    numSolved: $teamScore->numPoints,
                    totalRuntime: $teamScore->totalRuntime,
                );
            } else {
                $score = new Score(
                    numSolved: $teamScore->numPoints,
                    totalTime: $this->formatTime($teamScore->totalTime, $ccsApiVersion, $scoreIsInSeconds),
                );
            }

            $lastProblemTime = null;

            $problems = [];
            foreach ($scoreboard->getMatrix()[$teamScore->team->getTeamid()] as $problemId => $matrixItem) {
                $contestProblem = $scoreboard->getProblems()[$problemId];
                $problem = new Problem(
                    label: $contestProblem->getShortname(),
                    problemId: $contestProblem->getProblem()->getExternalid(),
                    numJudged: $matrixItem->numSubmissions,
                    numPending: $matrixItem->numSubmissionsPending,
                    solved: $matrixItem->isCorrect,
                );

                if ($contest->getRuntimeAsScoreTiebreaker()) {
                    $problem->fastestSubmission = $matrixItem->isCorrect && $scoreboard->isFastestSubmission($teamScore->team, $contestProblem);
                    if ($matrixItem->isCorrect) {
                        $problem->runtime = $matrixItem->runtime;
                    }
                } else {
                    $problem->firstToSolve = $matrixItem->isCorrect && $scoreboard->solvedFirst($teamScore->team, $contestProblem);
                    if ($matrixItem->isCorrect) {
                        $problemTime = Utils::scoretime($matrixItem->time, $scoreIsInSeconds);
                        $problem->time = $this->formatTime($problemTime, $ccsApiVersion, $scoreIsInSeconds);
                        $lastProblemTime = max($lastProblemTime, $problemTime);
                    }
                }

                $problems[] = $problem;
            }

            if ($lastProblemTime !== null) {
                $score->time = $this->formatTime($lastProblemTime, $ccsApiVersion, $scoreIsInSeconds);
            }

            usort($problems, fn(Problem $a, Problem $b) => $a->label <=> $b->label);

            $row = new Row(
                rank: $teamScore->rank,
                teamId: $teamScore->team->getExternalid(),
                score: $score,
                problems: $problems,
            );

            $results->rows[] = $row;
        }

        return $results;
    }

    protected function formatTime(
        int $time,
        CcsApiVersion $ccsApiVersion,
        bool $scoreIsInSeconds
    ): int|string {
        if ($ccsApiVersion->useRelTimes()) {
            return $scoreIsInSeconds ? Utils::relTime($time) : Utils::relTime($time * 60);
        } else {
            return $time;
        }
    }
}
