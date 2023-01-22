<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Contest;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use Exception;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Class AbstractRestController
 * @package App\Controller\API
 */
abstract class AbstractRestController extends AbstractFOSRestController
{
    protected EntityManagerInterface $em;
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected EventLogService $eventLogService;

    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService
    ) {
        $this->em              = $entityManager;
        $this->dj              = $dj;
        $this->eventLogService = $eventLogService;
        $this->config          = $config;
    }

    /**
     * Get all objects for this endpoint.
     * @throws NonUniqueResultException
     */
    protected function performListAction(Request $request): Response
    {
        return $this->renderData($request, $this->listActionHelper($request));
    }

    /**
     * Get a single object for this endpoint.
     * @throws NonUniqueResultException
     */
    protected function performSingleAction(Request $request, string $id): Response
    {
        // Make sure we clear the entity manager class, for when this method is called multiple times
        // by internal requests.
        $this->em->clear();

        // Special case for submissions and clarifications: they can have an external ID even if when running in
        // full local mode, because one can use the API to upload one with an external ID.
        $externalIdAlwaysAllowed = [
            's.submitid',
            'clar.clarid',
        ];
        $idField = $this->getIdField();
        if (in_array($idField, $externalIdAlwaysAllowed)) {
            $table        = explode('.', $idField)[0];
            $queryBuilder = $this->getQueryBuilder($request)
                ->andWhere(sprintf('(%s.externalid IS NULL AND %s = :id) OR %s.externalid = :id', $table, $idField, $table))
                ->setParameter('id', $id);
        } else {
            $queryBuilder = $this->getQueryBuilder($request)
                ->andWhere(sprintf('%s = :id', $idField))
                ->setParameter('id', $id);
        }

        $object = $queryBuilder
            ->getQuery()
            ->getOneOrNullResult();

        if ($object === null) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $id));
        }

        if ($this instanceof QueryObjectTransformer) {
            $object = $this->transformObject($object);
        }

        return $this->renderData($request, $object);
    }

    /**
     * Render the given data using the correct groups.
     *
     * @param mixed    $data
     * @param string[] $extraheaders
     */
    protected function renderData(
        Request $request,
        $data,
        int $statusCode = Response::HTTP_OK,
        array $extraheaders = []
    ): Response {
        $view = $this->view($data);

        // Set the DOMjudge service on the context, so we can use it for permissions.
        $view->getContext()->setAttribute('domjudge_service', $this->dj);
        $view->getContext()->setAttribute('config_service', $this->config);

        $groups = ['Default'];
        if (!$request->query->has('strict') || !$request->query->getBoolean('strict')) {
            $groups[] = 'Nonstrict';
        }
        if ($this->dj->checkrole('api_reader')) {
            $groups[] = 'Restricted';
        }
        if (in_array('Nonstrict', $groups) && in_array('Restricted', $groups)) {
            $groups[] = 'RestrictedNonstrict';
        }
        $view->getContext()->setGroups($groups);

        $response = $this->handleView($view);
        $response->setStatusCode($statusCode);
        $response->headers->add($extraheaders);
        return $response;
    }

    /**
     * Render the given create data using the correct groups.
     *
     * @param mixed      $data
     * @param string|int $id
     */
    protected function renderCreateData(
        Request $request,
        $data,
        string $routeType,
        $id
    ): Response {
        $params = [
            'id' => $id,
        ];
        if ($routeType !== 'user') {
            $params['cid'] = $request->attributes->get('cid');
        }
        $headers = [
            'Location' => $this->generateUrl("v4_app_api_{$routeType}_single", $params, UrlGeneratorInterface::ABSOLUTE_URL),
        ];
        return $this->renderData($request, $data, Response::HTTP_CREATED,
            $headers);
    }

    /**
     * Get the query builder used for getting contests.
     * @param bool $onlyActive return only contests that are active
     */
    protected function getContestQueryBuilder(bool $onlyActive = false): QueryBuilder
    {
        $now = Utils::now();
        $qb  = $this->em->createQueryBuilder();
        $qb
            ->from(Contest::class, 'c')
            ->select('c')
            ->andWhere('c.enabled = 1')
            ->orderBy('c.activatetime');

        if ($onlyActive || !$this->dj->checkrole('api_reader')) {
            $qb
                ->andWhere('c.activatetime <= :now')
                ->andWhere('c.deactivatetime IS NULL OR c.deactivatetime > :now')
                ->setParameter('now', $now);
        }

        // Filter on contests this user has access to
        if (!$this->dj->checkrole('api_reader') && !$this->dj->checkrole('judgehost')) {
            if ($this->dj->checkrole('team') && $this->dj->getUser()->getTeam()) {
                $qb->leftJoin('c.teams', 'ct')
                    ->leftJoin('c.team_categories', 'tc')
                    ->leftJoin('tc.teams', 'tct')
                    ->andWhere('ct.teamid = :teamid OR tct.teamid = :teamid OR c.openToAllTeams = 1')
                    ->setParameter('teamid', $this->dj->getUser()->getTeam());
            } else {
                $qb->andWhere('c.public = 1');
            }
        }

        return $qb;
    }

    /**
     * @throws NonUniqueResultException
     */
    protected function getContestId(Request $request): int
    {
        if (!$request->attributes->has('cid')) {
            throw new BadRequestHttpException('cid parameter missing');
        }

        $qb = $this->getContestQueryBuilder($request->query->getBoolean('onlyActive', false));
        $qb
            ->andWhere(sprintf('c.%s = :cid', $this->getContestIdField()))
            ->setParameter('cid', $request->attributes->get('cid'));

        /** @var Contest $contest */
        $contest = $qb->getQuery()->getOneOrNullResult();

        if ($contest === null) {
            throw new NotFoundHttpException(sprintf('Contest with ID \'%s\' not found', $request->attributes->get('cid')));
        }

        return $contest->getCid();
    }

    protected function getContestIdField(): string
    {
        try {
            return $this->eventLogService->externalIdFieldForEntity(Contest::class) ?? 'cid';
        } catch (Exception $e) {
            return 'cid';
        }
    }

    /**
     * Get the query builder to use for request for this REST endpoint.
     * @throws NonUniqueResultException
     */
    abstract protected function getQueryBuilder(Request $request): QueryBuilder;

    /**
     * Return the field used as ID in requests.
     */
    abstract protected function getIdField(): string;

    /**
     * @throws NonUniqueResultException
     */
    protected function listActionHelper(Request $request): array
    {
        // Make sure we clear the entity manager class, for when this method is called multiple times
        // by internal requests.
        $this->em->clear();
        $queryBuilder = $this->getQueryBuilder($request);

        if ($request->query->has('ids')) {
            $ids = $request->query->all('ids');

            $ids = array_unique($ids);

            // Special case for submissions and clarifications: they can have an external ID even if when running in
            // full local mode, because one can use the API to upload one with an external ID.
            $externalIdAlwaysAllowed = [
                's.submitid',
                'clar.clarid',
            ];
            $idField = $this->getIdField();
            if (in_array($idField, $externalIdAlwaysAllowed)) {
                $table        = explode('.', $idField)[0];
                $or = $queryBuilder->expr()->orX();
                foreach ($ids as $index => $id) {
                    $or->add(sprintf('(%s.externalid IS NULL AND %s = :id%s) OR %s.externalid = :id%s', $table, $idField, $index, $table, $index));
                    $queryBuilder->setParameter(sprintf('id%s', $index), $id);
                }
                $queryBuilder->andWhere($or);
            } else {
                $queryBuilder
                    ->andWhere(sprintf('%s IN (:ids)', $this->getIdField()))
                    ->setParameter('ids', $ids);
            }
        }

        $objects = $queryBuilder
            ->getQuery()
            ->getResult();

        if (isset($ids) && count($objects) !== count($ids)) {
            throw new NotFoundHttpException('One or more objects not found');
        }

        if ($this instanceof QueryObjectTransformer) {
            $objects = array_map([$this, 'transformObject'], $objects);
        }
        return $objects;
    }

    /**
     * Send a binary file response, sending a 304 if it did not modify since last requested.
     */
    public static function sendBinaryFileResponse(Request $request, string $fileName): BinaryFileResponse
    {
        // Note: we set auto-etag to true to automatically send the ETag based on the file contents.
        // ETags can be used to determine whether the file changed and if it didn't change, the response will
        // be a 304 Not Modified.
        $response = new BinaryFileResponse($fileName, 200, [], true, null, true);
        $contentType = mime_content_type($fileName);
        // Some SVGs do not have an XML header and mime_content_type reports those incorrectly.
        // image/svg+xml is the official mimetype for all SVGs.
        if ($contentType === 'image/svg') {
            $contentType = 'image/svg+xml';
        }
        $response->headers->set('Content-Type', $contentType);

        // Check if we need to send a 304 Not Modified and if so, send it.
        // This is done both on the
        // - ETag / If-None-Match and
        // - Last-Modified / If-Modified-Since
        // header pairs.
        if ($response->isNotModified($request)) {
            $response->send();
        }

        return $response;
    }

    public function responseForErrors(
        ConstraintViolationListInterface $violations,
        bool $singleProperty = false
    ): ?JsonResponse {
        if ($violations->count()) {
            $errors = [];
            /** @var ConstraintViolationInterface $violation */
            foreach ($violations as $violation) {
                if ($singleProperty) {
                    $errors[] = $violation->getMessage();
                } else {
                    $errors[$violation->getPropertyPath()][] = $violation->getMessage();
                }
            }
            $data = [
                'title' => 'Validation failed',
                'errors' => $errors
            ];
            return new JsonResponse($data, Response::HTTP_BAD_REQUEST);
        }

        return null;
    }
}
