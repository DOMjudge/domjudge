<?php declare(strict_types=1);

namespace App\Controller\API;

use App\DataTransferObject\ClarificationPost;
use App\Entity\Clarification;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Team;
use App\Service\AuthorizedUserService;
use App\Service\ClarificationService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractRestController<Clarification, Clarification>
 */
#[Rest\Route(path: '/contests/{cid}/clarifications')]
#[OA\Tag(name: 'Clarifications')]
#[OA\Parameter(ref: '#/components/parameters/cid')]
#[OA\Parameter(ref: '#/components/parameters/strict')]
#[OA\Response(ref: '#/components/responses/InvalidResponse', response: 400)]
#[OA\Response(ref: '#/components/responses/Unauthenticated', response: 401)]
#[OA\Response(ref: '#/components/responses/Unauthorized', response: 403)]
#[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
class ClarificationController extends AbstractRestController
{
    public function __construct(
        AuthorizedUserService $authService,
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService,
        protected readonly ClarificationService $clarificationService,
    ) {
        parent::__construct($authService, $em, $dj, $config, $eventLogService);
    }

    /**
     * Get all the clarifications for this contest.
     *
     * Note that we restrict the returned clarifications in the query builder.
     * @throws NonUniqueResultException
     */
    #[Rest\Get(path: '')]
    #[OA\Response(
        response: 200,
        description: 'Returns all the clarifications for this contest',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Clarification::class))
        )
    )]
    #[OA\Parameter(ref: '#/components/parameters/idlist')]
    #[OA\Parameter(
        name: 'problem',
        description: 'Only show clarifications for the given problem',
        in: 'query',
        schema: new OA\Schema(type: 'string')
    )]
    public function listAction(Request $request): Response
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given clarifications for this contest.
     *
     * Note that we restrict the returned clarifications based on the user's role.
     * Admin and api_reader get everything, anonymous gets only general clarifications,
     * team user gets general clarifications plus those sent from or to the team.
     * @throws NonUniqueResultException
     */
    #[Rest\Get(path: '/{id}')]
    #[OA\Response(
        response: 200,
        description: 'Returns the given clarification for this contest',
        content: new Model(type: Clarification::class)
    )]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function singleAction(Request $request, string $id): Response
    {
        return parent::performSingleAction($request, $id);
    }

    /**
     * Add a clarification to this contest
     * @throws NonUniqueResultException
     */
    #[IsGranted(
        new Expression("is_granted('ROLE_TEAM') or is_granted('ROLE_API_WRITER')"),
        message: 'You need to have the Team Member role to add a clarification'
    )]
    #[Rest\Post(path: '')]
    #[Rest\Put(path: '/{id}')]
    #[OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(ref: new Model(type: ClarificationPost::class))
            ),
            new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(ref: new Model(type: ClarificationPost::class))
            )
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'When creating a clarification was successful',
        content: new Model(type: Clarification::class)
    )]
    public function addAction(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ClarificationPost $clarificationPost,
        Request $request,
        ?string $id
    ): Response {
        $maxLength = $this->clarificationService->getClarificationMaximumBodyLength();
        if ($maxLength > 0 && mb_strlen($clarificationPost->text) > $maxLength) {
            throw new BadRequestHttpException(
                sprintf('Clarification body is too long: %d characters, maximum is %d.', mb_strlen($clarificationPost->text), $maxLength)
            );
        }

        $contestId = $this->getContestId($request);
        $contest   = $this->em->getRepository(Contest::class)->find($contestId);

        $clarification = new Clarification();
        $clarification
            ->setContest($contest)
            ->setBody($clarificationPost->text);

        if ($problemId = $clarificationPost->problemId) {
            // Load the problem
            /** @var ContestProblem|null $problem */
            $problem = $this->em->createQueryBuilder()
                ->from(ContestProblem::class, 'cp')
                ->join('cp.problem', 'p')
                ->join('cp.contest', 'c')
                ->select('cp, c')
                ->andWhere('p.externalid = :problem')
                ->andWhere('cp.contest = :contest')
                ->andWhere('cp.allowSubmit = 1')
                ->setParameter('problem', $problemId)
                ->setParameter('contest', $contestId)
                ->getQuery()
                ->getOneOrNullResult();

            if ($problem === null) {
                throw new BadRequestHttpException(
                    sprintf("Problem '%s' not found.", $problemId));
            }

            $clarification->setProblem($problem->getProblem());
        }

        // By default, use the team of the user
        $fromTeam = $this->isGranted('ROLE_API_WRITER') ? null : $this->authService->getUser()->getTeam();
        if ($fromTeamId = $clarificationPost->fromTeamId) {
            // If the user is an admin or API writer, allow it to specify the team
            if ($this->isGranted('ROLE_API_WRITER')) {
                $fromTeam = $this->dj->loadTeam($fromTeamId, $contest);
            } elseif (!$fromTeam) {
                throw new BadRequestHttpException('User does not belong to a team.');
            } elseif ($fromTeam->getExternalid() !== $fromTeamId) {
                throw new BadRequestHttpException('Can not create a clarification from a different team.');
            }
        } elseif (!$this->isGranted('ROLE_API_WRITER') && !$fromTeam) {
            throw new BadRequestHttpException('User does not belong to a team.');
        }

        $clarification->setSender($fromTeam);

        // Resolve the recipient team id. CCS 2026-01+ uses to_team_ids (array, at most 1 entry);
        // older versions use a single to_team_id. Prefer to_team_ids only when it carries a value,
        // because some cross-version clients send both fields with an empty to_team_ids array.
        if ($clarificationPost->toTeamIds !== null && count($clarificationPost->toTeamIds) > 1) {
            throw new BadRequestHttpException('Only a single recipient team is supported.');
        }
        $toTeamId = $clarificationPost->toTeamIds[0]
            ?? $clarificationPost->toTeamId;

        // By default, send to jury.
        $toTeam = null;
        if ($toTeamId) {
            // If the user is an admin or API writer, allow it to specify the team.
            if ($this->isGranted('ROLE_API_WRITER')) {
                $toTeam = $this->dj->loadTeam($toTeamId, $contest);
            } else {
                throw new BadRequestHttpException('Can not create a clarification that is sent to a team.');
            }
        }

        $clarification->setRecipient($toTeam);

        if ($toTeam && $fromTeam) {
            throw new BadRequestHttpException('Can not send a clarification from and to a team.');
        }

        if ($replyToId = $clarificationPost->replyToId) {
            $qb = $this->clarificationService->getQueryBuilder(internalContestId: $contestId, externalClarificationId: $replyToId)
                ->select('clar');

            // Load the clarification.
            /** @var Clarification|null $replyTo */
            $replyTo = $qb->getQuery()->getOneOrNullResult();

            if ($replyTo === null) {
                throw new BadRequestHttpException("Clarification '$replyToId' not found.");
            }

            $clarification->setInReplyTo($replyTo);
        }

        $time = Utils::now();
        if ($contest->getStartTime() > $time) {
            throw new BadRequestHttpException(
                "Sending clarifications via API before the contest is not allowed."
            );
        }
        if ($timeString = $clarificationPost->time) {
            if ($this->isGranted('ROLE_API_WRITER')) {
                try {
                    $time = Utils::toEpochFloat($timeString);
                } catch (Exception) {
                    throw new BadRequestHttpException(sprintf("Can not parse time '%s'.", $timeString));
                }
            } else {
                throw new BadRequestHttpException('A team can not assign time.');
            }
        }

        $clarification->setSubmittime($time);

        if ($clarificationId = $clarificationPost->id) {
            if ($request->isMethod('POST')) {
                throw new BadRequestHttpException('Passing an ID is not supported for POST.');
            } elseif ($id !== $clarificationId) {
                throw new BadRequestHttpException('ID does not match URI.');
            } elseif ($this->isGranted('ROLE_API_WRITER')) {
                // Check if we already have a clarification with this ID
                // Deliberately not the ClarificationService query builder: this
                // asks whether the id is taken, not whether the current user may
                // see the clarification holding it.
                $existingClarification = $this->em->getRepository(Clarification::class)
                    ->findOneBy(['contest' => $contestId, 'externalid' => $clarificationId]);
                if ($existingClarification !== null) {
                    throw new BadRequestHttpException(sprintf("Clarification with ID '%s' already exists.", $clarificationId));
                }
            } else {
                throw new BadRequestHttpException('A team can not assign id.');
            }
        }

        $clarification
            ->setExternalid($clarificationId)
            ->setQueue($this->clarificationService->getClarificationDefaultProblemQueue());

        if (!$clarification->getProblem() && $clarificationCategories = $this->clarificationService->getClarificationCategories()) {
            $clarificationCategoryNames = array_keys($clarificationCategories);
            $clarification->setCategory(reset($clarificationCategoryNames));
        }

        // We are ready to save the clarification.
        $this->em->persist($clarification);
        $this->em->flush();

        $this->dj->auditlog('clarification', $clarification->getExternalid(), 'added', null, null, $contest->getExternalid());
        $this->eventLogService->log('clarification', $clarification->getClarid(), 'create', $contestId);

        // Refresh the clarification since the event log service will have unloaded it.
        $clarification = $this->em->getRepository(Clarification::class)->find($clarification->getClarid());

        if ($clarification->getRecipient()) {
            $clarification->getRecipient()->addUnreadClarification($clarification);
        } else {
            $teams = $this->em->getRepository(Team::class)->findAll();
            foreach ($teams as $team) {
                $team->addUnreadClarification($clarification);
            }
        }
        $this->em->flush();

        return $this->renderData($request, $clarification);
    }

    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        $contestId = $this->getContestId($request);
        $problem = $request->query->get('problem');
        return $this->clarificationService->getQueryBuilder(internalContestId: $contestId, problem: $problem)
            ->select('clar, c, r, reply, p')
            ->orderBy('clar.clarid');
    }

    protected function getIdField(): string
    {
        return 'clar.externalid';
    }
}
