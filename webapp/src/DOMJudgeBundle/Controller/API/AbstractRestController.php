<?php

namespace DOMJudgeBundle\Controller\API;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use DOMJudgeBundle\Service\DOMJudgeService;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class AbstractRestController
 * @package DOMJudgeBundle\Controller\API
 */
abstract class AbstractRestController extends FOSRestController
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var DOMJudgeService
     */
    private $DOMJudgeService;

    /**
     * AbstractRestController constructor.
     * @param EntityManagerInterface $entityManager
     * @param DOMJudgeService $DOMJudgeService
     */
    public function __construct(EntityManagerInterface $entityManager, DOMJudgeService $DOMJudgeService)
    {
        $this->entityManager   = $entityManager;
        $this->DOMJudgeService = $DOMJudgeService;
    }

    /**
     * Get all objects for this endpoint
     * @Rest\Get("")
     * @param Request $request
     * @return Response
     */
    public function listAction(Request $request)
    {
        $queryBuilder = $this->getQueryBuilder($request);
        $objects      = $queryBuilder
            ->getQuery()
            ->getResult();

        return $this->renderData($request, $objects);
    }

    /**
     * Get multiple objects for this endpoint using ID's provided in the body of the request
     * @Rest\Post("")
     * @param Request $request
     * @return Response
     */
    public function getMultipleAction(Request $request)
    {
        $ids = $request->request->get('ids', []);
        if (!is_array($ids) || empty($ids)) {
            throw new BadRequestHttpException('Please provide a field \'ids\' in the body with an array of ID\'s to fetch');
        }

        $queryBuilder = $this->getQueryBuilder($request);
        $objects      = $queryBuilder
            ->where(sprintf('%s IN (:ids)', $this->getIdField()))
            ->setParameter(':ids', $ids)
            ->getQuery()
            ->getResult();

        if (count($objects) !== count($ids)) {
            throw new NotFoundHttpException('One or more objects not found');
        }

        return $this->renderData($request, $objects);
    }

    /**
     * Get a single object for this endpoint
     * @Rest\Get("/{id}")
     * @param Request $request
     * @param string $id
     * @return Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getSingleAction(Request $request, string $id)
    {
        $queryBuilder = $this->getQueryBuilder($request)
            ->where(sprintf('%s = :id', $this->getIdField()))
            ->setParameter(':id', $id)
            ->setMaxResults(1);

        $object = $queryBuilder
            ->getQuery()
            ->getOneOrNullResult();

        if ($object === null) {
            throw new NotFoundHttpException('Object not found');
        }

        return $this->renderData($request, $object);
    }

    /**
     * Render the given data using the correct groups
     * @param Request $request
     * @param mixed $data
     * @return Response
     */
    protected function renderData(Request $request, $data): Response
    {
        $view = $this->view($data);

        // Set the user on the context, so it can be used to determine access to certain attributes
        $view->getContext()->setAttribute('user', $this->DOMJudgeService->getUser());

        $groups = ['Default'];
        if (!$request->query->has('strict')) {
            $groups[] = 'Nonstrict';
        }
        $view->getContext()->setGroups($groups);

        return $this->handleView($view);
    }

    /**
     * Get the query builder to use for request for this REST endpoint
     * @param Request $request
     * @return QueryBuilder
     */
    abstract protected function getQueryBuilder(Request $request): QueryBuilder;

    /**
     * Return the field used as ID in requests
     * @return string
     */
    abstract protected function getIdField(): string;
}
