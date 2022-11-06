<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\BaseApiEntity;
use App\Entity\Contest;
use App\Entity\JudgeTask;
use App\Entity\ContestProblem;
use App\Entity\Problem;
use App\Entity\RankCache;
use App\Entity\ScoreCache;
use App\Entity\Team;
use App\Entity\TeamCategory;
use App\Entity\QueueTask;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class BaseController
 *
 * Base controller other controllers can inherit from to get shared functionality.
 *
 * @package App\Controller
 */
abstract class BaseController extends AbstractController
{
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
    protected function isLocalRefererUrl(RouterInterface $router, string $referer, string $prefix): bool
    {
        if (strpos($referer, $prefix) === 0) {
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
            } catch (ResourceNotFoundException $e) {
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
    protected function redirectToLocalReferrer(RouterInterface $router, Request $request, string $defaultUrl): RedirectResponse
    {
        if ($this->isLocalReferer($router, $request)) {
            return $this->redirect($request->headers->get('referer'));
        }

        return $this->redirect($defaultUrl);
    }

    /**
     * Save the given entity, adding an eventlog and auditlog entry.
     */
    protected function saveEntity(
        EntityManagerInterface $entityManager,
        EventLogService $eventLogService,
        DOMJudgeService $DOMJudgeService,
        object $entity,
        $id,
        bool $isNewEntity
    ): void {
        $auditLogType = Utils::tableForEntity($entity);

        $entityManager->persist($entity);
        $entityManager->flush();

        // If we have no ID but we do have a Doctrine entity, automatically
        // get the primary key if possible.
        if ($id === null) {
            try {
                $metadata = $entityManager->getClassMetadata(get_class($entity));
                if (count($metadata->getIdentifierColumnNames()) === 1) {
                    $primaryKey = $metadata->getIdentifierColumnNames()[0];
                    $accessor   = PropertyAccess::createPropertyAccessor();
                    $id         = $accessor->getValue($entity, $primaryKey);
                }
            } catch (MappingException $e) {
                // Entity is not actually a Doctrine entity, ignore.
            }
        }

        if ($endpoint = $eventLogService->endpointForEntity($entity)) {
            foreach ($this->contestsForEntity($entity, $DOMJudgeService) as $contest) {
                $eventLogService->log($endpoint, $id,
                                      $isNewEntity ? EventLogService::ACTION_CREATE : EventLogService::ACTION_UPDATE,
                                      $contest->getCid());
            }
        }

        $DOMJudgeService->auditlog($auditLogType, $id, $isNewEntity ? 'added' : 'updated');
    }

    /**
     * Helper function to get the database structure for an object.
     */
    protected function getDatabaseRelations(array $files, EntityManagerInterface $entityManager): array
    {
        $relations = [];
        foreach ($files as $file) {
            $parts      = explode('/', $file);
            $shortClass = str_replace('.php', '', $parts[count($parts) - 1]);
            $class      = sprintf('App\\Entity\\%s', $shortClass);
            if (class_exists($class) && !in_array($class,
                [RankCache::class, ScoreCache::class, BaseApiEntity::class])) {
                $metadata = $entityManager->getClassMetadata($class);

                $tableRelations = [];
                foreach ($metadata->getAssociationMappings() as $associationMapping) {
                    if (isset($associationMapping['joinColumns']) && count($associationMapping['joinColumns']) === 1) {
                        foreach ($associationMapping['joinColumns'] as $joinColumn) {
                            $type                                = $joinColumn['onDelete'] ?? null;
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
     */
    protected function commitDeleteEntity(
        $entity,
        DOMJudgeService $DOMJudgeService,
        EntityManagerInterface $entityManager,
        array $primaryKeyData,
        EventLogService $eventLogService
    ): void {
        // Used to remove data from the rank and score caches.
        $teamId = null;
        if ($entity instanceof Team) {
            $teamId = $entity->getTeamid();
        }

        // Get the contests to trigger the event for. We do this before
        // deleting the entity, since linked data might have vanished.
        $contestsForEntity = $this->contestsForEntity($entity, $DOMJudgeService);

        $cid = null;
        // Remember the cid to use it in the EventLog later.
        if ($entity instanceof Contest) {
            $cid = $entity->getCid();
        }

        // Add an audit log entry.
        $auditLogType = Utils::tableForEntity($entity);
        $DOMJudgeService->auditlog($auditLogType, implode(', ', $primaryKeyData), 'deleted');

        // Trigger the delete event. We need to do this before deleting the entity to make
        // sure we can still find the entity in the table.
        if ($endpoint = $eventLogService->endpointForEntity($entity)) {
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
                $eventLogService->log($endpoint, $dataId,
                    EventLogService::ACTION_DELETE,
                    $cid, null, null, false);
            }
        }

        // Now actually delete the entity.
        $entityManager->wrapInTransaction(function () use ($entityManager, $entity) {
            if ($entity instanceof Problem) {
                // Deleting a problem is a special case: its dependent tables do not
                // form a tree, and the deletion of judging_run can only cascade from
                // judging, not from testcase. Since MySQL does not define the
                // order of cascading deletes, we need to manually first cascade
                // via submission -> judging -> judging_run.
                $entityManager->getConnection()->executeQuery(
                    'DELETE FROM submission WHERE probid = :probid',
                    ['probid' => $entity->getProbid()]
                );
                // Also delete internal errors that are "connected" to this problem.
                $disabledJson = '{"kind":"problem","probid":' . $entity->getProbid() . '}';
                $entityManager->getConnection()->executeQuery(
                    'DELETE FROM internal_error WHERE disabled = :disabled',
                    ['disabled' => $disabledJson]
                );
                $entityManager->clear();
                $entity = $entityManager->getRepository(Problem::class)->find($entity->getProbid());
            }
            $entityManager->remove($entity);
        });

        if ($entity instanceof Team) {
            // No need to do this in a transaction, since the chance of a team
            // with same ID being created at the same time is negligible.
            $entityManager->getConnection()->executeQuery(
                'DELETE FROM scorecache WHERE teamid = :teamid',
                ['teamid' => $teamId]
            );
            $entityManager->getConnection()->executeQuery(
                'DELETE FROM rankcache WHERE teamid = :teamid',
                ['teamid' => $teamId]
            );
        }
    }

    protected function buildDeleteTree(
        array $entities,
        array $relations,
        EntityManagerInterface $entityManager
    ): array {
        $isError          = false;
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $inflector        = InflectorFactory::create()->build();
        $readableType     = str_replace('_', ' ', Utils::tableForEntity($entities[0]));
        $metadata         = $entityManager->getClassMetadata(get_class($entities[0]));
        $primaryKeyData   = [];
        $messages         = [];
        foreach ($entities as $entity) {
            $primaryKeyDataTemp = [];
            foreach ($metadata->getIdentifierColumnNames() as $primaryKeyColumn) {
                $primaryKeyColumnValue = $propertyAccessor->getValue($entity, $primaryKeyColumn);
                $primaryKeyDataTemp[]      = $primaryKeyColumnValue;

                // Check all relationships.
                foreach ($relations as $table => $tableRelations) {
                    foreach ($tableRelations as $column => $constraint) {
                        // If the target class and column match, check if there are any entities with this value.
                        if ($constraint['targetColumn'] === $primaryKeyColumn && $constraint['target'] === get_class($entity)) {
                            $count = (int)$entityManager->createQueryBuilder()
                                ->from($table, 't')
                                ->select(sprintf('COUNT(t.%s) AS cnt', $column))
                                ->andWhere(sprintf('t.%s = :value', $column))
                                ->setParameter('value', $primaryKeyColumnValue)
                                ->getQuery()
                                ->getSingleScalarResult();
                            if ($count > 0) {
                                $parts              = explode('\\', $table);
                                $targetEntityType   = $parts[count($parts) - 1];
                                $targetReadableType = str_replace(
                                    '_', ' ',
                                    $inflector->tableize($inflector->pluralize($targetEntityType))
                                );

                                switch ($constraint['type']) {
                                    case 'CASCADE':
                                        $message           = sprintf('Cascade to %s', $targetReadableType);
                                        $dependentEntities = $this->getDependentEntities($table, $relations);
                                        if (!empty($dependentEntities)) {
                                            $dependentEntitiesReadable = [];
                                            foreach ($dependentEntities as $dependentEntity) {
                                                $parts                       = explode('\\', $dependentEntity);
                                                $dependentEntityType         = $parts[count($parts) - 1];
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
                                        $isError  = true;
                                        $messages = [
                                            sprintf('%s with %s "%s" is still referenced in %s, cannot delete.',
                                                    ucfirst($readableType), $primaryKeyColumn, $primaryKeyColumnValue,
                                                    $targetReadableType)
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
        return [$isError, $primaryKeyData, $messages];
    }

    /**
     * Perform delete operation for the given entities.
     *
     * @throws DBALException
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    protected function deleteEntities(
        Request $request,
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        EventLogService $eventLogService,
        KernelInterface $kernel,
        array $entities,
        string $redirectUrl
    ) : Response {
        // Assume that we only delete entities of the same class.
        foreach ($entities as $entity) {
            assert(get_class($entities[0]) === get_class($entity));
        }
        // Determine all the relationships between all tables using Doctrine cache.
        $dir          = realpath(sprintf('%s/src/Entity', $kernel->getProjectDir()));
        $files        = glob($dir . '/*.php');
        $relations    = $this->getDatabaseRelations($files, $entityManager);
        $readableType = str_replace('_', ' ', Utils::tableForEntity($entities[0]));
        $messages     = [];

        [$isError, $primaryKeyData, $deleteTreeMessages] = $this->buildDeleteTree($entities, $relations, $entityManager);
        if (!empty($deleteTreeMessages)) {
            $messages = $deleteTreeMessages;
        }

        if ($request->isMethod('POST')) {
            if ($isError) {
                throw new BadRequestHttpException(reset($messages));
            }

            $msgList = [];
            foreach ($entities as $id => $entity) {
                $this->commitDeleteEntity($entity, $DOMJudgeService, $entityManager, $primaryKeyData[$id], $eventLogService);
                $description = $entity->getShortDescription();
                $msgList[] = sprintf('Successfully deleted %s %s "%s"',
                                     $readableType, implode(', ', $primaryKeyData[$id]), $description);
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
            'primaryKey' => implode(', ', array_merge(...$primaryKeyData)),
            'description' => implode(',', $descriptions),
            'messages' => $messages,
            'isError' => $isError,
            'showModalSubmit' => !$isError,
            'modalUrl' => $request->getRequestUri(),
            'redirectUrl' => $redirectUrl,
        ];
        if ($request->isXmlHttpRequest()) {
            return $this->render('jury/delete_modal.html.twig', $data);
        }

        return $this->render('jury/delete.html.twig', $data);
    }

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
     * Get the contests that an event for the given entity should be triggered on
     * @param mixed $entity
     *
     * @return Contest[]
     */
    protected function contestsForEntity($entity, DOMJudgeService $dj): array
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
            $possibleContests = $dj->getCurrentContests();
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
            $contests = $dj->getCurrentContests();
        }

        return $contests;
    }

    /**
     * Stream a response with the given callback.
     *
     * The callback can use ob_flush(); flush(); to flush its output to the browser.
     */
    protected function streamResponse(callable $callback): StreamedResponse
    {
        $response         = new StreamedResponse();
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->setCallback($callback);
        return $response;
    }

    protected function judgeRemaining(array $judgings): void
    {
        $inProgress = [];
        $alreadyRequested = [];
        $invalidJudgings = [];
        $numRequested = 0;
        foreach ($judgings as $judging) {
            $judgingId = $judging->getJudgingid();
            if ($judging->getResult() === null) {
                $inProgress[] = $judgingId;
            } elseif ($judging->getJudgeCompletely()) {
                $alreadyRequested[] = $judgingId;
            } elseif (!$judging->getValid()) {
                $invalidJudgings[] = $judgingId;
            } else {
                $numRequested = $this->em->getConnection()->executeStatement(
                    'UPDATE judgetask SET valid=1'
                    . ' WHERE jobid=:jobid'
                    . ' AND judgehostid IS NULL',
                    [
                        'jobid' => $judgingId,
                    ]
                );
                $judging->setJudgeCompletely(true);

                $submission = $judging->getSubmission();

                $queueTask = new QueueTask();
                $queueTask->setJobId($judging->getJudgingid())
                    ->setPriority(JudgeTask::PRIORITY_LOW)
                    ->setTeam($submission->getTeam())
                    ->setTeamPriority((int)$submission->getSubmittime())
                    ->setStartTime(null);
                $this->em->persist($queueTask);
            }
        }
        $this->em->flush();
        if (count($judgings) === 1) {
            if ($inProgress !== []) {
                $this->addFlash('warning', 'Please be patient, this judging is still in progress.');
            }
            if ($alreadyRequested !== []) {
                $this->addFlash('warning', 'This judging was already requested to be judged completely.');
            }
        } else {
            if ($inProgress !== []) {
                $this->addFlash('warning', sprintf('Please be patient, these judgings are still in progress: %s', implode(', ', $inProgress)));
            }
            if ($alreadyRequested !== []) {
                $this->addFlash('warning', sprintf('These judgings were already requested to be judged completely: %s', implode(', ', $alreadyRequested)));
            }
            if ($invalidJudgings !== []) {
                $this->addFlash('warning', sprintf('These judgings were skipped as they were superseded by other judgings: %s', implode(', ', $invalidJudgings)));
            }
        }
        if ($numRequested === 0) {
            $this->addFlash('warning', 'No more remaining runs to be judged.');
        } else {
            $this->addFlash('info', "Requested $numRequested remaining runs to be judged.");
        }
    }
}
