<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Clarification;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Problem;
use App\Entity\Team;
use App\Utils\Utils;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @Rest\Route("/contests/{cid}/clarifications")
 * @OA\Tag(name="Clarifications")
 * @OA\Parameter(ref="#/components/parameters/cid")
 * @OA\Response(response="400", ref="#/components/responses/InvalidResponse")
 * @OA\Response(response="401", ref="#/components/responses/Unauthenticated")
 * @OA\Response(response="403", ref="#/components/responses/Unauthorized")
 * @OA\Response(response="404", ref="#/components/responses/NotFound")
 */
class ClarificationController extends AbstractRestController
{
    /**
     * Get all the clarifications for this contest.
     *
     * Note that we restrict the returned clarifications in the query builder.
     * @Rest\Get("")
     * @OA\Response(
     *     response="200",
     *     description="Returns all the clarifications for this contest",
     *     @OA\JsonContent(
     *         type="array",
     *         @OA\Items(ref=@Model(type=Clarification::class))
     *     )
     * )
     * @OA\Parameter(ref="#/components/parameters/idlist")
     * @OA\Parameter(ref="#/components/parameters/strict")
     * @OA\Parameter(
     *     name="problem",
     *     in="query",
     *     description="Only show clarifications for the given problem",
     *     @OA\Schema(type="string")
     * )
     * @throws NonUniqueResultException
     */
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
     * @Rest\Get("/{id}")
     * @OA\Response(
     *     response="200",
     *     description="Returns the given clarification for this contest",
     *     @Model(type=Clarification::class)
     * )
     * @OA\Parameter(ref="#/components/parameters/id")
     * @OA\Parameter(ref="#/components/parameters/strict")
     */
    public function singleAction(Request $request, string $id): Response
    {
        return parent::performSingleAction($request, $id);
    }

    /**
     * Add a clarification to this contest
     * @Rest\Post("")
     * @Rest\Put("/{id}")
     * @Security("is_granted('ROLE_TEAM') or is_granted('ROLE_API_WRITER')", message="You need to have the Team Member role to add a clarification")
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(ref="#/components/schemas/ClarificationPost")
     *     ),
     *     @OA\MediaType(
     *         mediaType="application/json",
     *         @OA\Schema(ref="#/components/schemas/ClarificationPost")
     *     )
     * )
     * @OA\Response(
     *     response="200",
     *     description="When creating a clarification was successful",
     *     @Model(type=Clarification::class)
     * )
     * @throws NonUniqueResultException
     */
    public function addAction(Request $request, ?string $id): Response
    {
        $required = ['text'];
        foreach ($required as $argument) {
            if (!$request->request->has($argument)) {
                throw new BadRequestHttpException(sprintf("Argument '%s' is mandatory.", $argument));
            }
        }

        $contestId = $this->getContestId($request);
        $contest   = $this->em->getRepository(Contest::class)->find($contestId);

        $clarification = new Clarification();
        $clarification
            ->setContest($contest)
            ->setBody($request->request->get('text'));

        if ($problemId = $request->request->get('problem_id')) {
            // Load the problem
            /** @var ContestProblem $problem */
            $problem = $this->em->createQueryBuilder()
                ->from(ContestProblem::class, 'cp')
                ->join('cp.problem', 'p')
                ->join('cp.contest', 'c')
                ->select('cp, c')
                ->andWhere(sprintf('p.%s = :problem',
                    $this->eventLogService->externalIdFieldForEntity(Problem::class) ?? 'probid'))
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

        if ($replyToId = $request->request->get('reply_to_id')) {
            // Load the clarification.
            /** @var Clarification $replyTo */
            $replyTo = $this->em->createQueryBuilder()
                ->from(Clarification::class, 'c')
                ->select('c')
                ->andWhere(sprintf('c.%s = :clarification',
                    $this->eventLogService->externalIdFieldForEntity(Clarification::class) ?? 'clarid'))
                ->andWhere('c.contest = :contest')
                ->setParameter('clarification', $replyToId)
                ->setParameter('contest', $contestId)
                ->getQuery()
                ->getOneOrNullResult();

            if ($replyTo === null) {
                throw new BadRequestHttpException(
                    sprintf("Clarification '%s' not found.", $replyToId));
            }

            $clarification->setInReplyTo($replyTo);
        }

        // By default, use the team of the user
        $fromTeam = $this->isGranted('ROLE_API_WRITER') ? null : $this->dj->getUser()->getTeam();
        if ($fromTeamId = $request->request->get('from_team_id')) {
            $idField = $this->eventLogService->externalIdFieldForEntity(Team::class) ?? 'teamid';
            $method  = sprintf('get%s', ucfirst($idField));

            // If the user is an admin or API writer, allow it to specify the team
            if ($this->isGranted('ROLE_API_WRITER')) {
                $fromTeam = $this->dj->loadTeam($idField, $fromTeamId, $contest);
            } elseif (!$fromTeam) {
                throw new BadRequestHttpException('User does not belong to a team.');
            } elseif ((string)call_user_func([$fromTeam, $method]) !== (string)$fromTeamId) {
                throw new BadRequestHttpException('Can not create a clarification from a different team.');
            }
        } elseif (!$this->isGranted('ROLE_API_WRITER') && !$fromTeam) {
            throw new BadRequestHttpException('User does not belong to a team.');
        }

        $clarification->setSender($fromTeam);

        // By default, send to jury.
        $toTeam = null;
        if ($toTeamId = $request->request->get('to_team_id')) {
            $idField = $this->eventLogService->externalIdFieldForEntity(Team::class) ?? 'teamid';

            // If the user is an admin or API writer, allow it to specify the team.
            if ($this->isGranted('ROLE_API_WRITER')) {
                $toTeam = $this->dj->loadTeam($idField, $toTeamId, $contest);
            } else {
                throw new BadRequestHttpException('Can not create a clarification that is sent to a team.');
            }
        }

        $clarification->setRecipient($toTeam);

        if ($toTeam && $fromTeam) {
            throw new BadRequestHttpException('Can not send a clarification from and to a team.');
        }

        $time = Utils::now();
        if ($timeString = $request->request->get('time')) {
            if ($this->isGranted('ROLE_API_WRITER')) {
                try {
                    $time = Utils::toEpochFloat($timeString);
                } catch (Exception $e) {
                    throw new BadRequestHttpException(sprintf("Can not parse time '%s'.", $timeString));
                }
            } else {
                throw new BadRequestHttpException('A team can not assign time.');
            }
        }

        $clarification->setSubmittime($time);

        if ($clarificationId = $request->request->get('id')) {
            if ($request->isMethod('POST')) {
                throw new BadRequestHttpException('Passing an ID is not supported for POST.');
            } elseif ($id !== $clarificationId) {
                throw new BadRequestHttpException('ID does not match URI.');
            } elseif ($this->isGranted('ROLE_API_WRITER')) {
                // Check if we already have a clarification with this ID
                $existingClarification = $this->em->createQueryBuilder()
                    ->from(Clarification::class, 'c')
                    ->select('c')
                    ->andWhere('(c.externalid IS NULL AND c.clarid = :clarid) OR c.externalid = :clarid')
                    ->andWhere('c.contest = :contest')
                    ->setParameter('clarid', $clarificationId)
                    ->setParameter('contest', $contestId)
                    ->getQuery()
                    ->getOneOrNullResult();
                if ($existingClarification !== null) {
                    throw new BadRequestHttpException(sprintf("Clarification with ID '%s' already exists.", $clarificationId));
                }
            } else {
                throw new BadRequestHttpException('A team can not assign id.');
            }
        }

        $clarification
            ->setExternalid($clarificationId)
            ->setQueue($this->config->get('clar_default_problem_queue'));

        if (!$clarification->getProblem() && $clarificationCategories = $this->config->get('clar_categories')) {
            $clarificationCategoryNames = array_keys($clarificationCategories);
            $clarification->setCategory(reset($clarificationCategoryNames));
        }

        // We are ready to save the clarification.
        $this->em->persist($clarification);
        $this->em->flush();

        $this->dj->auditlog('clarification', $clarification->getClarid(), 'added', null, null, $contestId);
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
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Clarification::class, 'clar')
            ->join('clar.contest', 'c')
            ->leftJoin('clar.in_reply_to', 'reply')
            ->leftJoin('clar.sender', 's')
            ->leftJoin('clar.recipient', 'r')
            ->leftJoin('clar.problem', 'p')
            ->select('clar, c, r, reply, p')
            ->andWhere('clar.contest = :cid')
            ->setParameter('cid', $this->getContestId($request));

        if (!$this->dj->checkrole('api_reader') &&
            !$this->dj->checkrole('judgehost')) {
            if ($this->dj->checkrole('team')) {
                $queryBuilder
                    ->andWhere('clar.sender = :team OR clar.recipient = :team OR (clar.sender IS NULL AND clar.recipient IS NULL)')
                    ->setParameter('team', $this->dj->getUser()->getTeam());
            } else {
                $queryBuilder
                    ->andWhere('clar.sender IS NULL')
                    ->andWhere('clar.recipient IS NULL');
            }
        }

        if ($request->query->has('problem')) {
            $queryBuilder
                ->andWhere('clar.problem = :problem')
                ->setParameter('problem', $request->query->get('problem'));
        }

        return $queryBuilder;
    }

    protected function getIdField(): string
    {
        return sprintf('clar.%s', $this->eventLogService->externalIdFieldForEntity(Clarification::class) ?? 'clarid');
    }
}
