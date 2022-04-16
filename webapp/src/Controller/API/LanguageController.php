<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Language;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Rest\Route("/contests/{cid}/languages")
 * @OA\Tag(name="Languages")
 * @OA\Parameter(ref="#/components/parameters/cid")
 * @OA\Response(response="404", ref="#/components/responses/NotFound")
 * @OA\Response(response="401", ref="#/components/responses/Unauthorized")
 * @OA\Response(response="400", ref="#/components/responses/InvalidResponse")
 */
class LanguageController extends AbstractRestController
{
    /**
     * Get all the languages for this contest.
     * @Rest\Get("")
     * @OA\Response(
     *     response="200",
     *     description="Returns all the languages for this contest",
     *     @OA\JsonContent(
     *         type="array",
     *         @OA\Items(ref=@Model(type=Language::class))
     *     )
     * )
     * @OA\Parameter(ref="#/components/parameters/idlist")
     * @OA\Parameter(ref="#/components/parameters/strict")
     * @throws NonUniqueResultException
     */
    public function listAction(Request $request): Response
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given language for this contest.
     * @throws NonUniqueResultException
     * @Rest\Get("/{id}")
     * @OA\Response(
     *     response="200",
     *     description="Returns the given language for this contest",
     *     @Model(type=Language::class)
     * )
     * @OA\Parameter(ref="#/components/parameters/id")
     * @OA\Parameter(ref="#/components/parameters/strict")
     */
    public function singleAction(Request $request, string $id): Response
    {
        return parent::performSingleAction($request, $id);
    }

    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        // Make sure the contest exists by calling getContestId. Most API endpoints use the contest to filter its
        // queries, but the languages endpoint does not. So we just call it here.
        $this->getContestId($request);
        return $this->em->createQueryBuilder()
            ->from(Language::class, 'lang')
            ->select('lang')
            ->andWhere('lang.allowSubmit = 1');
    }

    protected function getIdField(): string
    {
        return sprintf('lang.%s', $this->eventLogService->externalIdFieldForEntity(Language::class) ?? 'langid');
    }
}
