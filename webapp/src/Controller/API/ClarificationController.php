<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Clarification;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Rest\Route("/contests/{cid}/clarifications")
 * @OA\Tag(name="Clarifications")
 * @OA\Parameter(ref="#/components/parameters/cid")
 * @OA\Response(response="404", ref="#/components/schemas/NotFound")
 * @OA\Response(response="401", ref="#/components/schemas/Unauthorized")
 */
class ClarificationController extends AbstractRestController
{
    /**
     * Get all the clarifications for this contest.
     *
     * Note that we restrict the returned clarifications in the query builder.
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Rest\Get("")
     * @OA\Response(
     *     response="200",
     *     description="Returns all the clarifications for this contest",
     *     @OA\Schema(
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
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function listAction(Request $request)
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given clarifications for this contest.
     *
     * Note that we restrict the returned clarifications in the query builder.
     * @param Request $request
     * @param string $id
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @Rest\Get("/{id}")
     * @OA\Response(
     *     response="200",
     *     description="Returns the given clarification for this contest",
     *     @Model(type=Clarification::class)
     * )
     * @OA\Parameter(ref="#/components/parameters/id")
     * @OA\Parameter(ref="#/components/parameters/strict")
     */
    public function singleAction(Request $request, string $id)
    {
        return parent::performSingleAction($request, $id);
    }

    /**
     * @inheritdoc
     */
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
            ->andWhere('clar.cid = :cid')
            ->setParameter(':cid', $this->getContestId($request));

        if (!$this->dj->checkrole('api_reader') &&
            !$this->dj->checkrole('judgehost'))
        {
            if ($this->dj->checkrole('team')) {
                $queryBuilder
                    ->andWhere('clar.sender = :team OR clar.recipient = :team OR (clar.sender IS NULL AND clar.recipient IS NULL)')
                    ->setParameter(':team', $this->dj->getUser()->getTeam());
            } else {
                $queryBuilder
                    ->andWhere('clar.sender IS NULL')
                    ->andWhere('clar.recipient IS NULL');
            }
        }

        if ($request->query->has('problem')) {
            $queryBuilder
                ->andWhere('clar.probid = :problem')
                ->setParameter(':problem', $request->query->get('problem'));
        }

        return $queryBuilder;
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    protected function getIdField(): string
    {
        return sprintf('clar.%s', $this->eventLogService->externalIdFieldForEntity(Clarification::class) ?? 'clarid');
    }
}
