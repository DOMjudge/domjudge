<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\API;

use Doctrine\ORM\QueryBuilder;
use DOMJudgeBundle\Entity\Language;
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
     */
    public function listAction(Request $request)
    {
        return parent::performListAction($request);
    }

    /**
     * Get the languages for this contest with the given ID's
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Rest\Post("")
     * @SWG\Response(
     *     response="200",
     *     description="Returns the languages for this contest with the given ID's",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(ref=@Model(type=Language::class))
     *     )
     * )
     * @SWG\Parameter(ref="#/parameters/idlist")
     */
    public function getMultipleAction(Request $request)
    {
        return parent::performGetMultipleAction($request);
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
     */
    public function getSingleAction(Request $request, string $id)
    {
        return parent::performGetSingleAction($request, $id);
    }

    /**
     * @inheritdoc
     */
    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Language', 'lang')
            ->select('lang')
            ->where('lang.allow_submit = 1');
    }

    /**
     * @inheritdoc
     */
    protected function getIdField(): string
    {
        return 'lang.externalid';
    }
}
