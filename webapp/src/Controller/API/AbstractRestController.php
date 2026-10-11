<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\BaseApiEntity;
use App\Utils\CcsApiVersion;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query;
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
        $object = $this->getQueryBuilder($request)
            ->andWhere(sprintf('%s = :id', $this->getIdField()))
            ->setParameter('id', $id)
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
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

        /** @var CcsApiVersion $ccsApiVersion */
        $ccsApiVersion = $this->config->get('ccs_api_version');
        $groups = [static::GROUP_DEFAULT, $ccsApiVersion->value];
        if (!$request->query->has('strict') || !$request->query->getBoolean('strict')) {
            $groups[] = static::GROUP_NONSTRICT;
        }
        if ($this->authService->checkRole('api_reader')) {
            $groups[] = static::GROUP_RESTRICTED;
        }
        if (in_array(static::GROUP_NONSTRICT, $groups) && in_array(static::GROUP_RESTRICTED, $groups)) {
            $groups[] = static::GROUP_RESTRICTED_NONSTRICT;
        }
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
     * @return array<U>
     * @throws NonUniqueResultException
     */
    protected function listActionHelper(Request $request): array
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
        $objects = $queryBuilder
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
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
