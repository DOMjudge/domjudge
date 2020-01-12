<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Language;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Rest\Route("/api/v4/contests/{cid}/languages", defaults={ "_format" = "json" })
 * @Rest\Prefix("/api/contests/{cid}/languages")
 * @Rest\NamePrefix("language_")
 * @SWG\Tag(name="Languages")
 * @SWG\Parameter(ref="#/parameters/cid")
 * @SWG\Response(response="404", ref="#/definitions/NotFound")
 * @SWG\Response(response="401", ref="#/definitions/Unauthorized")
 */
class LanguageController extends AbstractRestController
{
    /**
     * Get all the languages for this contest
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Rest\Get("")
     * @SWG\Response(
     *     response="200",
     *     description="Returns all the languages for this contest",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(ref=@Model(type=Language::class))
     *     )
     * )
     * @SWG\Parameter(ref="#/parameters/idlist")
     * @SWG\Parameter(ref="#/parameters/strict")
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function listAction(Request $request)
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given language for this contest
     * @param Request $request
     * @param string $id
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @Rest\Get("/{id}")
     * @SWG\Response(
     *     response="200",
     *     description="Returns the given language for this contest",
     *     @Model(type=Language::class)
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
        // Make sure the contest exists by calling getContestId. Most API endpoints use the contest to filter its
        // queries, but the languages endpoint does not. So we just call it here
        $this->getContestId($request);
        return $this->em->createQueryBuilder()
            ->from(Language::class, 'lang')
            ->select('lang')
            ->andWhere('lang.allowSubmit = 1');
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    protected function getIdField(): string
    {
        return sprintf('lang.%s', $this->eventLogService->externalIdFieldForEntity(Language::class) ?? 'langid');
    }
}
