<?php declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\ExternalIdAlreadyExistsException;
use App\Entity\AbstractJudgement;
use App\Entity\AbstractRun;
use App\Entity\BaseApiEntity;
use App\Entity\CalculatedExternalIdBasedOnRelatedFieldInterface;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\ExternalIdFromInternalIdInterface;
use App\Entity\HasExternalIdInterface;
use App\Entity\Problem;
use App\Entity\RankCache;
use App\Entity\ScoreCache;
use App\Entity\Team;
use App\Entity\TeamCategory;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;

/**
 * Base controller other controllers can inherit from to get shared functionality.
 */
abstract class BaseController extends AbstractController
{
    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly EventLogService $eventLog,
        protected readonly DOMJudgeService $dj,
        protected readonly KernelInterface $kernel,
    ) {}

    /**
     * Check whether the referrer in the request is of the current application.
     */
    protected function isLocalReferer(RouterInterface $router, Request $request): bool
    {
        if ($referer = $request->headers->get('referer')) {
            $prefix = sprintf('%s%s', $request->getSchemeAndHttpHost(), $request->getBasePath());
            return $this->isLocalRefererUrl($router, $referer, $prefix);
        }

        return false;
    }

    /**
     * Check whether the given referer is local.
     */
    protected function isLocalRefererUrl(
        RouterInterface $router,
        string $referer,
        string $prefix
    ): bool {
        if (str_starts_with($referer, $prefix)) {
            $path = substr($referer, strlen($prefix));
            if (($questionMark = strpos($path, '?')) !== false) {
                $path = substr($path, 0, $questionMark);
            }
            $context = $router->getContext();
            $method = $context->getMethod();
            $context->setMethod('GET');
            try {
                $router->match($path);
                return true;
            } catch (ResourceNotFoundException) {
                return false;
            } finally {
                $context->setMethod($method);
            }
        }

        return false;
    }

    /**
     * Redirect to the referrer if it is a known (local) route, otherwise redirect to the given URL.
     */
    protected function redirectToLocalReferrer(
        RouterInterface $router,
        Request $request,
        string $defaultUrl
    ): RedirectResponse {
        if ($this->isLocalReferer($router, $request)) {
            return $this->redirect($request->headers->get('referer'));
        }

        return $this->redirect($defaultUrl);
    }

    /**
     * Save the given entity, adding an eventlog and auditlog entry.
     */
    protected function saveEntity(
        object $entity,
        mixed $id,
        bool $isNewEntity
    ): void {
        $auditLogType = Utils::tableForEntity($entity);

        // Call the prePersist lifecycle callbacks.
        // This used to work in preUpdate, but Doctrine has deprecated that feature.
        // See https://www.doctrine-project.org/projects/doctrine-orm/en/3.1/reference/events.html#events-overview.
        $metadata = $this->em->getClassMetadata($entity::class);
        foreach ($metadata->lifecycleCallbacks['prePersist'] ?? [] as $prePersistMethod) {
            $entity->$prePersistMethod();
        }

        $this->em->persist($entity);
        $this->em->flush();

        // If we have no ID but we do have a Doctrine entity, automatically
        // get the primary key if possible.
        if ($id === null) {
            try {
                $metadata = $this->em->getClassMetadata($entity::class);
                if (count($metadata->getIdentifierColumnNames()) === 1) {
                    $primaryKey = $metadata->getIdentifierColumnNames()[0];
                    $accessor = PropertyAccess::createPropertyAccessor();
                    $id = $accessor->getValue($entity, $primaryKey);
                }
            } catch (MappingException) {
                // Entity is not actually a Doctrine entity, ignore.
            }
        }

        if ($endpoint = $this->eventLog->endpointForEntity($entity)) {
            foreach ($this->contestsForEntity($entity) as $contest) {
                $this->eventLog->log($endpoint, $id,
                    $isNewEntity ? EventLogService::ACTION_CREATE : EventLogService::ACTION_UPDATE,
                    $contest->getCid());
            }
        }

        if ($entity instanceof HasExternalIdInterface) {
            $dataid = $entity->getExternalId();
        } else {
            $dataid = $id;
        }
        $this->dj->auditlog($auditLogType, (string)$dataid, $isNewEntity ? 'added' : 'updated');
    }

    /**
     * Helper function to get the database structure for an object.
     *
     * @param string[] $files
     *
     * @return array<string, array<array{'target': string, 'targetColumn': string, 'type': string}>>
     */
    protected function getDatabaseRelations(array $files): array {
        $skippedClasses = [
            RankCache::class,
            ScoreCache::class,
            BaseApiEntity::class,
            AbstractJudgement::class,
            AbstractRun::class,
        ];
        $relations = [];
        foreach ($files as $file) {
            $parts = explode('/', $file);
            $shortClass = str_replace('.php', '', $parts[count($parts) - 1]);
            $class = sprintf('App\\Entity\\%s', $shortClass);
            if (class_exists($class) &&
                !in_array($class, $skippedClasses) &&
                !enum_exists($class)) {
                $metadata = $this->em->getClassMetadata($class);

                $tableRelations = [];
                foreach ($metadata->getAssociationMappings() as $associationMapping) {
                    if (isset($associationMapping['joinColumns']) && count($associationMapping['joinColumns']) === 1) {
                        foreach ($associationMapping['joinColumns'] as $joinColumn) {
                            $type = $joinColumn['onDelete'] ?? null;
                            $tableRelations[$associationMapping['fieldName']] = [
                                'target' => $associationMapping['targetEntity'],
                                'targetColumn' => $joinColumn['referencedColumnName'],
                                'type' => $type,
                            ];
                        }
                    }
                }

                $relations[$class] = $tableRelations;
            }
        }
        return $relations;
    }

    /**
     * Handle the actual removal of an entity and the dependencies in the database.
     *
     * @param int[] $primaryKeyData
     */
    protected function commitDeleteEntity(object $entity, array $primaryKeyData): void {
        // Used to remove data from the rank and score caches.
        $teamId = null;
        if ($entity instanceof Team) {
            $teamId = $entity->getTeamid();
        }

        // Get the contests to trigger the event for. We do this before
        // deleting the entity, since linked data might have vanished.
        $contestsForEntity = $this->contestsForEntity($entity);

        $cid = null;
        // Remember the cid to use it in the EventLog later.
        if ($entity instanceof Contest) {
            $cid = $entity->getCid();
        }

        // Add an audit log entry.
        $auditLogType = Utils::tableForEntity($entity);
        if ($entity instanceof HasExternalIdInterface) {
            $dataid = $entity->getExternalId();
        } else {
            $dataid = implode(', ', $primaryKeyData);
        }
        $this->dj->auditlog($auditLogType, $dataid, 'deleted');

        // Trigger the delete event. We need to do this before deleting the entity to make
        // sure we can still find the entity in the table.
        if ($endpoint = $this->eventLog->endpointForEntity($entity)) {
            foreach ($contestsForEntity as $contest) {
                // When the $entity is a contest it has no id anymore after the EntityManager->remove
                // for this reason we either remember it or check all other contests and use their cid.
                if (!$entity instanceof Contest) {
                    $cid = $contest->getCid();
                }
                $dataId = $primaryKeyData[0];
                if ($entity instanceof ContestProblem) {
                    $dataId = $entity->getProbid();
                }
                // TODO: cascade deletes. Maybe use getDependentEntities()?
                $this->eventLog->log($endpoint, $dataId,
                    EventLogService::ACTION_DELETE,
                    $cid, null, null, false);
            }
        }

        // Now actually delete the entity.
        $this->em->wrapInTransaction(function () use ($entity): void {
            if ($entity instanceof Problem) {
                // Deleting a problem is a special case:
                // Its dependent tables do not form a tree (but something like a diamond shape),
                // and there are multiple cascading removal paths from problem to its dependent
                // tables.
                // Since MySQL does not define the order of cascading deletes, we need to
                // first manually delete judging_runs and then cascade via
                // submission to all of judging, judgeTasks and queueTasks.
                // See also https://github.com/DOMjudge/domjudge/issues/243 and associated commits.

                // First delete judging_runs.
                $this->em->getConnection()->executeQuery(
                    'DELETE jr FROM judging_run jr
                         INNER JOIN judging j ON jr.judgingid = j.judgingid
                         INNER JOIN submission s ON j.submitid = s.submitid
                         WHERE s.probid = :probid',
                    ['probid' => $entity->getProbid()]
                );

                // Then delete submissions which will cascade to judging, judgeTasks and queueTasks.
                $this->em->getConnection()->executeQuery(
                    'DELETE FROM submission WHERE probid = :probid',
                    ['probid' => $entity->getProbid()]
                );

                // Lastly, delete internal errors that are "connected" to this problem.
                $disabledJson = '{"kind":"problem","probid":' . $entity->getProbid() . '}';
                $this->em->getConnection()->executeQuery(
                    'DELETE FROM internal_error WHERE disabled = :disabled',
                    ['disabled' => $disabledJson]
                );

                $this->em->clear();
                $entity = $this->em->getRepository(Problem::class)->find($entity->getProbid());
            }
            $this->em->remove($entity);
        });

        if ($entity instanceof Team) {
            // No need to do this in a transaction, since the chance of a team
            // with same ID being created at the same time is negligible.
            $this->em->getConnection()->executeQuery(
                'DELETE FROM scorecache WHERE teamid = :teamid',
                ['teamid' => $teamId]
            );
            $this->em->getConnection()->executeQuery(
                'DELETE FROM rankcache WHERE teamid = :teamid',
                ['teamid' => $teamId]
            );
        }
    }

    /**
     * @param Object[] $entities
     * @param array<string, array<string, array{'target': string, 'targetColumn': string, 'type': string}>> $relations
     *
     * @return array{0: bool, 1: array<int[]>, 2: string[], 3: array<string>}
     */
    protected function buildDeleteTree(array $entities, array $relations): array {
        $isError = false;
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $inflector = InflectorFactory::create()->build();
        $readableType = str_replace('_', ' ', Utils::tableForEntity($entities[0]));
        $metadata = $this->em->getClassMetadata($entities[0]::class);
        $primaryKeyData = [];
        $externalIdData = [];
        $messages = [];
        foreach ($entities as $entity) {
            $primaryKeyDataTemp = [];
            if ($entity instanceof HasExternalIdInterface) {
                $externalIdData[] = $entity->getExternalId();
            }
            foreach ($metadata->getIdentifierColumnNames() as $primaryKeyColumn) {
                $primaryKeyColumnValue = $propertyAccessor->getValue($entity, $primaryKeyColumn);
                $primaryKeyDataTemp[] = $primaryKeyColumnValue;

                // Check all relationships.
                foreach ($relations as $table => $tableRelations) {
                    foreach ($tableRelations as $column => $constraint) {
                        // If the target class and column match, check if there are any entities with this value.
                        if ($constraint['targetColumn'] === $primaryKeyColumn && $constraint['target'] === $entity::class) {
                            $count = (int)$this->em->createQueryBuilder()
                                ->from($table, 't')
                                ->select(sprintf('COUNT(t.%s) AS cnt', $column))
                                ->andWhere(sprintf('t.%s = :value', $column))
                                ->setParameter('value', $primaryKeyColumnValue)
                                ->getQuery()
                                ->getSingleScalarResult();
                            if ($count > 0) {
                                $parts = explode('\\', $table);
                                $targetEntityType = $parts[count($parts) - 1];
                                $targetReadableType = str_replace(
                                    '_', ' ',
                                    $inflector->tableize($inflector->pluralize($targetEntityType))
                                );

                                switch ($constraint['type']) {
                                    case 'CASCADE':
                                        $message = sprintf('Cascade to %s', $targetReadableType);
                                        $dependentEntities = $this->getDependentEntities($table, $relations);
                                        if (!empty($dependentEntities)) {
                                            $dependentEntitiesReadable = [];
                                            foreach ($dependentEntities as $dependentEntity) {
                                                $parts = explode('\\', $dependentEntity);
                                                $dependentEntityType = $parts[count($parts) - 1];
                                                $dependentEntitiesReadable[] = str_replace(
                                                    '_', ' ',
                                                    $inflector->tableize($inflector->pluralize($dependentEntityType))
                                                );
                                            }
                                            $message .= sprintf(
                                                ', and possibly to dependent entities %s',
                                                implode(', ', $dependentEntitiesReadable)
                                            );
                                        }
                                        $messages[] = $message;
                                        break;
                                    case 'SET NULL':
                                        $messages[] = sprintf('Create dangling references in %s', $targetReadableType);
                                        break;
                                    case null:
                                        $isError = true;
                                        $messages = [
                                            sprintf('%s with %s "%s" is still referenced in %s, cannot delete.',
                                                ucfirst($readableType), $primaryKeyColumn, $primaryKeyColumnValue,
                                                $targetReadableType),
                                        ];
                                        break 4;
                                }
                            }
                        }
                    }
                }
            }
            $primaryKeyData[] = $primaryKeyDataTemp;
        }
        return [$isError, $primaryKeyData, array_values(array_unique($messages)), $externalIdData];
    }

    /**
     * Perform delete operation for the given entities.
     *
     * @param Object[] $entities
     *
     * @throws DBALException
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    protected function deleteEntities(
        Request $request,
        array $entities,
        string $redirectUrl
    ): Response {
        // Assume that we only delete entities of the same class.
        foreach ($entities as $entity) {
            assert($entities[0]::class === $entity::class);
        }
        // Determine all the relationships between all tables using Doctrine cache.
        $dir = realpath(sprintf('%s/src/Entity', $this->kernel->getProjectDir()));
        $files = glob($dir . '/*.php');
        $relations = $this->getDatabaseRelations($files);
        $readableType = str_replace('_', ' ', Utils::tableForEntity($entities[0]));
        $messages = [];

        [
            $isError,
            $primaryKeyData,
            $deleteTreeMessages,
            $externalIdData,
        ] = $this->buildDeleteTree($entities, $relations);
        if (!empty($deleteTreeMessages)) {
            $messages = $deleteTreeMessages;
        }

        if ($request->isMethod('POST')) {
            if ($isError) {
                throw new BadRequestHttpException(reset($messages));
            }

            $msgList = [];
            foreach ($entities as $id => $entity) {
                $this->commitDeleteEntity($entity, $primaryKeyData[$id]);
                $description = $entity->getShortDescription();
                $msgList[] = sprintf('Successfully deleted %s %s "%s"',
                    $readableType, $externalIdData[$id] ?? implode(', ', $primaryKeyData[$id]), $description);
            }

            $msg = implode("\n", $msgList);
            $this->addFlash('success', $msg);
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['url' => $redirectUrl]);
            }

            return $this->redirect($redirectUrl);
        }

        $descriptions = [];
        foreach ($entities as $entity) {
            $descriptions[] = $entity->getShortDescription();
        }

        $data = [
            'type' => $readableType,
            'primaryKey' => !empty($externalIdData) ? implode(', ', $externalIdData) : implode(', ', array_merge(...$primaryKeyData)),
            'description' => implode(',', $descriptions),
            'messages' => $messages,
            'isError' => $isError,
            'showModalSubmit' => !$isError,
            'modalUrl' => $request->getRequestUri(),
            'redirectUrl' => $redirectUrl,
            'count' => count($entities),
        ];
        if ($request->isXmlHttpRequest()) {
            return $this->render('jury/delete_modal.html.twig', $data);
        }

        return $this->render('jury/delete.html.twig', $data);
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     */
    protected function deleteMultiple(
        Request $request,
        string $entityClass,
        string $idProperty,
        string $redirectRoute,
        string $warningMessage,
        ?callable $filter = null
    ): Response {
        $ids = $request->query->all('ids');
        if (empty($ids)) {
            throw new BadRequestHttpException('No IDs specified for deletion');
        }

        /** @var \Doctrine\ORM\EntityRepository<T> $repository */
        $repository = $this->em->getRepository($entityClass);
        $entities = $repository->findBy([$idProperty => $ids]);

        if ($filter) {
            $entities = array_filter($entities, $filter);
        }

        if (empty($entities)) {
            $this->addFlash('warning', $warningMessage);
            return $this->redirectToRoute($redirectRoute);
        }

        return $this->deleteEntities($request, $entities, $this->generateUrl($redirectRoute));
    }

    /**
     * @param array<string, array<array{'target': string, 'targetColumn': string, 'type': string}>> $relations
     * @return string[]
     */
    protected function getDependentEntities(string $entityClass, array $relations): array
    {
        $result = [];
        // We do a BFS through the list of tables.
        $queue = [$entityClass];
        while (count($queue) > 0) {
            $currentEntity = reset($queue);
            unset($queue[array_search($currentEntity, $queue)]);

            if (in_array($currentEntity, $result)) {
                continue;
            }
            if ($currentEntity !== $entityClass) {
                $result[] = $currentEntity;
            }

            foreach ($relations as $nextEntity => $relatedEntities) {
                foreach ($relatedEntities as $constraint) {
                    if ($constraint['target'] === $currentEntity && $constraint['type'] === 'CASCADE') {
                        $queue[] = $nextEntity;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param array<string, array<string, mixed>> $table_fields
     */
    protected function addSelectAllCheckbox(array &$table_fields, string $title): void
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            $table_fields = array_merge(
                ['checkbox' => ['title' => sprintf('<input type="checkbox" class="select-all" title="Select all %s">', $title), 'sort' => false, 'search' => false, 'raw' => true]],
                $table_fields
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function addEntityCheckbox(array &$data, object $entity, mixed $identifierValue, string $checkboxClass, ?callable $condition = null): void
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            if ($condition !== null && !$condition($entity)) {
                $data['checkbox'] = ['value' => ''];
                return;
            }
            $data['checkbox'] = [
                'value' => sprintf(
                    '<input type="checkbox" name="ids[]" value="%s" class="%s">',
                    $identifierValue,
                    $checkboxClass
                ),
            ];
        }
    }

    /**
     * Get the contests that an event for the given entity should be triggered on
     *
     * @return Contest[]
     */
    protected function contestsForEntity(mixed $entity): array
    {
        // Determine contests to emit an event for the given entity:
        // * If the entity is a Problem entity, use the getContest()
        //   of every contest problem in getContestProblems().
        // * If the entity is a Team (category) entity, get all active
        //   contests and use those that are open to all teams or the
        //   team (category) belongs to.
        // * If the entity is a contest, use that.
        // * If the entity has a getContest() method, use that.
        // * If the entity has a getContests() method, use that.
        // Otherwise, use the currently active contests.
        $contests = [];
        if ($entity instanceof Team || $entity instanceof TeamCategory) {
            $possibleContests = $this->dj->getCurrentContests();
            foreach ($possibleContests as $contest) {
                if ($entity->inContest($contest)) {
                    $contests[] = $contest;
                }
            }
        } elseif ($entity instanceof Problem) {
            foreach ($entity->getContestProblems() as $contestProblem) {
                $contests[] = $contestProblem->getContest();
            }
        } elseif ($entity instanceof Contest) {
            $contests = [$entity];
        } elseif (method_exists($entity, 'getContest')) {
            $contests = [$entity->getContest()];
        } elseif (method_exists($entity, 'getContests')) {
            $contests = $entity->getContests();
        } else {
            $contests = $this->dj->getCurrentContests();
        }

        if ($contests instanceof Collection) {
            $contests = $contests->toArray();
        }

        return $contests;
    }

    /**
     * Stream a response with the given callback.
     *
     * The callback can use ob_flush(); flush(); to flush its output to the browser.
     */
    protected function streamResponse(RequestStack $requestStack, callable $callback): StreamedResponse
    {
        // Keep the current request, since streamed response removes it from the request stack and
        // we need it for sessions. See https://github.com/symfony/symfony/issues/46743.
        $mainRequest = $requestStack->getMainRequest();
        $response = new StreamedResponse();
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->setCallback(function () use ($requestStack, $callback, $mainRequest): void {
            $requestStack->push($mainRequest);
            $callback();
        });
        return $response;
    }

    /**
     * @param callable(): string         $urlGenerator
     * @param callable(): ?Response|null $saveCallback
     */
    protected function processAddFormForExternalIdEntity(
        FormInterface $form,
        ExternalIdFromInternalIdInterface|CalculatedExternalIdBasedOnRelatedFieldInterface $entity,
        callable $urlGenerator,
        ?callable $saveCallback = null
    ): ?Response {
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if ($saveCallback) {
                    if ($response = $saveCallback()) {
                        return $response;
                    }
                } else {
                    $this->saveEntity($entity, null, true);
                }
                return $this->redirect($urlGenerator());
            } catch (ExternalIdAlreadyExistsException $e) {
                $message = sprintf(
                    'The auto assigned external ID \'%s\' is already in use. Please type one yourself.',
                    $e->externalid
                );
                $form->get('externalid')->addError(new FormError($message));
                return null;
            }
        }

        return null;
    }

    /**
     * Get the previous and next object IDs for navigation.
     *
     * @param class-string                $entityClass     Entity class to query
     * @param mixed                       $currentIdValue  Current value of the ID field
     * @param string                      $idField         Field to return as the ID (e.g., 'externalid', 'submitid')
     * @param array<string, 'ASC'|'DESC'> $orderBy         Sort criteria as field => direction (e.g., ['e.submittime' => 'ASC', 'e.submitid' => 'ASC'])
     * @param bool                        $filterOnContest Whether to filter results by current contests
     *
     * @return array{previous: string|int|null, next: string|int|null}
     */
    protected function getPreviousAndNextObjectIds(
        string $entityClass,
        mixed $currentIdValue,
        string $idField = 'externalid',
        array $orderBy = ['e.externalid' => 'ASC'],
        bool $filterOnContest = false,
    ): array {
        $result = ['previous' => null, 'next' => null];

        // Fetch the current entity once to get field values
        $currentEntity = $this->em->getRepository($entityClass)->findOneBy([$idField => $currentIdValue]);
        if ($currentEntity === null) {
            return $result;
        }

        $accessor = PropertyAccess::createPropertyAccessor();

        // Pre-compute field values for comparison
        $fieldValues = [];
        foreach (array_keys($orderBy) as $field) {
            $fieldName = str_replace('e.', '', $field);
            $fieldValues[$field] = $accessor->getValue($currentEntity, $fieldName);
        }

        // Build the comparison conditions based on the sort criteria.
        // For multi-column ordering, we need: (col1 < val1) OR (col1 = val1 AND col2 < val2) etc.
        $buildComparisonConditions = function (string $operator) use ($orderBy, $fieldValues): array {
            $conditions = [];
            $parameters = [];
            $fields = array_keys($orderBy);
            $directions = array_values($orderBy);

            for ($i = 0; $i < count($fields); $i++) {
                $equalityParts = [];
                // Add equality conditions for all previous columns
                for ($j = 0; $j < $i; $j++) {
                    $field = $fields[$j];
                    $paramName = 'eq_' . $j;
                    $equalityParts[] = "$field = :$paramName";
                    $parameters[$paramName] = $fieldValues[$field];
                }

                // Add the comparison for this column
                $field = $fields[$i];
                $direction = $directions[$i];
                // For "previous": if ASC, we want < ; if DESC, we want >
                // For "next": if ASC, we want > ; if DESC, we want <
                $compOp = ($operator === 'previous')
                    ? ($direction === 'ASC' ? '<' : '>')
                    : ($direction === 'ASC' ? '>' : '<');
                $paramName = 'cmp_' . $i;
                $comparisonPart = "$field $compOp :$paramName";
                $parameters[$paramName] = $fieldValues[$field];

                if (!empty($equalityParts)) {
                    $conditions[] = '(' . implode(' AND ', $equalityParts) . ' AND ' . $comparisonPart . ')';
                } else {
                    $conditions[] = '(' . $comparisonPart . ')';
                }
            }

            return ['condition' => implode(' OR ', $conditions), 'parameters' => $parameters];
        };

        foreach (['previous', 'next'] as $direction) {
            $qb = $this->em->createQueryBuilder()
                ->select("e.$idField")
                ->from($entityClass, 'e');

            // Build and apply the comparison conditions
            $comp = $buildComparisonConditions($direction);
            if (!empty($comp['condition'])) {
                $qb->andWhere($comp['condition']);
                foreach ($comp['parameters'] as $param => $value) {
                    $qb->setParameter($param, $value);
                }
            }

            // Apply contest filter
            if ($filterOnContest && $contest = $this->dj->getCurrentContest()) {
                $qb->andWhere('e.contest = :contest')
                    ->setParameter('contest', $contest);
            }

            // Apply ordering (reversed for previous)
            foreach ($orderBy as $field => $dir) {
                $actualDir = $direction === 'previous'
                    ? ($dir === 'ASC' ? 'DESC' : 'ASC')
                    : $dir;
                $qb->addOrderBy($field, $actualDir);
            }

            $qb->setMaxResults(1);

            try {
                $value = $qb->getQuery()->getSingleScalarResult();
                $result[$direction] = $value;
            } catch (NoResultException) {
                // No previous/next found, leave as null
            }
        }

        return $result;
    }
}
