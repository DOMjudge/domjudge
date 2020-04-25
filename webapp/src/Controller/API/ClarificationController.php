<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Clarification;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Rest\Route("/api/v4/contests/{cid}/clarifications", defaults={ "_format" = "json" })
 * @Rest\Prefix("/api/contests/{cid}/clarifications")
 * @Rest\NamePrefix("clarification_")
 * @SWG\Tag(name="Clarifications")
 * @SWG\Parameter(ref="#/parameters/cid")
 * @SWG\Response(response="404", ref="#/definitions/NotFound")
 * @SWG\Response(response="401", ref="#/definitions/Unauthorized")
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
     * @SWG\Response(
     *     response="200",
     *     description="Returns all the clarifications for this contest",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(ref=@Model(type=Clarification::class))
     *     )
     * )
     * @SWG\Parameter(ref="#/parameters/idlist")
     * @SWG\Parameter(ref="#/parameters/strict")
     * @SWG\Parameter(
     *     name="problem",
     *     in="query",
     *     type="string",
     *     description="Only show clarifications for the given problem"
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
     * @SWG\Response(
     *     response="200",
     *     description="Returns the given clarification for this contest",
     *     @Model(type=Clarification::class)
     * )
     * @SWG\Parameter(ref="#/parameters/id")
     * @SWG\Parameter(ref="#/parameters/strict")
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
