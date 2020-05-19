<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\BaseApiEntity;
use App\Entity\Contest;
use App\Entity\Problem;
use App\Entity\RankCache;
use App\Entity\ScoreCache;
use App\Entity\Team;
use App\Entity\TeamCategory;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\MappingException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class BaseController
 *
 * Base controller other controllers can inherit from to get shared functionality
 *
 * @package App\Controller
 */
abstract class BaseController extends AbstractController
{
    /**
     * Check whether the referrer in the request is of the current application
     * @param RouterInterface $router
     * @param Request         $request
     * @return bool
     */
    protected function isLocalReferrer(RouterInterface $router, Request $request)
    {
        if ($referrer = $request->headers->get('referer')) {
            $prefix = sprintf('%s%s', $request->getSchemeAndHttpHost(), $request->getBasePath());
            if (strpos($referrer, $prefix) === 0) {
                $path = substr($referrer, strlen($prefix));
                try {
                    $router->match($path);
                    return true;
                } catch (ResourceNotFoundException $e) {
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * Redirect to the referrer if it is a known (local) route, otherwise redirect to the given URL
     * @param RouterInterface $router
     * @param Request         $request
     * @param string          $defaultUrl
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function redirectToLocalReferrer(RouterInterface $router, Request $request, string $defaultUrl)
    {
        if ($this->isLocalReferrer($router, $request)) {
            return $this->redirect($request->headers->get('referer'));
        }

        return $this->redirect($defaultUrl);
    }

    /**
     * Save the given entity, adding an eventlog and auditlog entry
     * @param EntityManagerInterface $entityManager
     * @param EventLogService        $eventLogService
     * @param DOMJudgeService        $DOMJudgeService
     * @param object                 $entity
     * @param mixed                  $id
     * @param bool                   $isNewEntity
     * @throws \Exception
     */
    protected function saveEntity(
        EntityManagerInterface $entityManager,
        EventLogService $eventLogService,
        DOMJudgeService $DOMJudgeService,
        $entity,
        $id,
        bool $isNewEntity
    ) {
        $auditLogType = Utils::tableForEntity($entity);

        $entityManager->flush();

        // If we have no ID but we do have a Doctrine entity, automatically
        // get the primary key if possible
        if ($id === null) {
            try {
                $metadata = $entityManager->getClassMetadata(get_class($entity));
                if (count($metadata->getIdentifierColumnNames()) === 1) {
                    $primaryKey = $metadata->getIdentifierColumnNames()[0];
                    $accessor   = PropertyAccess::createPropertyAccessor();
                    $id         = $accessor->getValue($entity, $primaryKey);
                }
            } catch (MappingException $e) {
                // Entity is not actually a Doctrine entity, ignore
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
     * Perform the delete for the given entity
     *
     * @param Request                $request
     * @param EntityManagerInterface $entityManager
     * @param DOMJudgeService        $DOMJudgeService
     * @param EventLogService        $eventLogService
     * @param KernelInterface        $kernel
     * @param                        $entity
     * @param string                 $description
     * @param string                 $redirectUrl
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    protected function deleteEntity(
        Request $request,
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        EventLogService $eventLogService,
        KernelInterface $kernel,
        $entity,
        string $description,
        string $redirectUrl
    ) {
        // Determine all the relationships between all tables using Doctrine cache
        $dir       = realpath(sprintf('%s/src/Entity', $kernel->getProjectDir()));
        $files     = glob($dir . '/*.php');
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

        $isError          = false;
        $messages         = [];
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $readableType     = str_replace('_', ' ', Utils::tableForEntity($entity));
        $metadata         = $entityManager->getClassMetadata(get_class($entity));
        $primaryKeyData   = [];
        foreach ($metadata->getIdentifierColumnNames() as $primaryKeyColumn) {
            $primaryKeyColumnValue = $propertyAccessor->getValue($entity, $primaryKeyColumn);
            $primaryKeyData[]      = $primaryKeyColumnValue;

            // Check all relationships
            foreach ($relations as $table => $tableRelations) {
                foreach ($tableRelations as $column => $constraint) {
                    // If the target class and column match, check if there are any entities with this value
                    if ($constraint['target'] === $class && $constraint['targetColumn'] === $primaryKeyColumn) {
                        $count = (int)$entityManager->createQueryBuilder()
                            ->from($table, 't')
                            ->select(sprintf('COUNT(t.%s) AS cnt', $column))
                            ->andWhere(sprintf('t.%s = :value', $column))
                            ->setParameter(':value', $primaryKeyColumnValue)
                            ->getQuery()
                            ->getSingleScalarResult();
                        if ($count > 0) {
                            $parts              = explode('\\', $table);
                            $targetEntityType   = $parts[count($parts) - 1];
                            $targetReadableType = str_replace(
                                '_', ' ',
                                Inflector::tableize(Inflector::pluralize($targetEntityType))
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
                                                Inflector::tableize(Inflector::pluralize($dependentEntityType))
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

        if ($request->isMethod('POST')) {
            if ($isError) {
                throw new BadRequestHttpException(reset($messages));
            }

            $entityId = null;
            if ($entity instanceof Team) {
                $entityId = $entity->getTeamid();
            }

            // Get the contests to trigger the event for. We do this before
            // deleting the entity, since linked data might have vanished
            $contestsForEntity = $this->contestsForEntity($entity, $DOMJudgeService);

            $entityManager->transactional(function () use ($entityManager, $entity) {
                if ($entity instanceof Problem) {
                    // Deleting problem is a special case: its dependent tables do not
                    // form a tree, and a delete to judging_run can only cascade from
                    // judging, not from testcase. Since MySQL does not define the
                    // order of cascading deletes, we need to manually first cascade
                    // via submission -> judging -> judging_run.
                    $entityManager->getConnection()->executeQuery(
                        'DELETE FROM submission WHERE probid = :probid',
                        [':probid' => $entity->getProbid()]
                    );
                    // Also delete internal errors that are "connected" to this problem.
                    $disabledJson = '{"kind":"problem","probid":' . $entity->getProbid() . '}';
                    $entityManager->getConnection()->executeQuery(
                        'DELETE FROM internal_error WHERE disabled = :disabled',
                        [':disabled' => $disabledJson]
                    );
                    $entityManager->clear();
                    $entity = $entityManager->getRepository(Problem::class)->find($entity->getProbid());
                }
                $entityManager->remove($entity);
            });

            // Add an audit log entry
            $auditLogType = Utils::tableForEntity($entity);
            $DOMJudgeService->auditlog($auditLogType, implode(', ', $primaryKeyData), 'deleted');

            // Trigger the delete event
            if ($endpoint = $eventLogService->endpointForEntity($entity)) {
                foreach ($contestsForEntity as $contest) {
                    // TODO: cascade deletes. Maybe use getDependentEntities()?
                    $eventLogService->log($endpoint, $primaryKeyData[0],
                        EventLogService::ACTION_DELETE,
                        $contest->getCid(), null, null, false);
                }
            }

            if ($entity instanceof Team) {
                // No need to do this in a transaction, since the chance of a team
                // with same ID being created at the same time is negligible.
                $entityManager->getConnection()->executeQuery(
                    'DELETE FROM scorecache WHERE teamid = :teamid',
                    [':teamid' => $entityId]
                );
                $entityManager->getConnection()->executeQuery(
                    'DELETE FROM rankcache WHERE teamid = :teamid',
                    [':teamid' => $entityId]
                );
            }

            $msg = sprintf('Successfully deleted %s %s "%s"',
                           $readableType, implode(', ', $primaryKeyData), $description);
            $this->addFlash('success', $msg);
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['url' => $redirectUrl]);
            } else {
                return $this->redirect($redirectUrl);
            }
        } else {
            $data = [
                'type' => $readableType,
                'primaryKey' => implode(', ', $primaryKeyData),
                'description' => $description,
                'messages' => $messages,
                'isError' => $isError,
                'showModalSubmit' => !$isError,
                'modalUrl' => $request->getRequestUri(),
                'redirectUrl' => $redirectUrl,
            ];
            if ($request->isXmlHttpRequest()) {
                return $this->render('jury/delete_modal.html.twig', $data);
            } else {
                return $this->render('jury/delete.html.twig', $data);
            }
        }
    }

    protected function getDependentEntities(string $entityClass, array $relations)
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
            if ($currentEntity != $entityClass) {
                $result[] = $currentEntity;
            }

            foreach ($relations as $nextEntity => $relatedEntities) {
                foreach ($relatedEntities as $constraint) {
                    if ($constraint['target'] == $currentEntity && $constraint['type'] == 'CASCADE') {
                        $queue[] = $nextEntity;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Get the contests that an event for the given entity should be triggered on
     * @param                 $entity
     * @param DOMJudgeService $dj
     *
     * @return Contest[]
     */
    protected function contestsForEntity($entity, DOMJudgeService $dj) {
        // Determine contests to emit an event for for the given entity:
        // * If the entity is a Problem entity, use the getContest()
        //   of every contest problem in getContestProblems()
        // * If the entity is a Team (category) entity, get all active
        //   contests and use those that are open to all teams or the
        //   team (category) belongs to
        // * If the entity is a contest, use that
        // * If the entity has a getContest() method, use that
        // * If the entity has a getContests() method, use that
        // Otherwise use the currently active contests
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
}
