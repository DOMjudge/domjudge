<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\BaseApiEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\ToManyAssociationMapping;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr\From;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Query\Expr\Select;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * @template T of BaseApiEntity
 * @template U
 */
abstract class AbstractRestController extends AbstractApiController
{
    public const ATTR_REFRESH_ENTITIES = '_domjudge_refresh_entities';

    protected function shouldRefresh(Request $request): bool
    {
        return $request->attributes->getBoolean(self::ATTR_REFRESH_ENTITIES);
    }

    /**
     * Get the objects for this endpoint and request.
     *
     * @internal Bypasses HTTP firewall and controller #[IsGranted] attributes; intended for internal/event-log callers.
     * @return mixed[]
     * @throws NonUniqueResultException
     */
    public function getEndpointObjects(Request $request, bool $refresh = false): array
    {
        return $this->listActionHelper($request, $refresh);
    }

    /**
     * Get a single object for this endpoint and ID.
     *
     * @internal Bypasses HTTP firewall and controller #[IsGranted] attributes; intended for internal/event-log callers.
     * @throws NonUniqueResultException
     */
    public function getSingleEndpointObject(Request $request, string $id, bool $refresh = false): mixed
    {
        $queryBuilder = $this->getQueryBuilder($request)
            ->andWhere(sprintf('%s = :id', $this->getIdField()))
            ->setParameter('id', $id);

        $object = $this->buildQuery($queryBuilder, $refresh)
            ->getOneOrNullResult();

        if ($object === null) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $id));
        }

        if ($this instanceof QueryObjectTransformer) {
            $object = $this->transformObject($object);
        }

        return $object;
    }

    protected function isStrict(Request $request): bool
    {
        return $request->query->getBoolean('strict');
    }

    /**
     * Get the serialized endpoint data as an array directly without HTTP sub-requests.
     *
     * @internal Bypasses HTTP firewall and controller #[IsGranted] attributes; intended for EventLogService.
     * @return mixed[]
     * @throws NonUniqueResultException
     */
    public function getEndpointData(Request $request): array
    {
        $objects = $this->getEndpointObjects($request, refresh: true);
        return $this->apiResourceSerializer->toArray($objects, $this->isStrict($request));
    }

    /**
     * Get the serialized single endpoint data as an array directly without HTTP sub-requests.
     *
     * @internal Bypasses HTTP firewall and controller #[IsGranted] attributes; intended for EventLogService.
     * @return mixed[]
     * @throws NonUniqueResultException
     */
    public function getSingleEndpointData(Request $request, string $id): array
    {
        $object = $this->getSingleEndpointObject($request, $id, refresh: true);
        return $this->apiResourceSerializer->toArray($object, $this->isStrict($request));
    }

    protected function performListAction(Request $request): Response
    {
        return $this->renderData($request, $this->getEndpointObjects($request, $this->shouldRefresh($request)));
    }

    /**
     * Get a single object for this endpoint.
     * @throws NonUniqueResultException
     */
    protected function performSingleAction(Request $request, string $id): Response
    {
        $object = $this->getSingleEndpointObject($request, $id, $this->shouldRefresh($request));
        return $this->renderData($request, $object);
    }

    /**
     * Render the given data using the correct groups.
     *
     * @param string[] $extraheaders
     */
    protected function renderData(
        Request $request,
        mixed $data,
        int $statusCode = Response::HTTP_OK,
        array $extraheaders = []
    ): Response {
        $view = $this->view($data);

        // Set the authorizedUser service on the context, so we can use it for permissions.
        $view->getContext()->setAttribute('authorized_user_service', $this->authService);
        $view->getContext()->setAttribute('config_service', $this->config);

        $groups = $this->apiResourceSerializer->getSerializationGroups($this->isStrict($request));
        $view->getContext()->setGroups($groups);

        $response = $this->handleView($view);
        $response->setStatusCode($statusCode);
        $response->headers->add($extraheaders);
        return $response;
    }

    /**
     * Render the given create data using the correct groups.
     */
    protected function renderCreateData(
        Request $request,
        mixed $data,
        string $routeType,
        int|string $id
    ): Response {
        $params = [
            'id' => $id,
        ];
        $postfix = '';
        if ($routeType !== 'user') {
            $params['cid'] = $request->attributes->get('cid');
            // If we request any entity without contest, we need to use the rout postfixed with _1,
            // which is the route without the contest in the URL.
            if ($params['cid'] === null) {
                $postfix = '_1';
            }
        }
        $headers = [
            'Location' => $this->generateUrl("v4_app_api_{$routeType}_single{$postfix}", $params, UrlGeneratorInterface::ABSOLUTE_URL),
        ];
        return $this->renderData($request, $data, Response::HTTP_CREATED,
            $headers);
    }

    /**
     * Render data saved by a POST, PUT or PATCH request.
     */
    protected function renderSavedData(
        Request $request,
        mixed $data,
        string $routeType,
        int|string $id,
        bool $created,
    ): Response {
        if ($created) {
            return $this->renderCreateData($request, $data, $routeType, $id);
        }

        return $this->renderData($request, $data);
    }

    protected function checkPayloadId(string $id, ?string $payloadId): void
    {
        if ($id !== $payloadId) {
            throw new BadRequestHttpException('ID in URL does not match ID in payload');
        }
    }

    /**
     * Overwrite the current data with the fields that were sent in the request.
     *
     * The patch object is only used for its validated values, since a null value means
     * something else than a field that was not sent.
     *
     * @param array<string, mixed> $current
     *
     * @return array<string, mixed>
     */
    protected function mergePatch(Request $request, object $patch, array $current): array
    {
        $patched = $this->normalizePayload($patch);

        return array_replace($current, array_intersect_key($patched, $request->getPayload()->all()));
    }

    /**
     * Convert a request DTO back to an array with the API (snake_case) field names.
     *
     * @return array<string, mixed>
     */
    protected function normalizePayload(object $payload): array
    {
        /** @var NormalizerInterface $normalizer */
        $normalizer = $this->container->get('serializer');

        return $normalizer->normalize($payload);
    }

    /**
     * Import a single entity using one of the JSON import methods and render the result.
     *
     * @param callable(array<array<string, mixed>>, ?string&, ?array<mixed>&): int $import
     * @param array<string, mixed>                                                  $data
     */
    protected function importSingle(
        Request $request,
        callable $import,
        array $data,
        string $routeType,
        bool $created,
    ): Response {
        $saved = [];
        $import([$data], $message, $saved);
        if (!empty($message)) {
            throw new BadRequestHttpException(sprintf('Error while saving %s: %s', $routeType, $message));
        }

        $entity = $saved[0];

        return $this->renderSavedData($request, $entity, $routeType, $entity->getExternalid(), $created);
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
     * Build a Doctrine Query from the given QueryBuilder, optionally enabling Query::HINT_REFRESH
     * and resetting any fetch-joined collections on already-managed entities so collections that
     * became empty in the database do not retain stale elements in Doctrine's ObjectHydrator.
     *
     * @return Query<mixed, mixed>
     */
    protected function buildQuery(QueryBuilder $queryBuilder, bool $refresh): Query
    {
        if ($refresh) {
            $this->resetFetchJoinedCollections($queryBuilder);
            return $queryBuilder->getQuery()->setHint(Query::HINT_REFRESH, true);
        }

        return $queryBuilder->getQuery();
    }

    /**
     * Doctrine's ObjectHydrator skips resetting fetch-joined PersistentCollections during
     * UnitOfWork::createEntity() and fails to clear an already-initialized collection when the
     * joined table now returns 0 rows. Reset clean fetch-joined collections to uninitialized
     * prior to hydration so both empty and non-empty database states hydrate accurately.
     */
    protected function resetFetchJoinedCollections(QueryBuilder $queryBuilder): void
    {
        $selectedAliases = [];
        /** @var Select $selectPart */
        foreach ($queryBuilder->getDQLPart('select') as $selectPart) {
            foreach ($selectPart->getParts() as $part) {
                foreach (explode(',', (string)$part) as $token) {
                    $token = trim($token);
                    if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $token)) {
                        $selectedAliases[$token] = true;
                    }
                }
            }
        }

        if (empty($selectedAliases)) {
            return;
        }

        /** @var array<string, class-string> $aliasToClass */
        $aliasToClass = [];
        /** @var From $fromPart */
        foreach ($queryBuilder->getDQLPart('from') as $fromPart) {
            /** @var class-string $fromClass */
            $fromClass = $fromPart->getFrom();
            $aliasToClass[$fromPart->getAlias()] = $fromClass;
        }

        $uow = $this->em->getUnitOfWork();
        $identityMap = $uow->getIdentityMap();

        /** @var Join[] $joins */
        foreach ($queryBuilder->getDQLPart('join') as $joins) {
            foreach ($joins as $join) {
                $joinExpr = $join->getJoin();
                if (!str_contains($joinExpr, '.')) {
                    if (class_exists($joinExpr)) {
                        $aliasToClass[$join->getAlias()] = $joinExpr;
                    }
                    continue;
                }

                [$parentAlias, $field] = explode('.', $joinExpr, 2);
                if (!isset($aliasToClass[$parentAlias])) {
                    continue;
                }

                $parentMeta = $this->em->getClassMetadata($aliasToClass[$parentAlias]);
                if (!$parentMeta->hasAssociation($field)) {
                    continue;
                }

                $assoc = $parentMeta->getAssociationMapping($field);
                $aliasToClass[$join->getAlias()] = $assoc->targetEntity;

                if (!isset($selectedAliases[$join->getAlias()]) || !$assoc instanceof ToManyAssociationMapping) {
                    continue;
                }

                foreach ($identityMap[$parentMeta->rootEntityName] ?? [] as $entity) {
                    if (!$entity instanceof $parentMeta->name || $uow->isUninitializedObject($entity)) {
                        continue;
                    }

                    $collection = $parentMeta->propertyAccessors[$field]->getValue($entity);
                    if ($collection instanceof PersistentCollection && !$collection->isDirty()) {
                        $collection->setInitialized(false);
                        $collection->unwrap()->clear();
                    } elseif ($collection instanceof Collection && !($collection instanceof PersistentCollection)) {
                        /** @var ArrayCollection<int|string, object> $emptyCollection */
                        $emptyCollection = new ArrayCollection();
                        $pColl = new PersistentCollection(
                            $this->em,
                            $this->em->getClassMetadata($assoc->targetEntity),
                            $emptyCollection
                        );
                        $pColl->setOwner($entity, $assoc);
                        $pColl->setInitialized(false);
                        $parentMeta->propertyAccessors[$field]->setValue($entity, $pColl);
                    }
                }
            }
        }
    }

    /**
     * @return array<U>
     * @throws NonUniqueResultException
     */
    protected function listActionHelper(Request $request, bool $refresh = false): array
    {
        $queryBuilder = $this->getQueryBuilder($request);

        if ($request->query->has('ids')) {
            $ids = $request->query->all('ids');
            $ids = array_unique($ids);
            $queryBuilder
                ->andWhere(sprintf('%s IN (:ids)', $this->getIdField()))
                ->setParameter('ids', $ids);
        }

        /** @var array<T> $objects */
        $objects = $this->buildQuery($queryBuilder, $refresh)
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
    public static function sendBinaryFileResponse(
        Request $request,
        string $fileName,
        bool $download = false
    ): BinaryFileResponse {
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
        if ($download) {
            $response->headers->set('Content-Disposition', 'attachment');
        }

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
