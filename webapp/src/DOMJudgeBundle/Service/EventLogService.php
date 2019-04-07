<?php declare(strict_types=1);

namespace DOMJudgeBundle\Service;

use Doctrine\Common\Inflector\Inflector;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\MappingException;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\ContestProblem;
use DOMJudgeBundle\Entity\Event;
use DOMJudgeBundle\Entity\Judging;
use DOMJudgeBundle\Entity\JudgingRun;
use DOMJudgeBundle\Entity\TeamAffiliation;
use DOMJudgeBundle\Entity\TeamCategory;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * This class is used to log events in the events table
 */
class EventLogService implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    // Keys used in below config
    const KEY_TYPE = 'type';
    const KEY_URL = 'url';
    const KEY_ENTITY = 'entity';
    const KEY_TABLES = 'tables';
    const KEY_EXTERNAL_ID = 'extid';
    const KEY_ALWAYS_USE_EXTERNAL_ID = 'always-use-external-id';

    // Types of endpoints
    const TYPE_CONFIGURATION = 'configuration';
    const TYPE_LIVE = 'live';
    const TYPE_AGGREGATE = 'aggregate';

    // Allowed actions
    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';

    // TODO: add a way to specify when to use external ID using some (DB)
    // config instead of hardcoding it here. Also relates to
    // AbstractRestController::getIdField
    public $apiEndpoints = [
        'contests' => [
            self::KEY_TYPE => self::TYPE_CONFIGURATION,
            self::KEY_URL => '',
            self::KEY_EXTERNAL_ID => 'externalid',
        ],
        'judgement-types' => [ // hardcoded in $VERDICTS and the API
            self::KEY_TYPE => self::TYPE_CONFIGURATION,
            self::KEY_ENTITY => null,
            self::KEY_TABLES => [],
        ],
        'languages' => [
            self::KEY_TYPE => self::TYPE_CONFIGURATION,
            self::KEY_EXTERNAL_ID => 'externalid',
            self::KEY_ALWAYS_USE_EXTERNAL_ID => true,
        ],
        'problems' => [
            self::KEY_TYPE => self::TYPE_CONFIGURATION,
            self::KEY_TABLES => ['problem', 'contestproblem'],
            self::KEY_EXTERNAL_ID => 'externalid',
        ],
        'groups' => [
            self::KEY_TYPE => self::TYPE_CONFIGURATION,
            self::KEY_ENTITY => TeamCategory::class,
            self::KEY_TABLES => ['team_category'],
        ],
        'organizations' => [
            self::KEY_TYPE => self::TYPE_CONFIGURATION,
            self::KEY_ENTITY => TeamAffiliation::class,
            self::KEY_TABLES => ['team_affiliation'],
            self::KEY_EXTERNAL_ID => 'externalid',
        ],
        'teams' => [
            self::KEY_TYPE => self::TYPE_CONFIGURATION,
            self::KEY_TABLES => ['team', 'contestteam'],
        ],
        'state' => [
            self::KEY_TYPE => self::TYPE_AGGREGATE,
            self::KEY_ENTITY => null,
            self::KEY_TABLES => [],
        ],
        'submissions' => [
            self::KEY_TYPE => self::TYPE_LIVE,
            self::KEY_EXTERNAL_ID => 'externalid',
        ],
        'judgements' => [
            self::KEY_TYPE => self::TYPE_LIVE,
            self::KEY_ENTITY => Judging::class,
            self::KEY_TABLES => ['judging'],
        ],
        'runs' => [
            self::KEY_TYPE => self::TYPE_LIVE,
            self::KEY_ENTITY => JudgingRun::class,
            self::KEY_TABLES => ['judging_run'],
        ],
        'clarifications' => [
            self::KEY_TYPE => self::TYPE_LIVE,
            self::KEY_EXTERNAL_ID => 'externalid',
        ],
        'awards' => [
            self::KEY_TYPE => self::TYPE_AGGREGATE,
            self::KEY_ENTITY => null,
            self::KEY_TABLES => [],
        ],
        'scoreboard' => [
            self::KEY_TYPE => self::TYPE_AGGREGATE,
            self::KEY_ENTITY => null,
            self::KEY_TABLES => [],
        ],
        'event-feed' => [
            self::KEY_TYPE => self::TYPE_AGGREGATE,
            self::KEY_ENTITY => null,
            self::KEY_TABLES => ['event'],
        ],
    ];

    // Entities to endpoints. Will be filled automatically except for special cases
    protected $entityToEndpoint = [
        // Special case for contest problems, as they should map to problems
        ContestProblem::class => 'problems',
    ];

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(
        DOMJudgeService $dj,
        EntityManagerInterface $em,
        LoggerInterface $logger
    ) {
        $this->dj     = $dj;
        $this->em     = $em;
        $this->logger = $logger;

        foreach ($this->apiEndpoints as $endpoint => $data) {
            if (!array_key_exists(self::KEY_URL, $data)) {
                $this->apiEndpoints[$endpoint][self::KEY_URL] = '/' . $endpoint;
            }
            if (!array_key_exists(self::KEY_ENTITY, $data)) {
                // Determine default controller
                $singular  = Inflector::singularize($endpoint);
                $entity    = Inflector::classify($singular);
                $fullClass = sprintf('DOMJudgeBundle\Entity\%s', $entity);
                if (!class_exists($fullClass)) {
                    throw new \BadMethodCallException(sprintf('Class \'%s\' does not exist', $fullClass));
                }
                $this->apiEndpoints[$endpoint][self::KEY_ENTITY] = $fullClass;
            }
            if (!array_key_exists(self::KEY_TABLES, $data)) {
                $this->apiEndpoints[$endpoint][self::KEY_TABLES] = [preg_replace('/s$/', '', $endpoint)];
            }

            // Make sure we have a fast way to look up endpoints for entities
            if (isset($this->apiEndpoints[$endpoint][self::KEY_ENTITY])) {
                $this->entityToEndpoint[$this->apiEndpoints[$endpoint][self::KEY_ENTITY]] = $endpoint;
            }
        }
    }

    /**
     * Log an event
     *
     * @param string      $type      Either an API endpoint or a DB table
     * @param mixed       $dataIds   Identifier(s) of the row in the associated
     *                               DB table as either one ID or an array of ID's.
     * @param string      $action    One of the self::ACTION_* constants
     * @param int|null    $contestId Contest ID to log this event for. If null,
     *                               log it for all currently active contests.
     * @param string|null $json      JSON content after the change. Generated if null.
     * @param mixed|null  $ids       Identifier(s) as shown in the REST API. If null it is
     *                               inferred from the content in the database or $json
     *                               passed as argument. Must be specified when deleting an
     *                               entry or if no DB table is associated to $type.
     *                               Can be null, one ID or an array of ID's.
     * @throws Exception
     */
    public function log(string $type, $dataIds, string $action, $contestId = null, $json = null, $ids = null)
    {
        // Sanitize and check input
        if (!is_array($dataIds)) {
            $dataIds = [$dataIds];
        }

        if (count($dataIds) > 1 && isset($ids)) {
            $this->logger->warning("EventLogService::log: passing multiple dataid's " .
                                   "while also passing one or more ID's not allowed yet");
            return;
        }

        if (count($dataIds) > 1 && isset($json)) {
            $this->logger->warning("EventLogService::log: passing multiple dataid's " .
                                   "while also passing a JSON object not allowed yet");
            return;
        }

        // Keep track of whether JSON was passed, as we need this later on
        $jsonPassed = isset($json);

        // Make a combined string to keep track of the data ID's
        $dataidsCombined = json_encode($dataIds);
        $idsCombined     = $ids === null ? null : is_array($ids) ? json_encode($ids) : $ids;

        $this->logger->debug(sprintf("EventLogService::log arguments: '%s' '%s' '%s' '%s' '%s' '%s'",
                                     $type, $dataidsCombined, $action, $contestId, $json, $idsCombined));


        // Gracefully fail since we may call this from the generic
        // jury/edit.php page where we don't know which table gets updated.
        if (array_key_exists($type, $this->apiEndpoints)) {
            $endpoint = $this->apiEndpoints[$type];
        } else {
            foreach ($this->apiEndpoints as $key => $ep) {
                if (in_array($type, $ep[self::KEY_TABLES], true)) {
                    $type     = $key;
                    $endpoint = $ep;
                    break;
                }
            }
        }

        if (!isset($endpoint)) {
            $this->logger->warning(sprintf("EventLogService::log: invalid endpoint '%s' specified", $type));
            return;
        }
        if (!in_array($action, [self::ACTION_CREATE, self::ACTION_UPDATE, self::ACTION_DELETE])) {
            $this->logger->warning(sprintf("EventLogService::log: invalid action '%s' specified", $action));
            return;
        }
        if ($endpoint[self::KEY_URL] === null) {
            $this->logger->warning(sprintf("EventLogService::log: no endpoint for '%s', ignoring", $type));
            return;
        }

        // Look up external/API ID from various sources.
        if ($ids === null) {
            $ids = $this->getExternalIds($type, $dataIds);
        }

        if ($ids === [null] && $json !== null) {
            $data = $this->dj->jsonDecode($json);
            if (!empty($data['id'])) {
                $ids = [$data['id']];
            }
        }

        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $idsCombined = $ids === null ? null : is_array($ids) ? json_encode($ids) : $ids;

        // State is a special case, as it works without an ID
        if ($type !== 'state' && count(array_filter($ids)) !== count($dataIds)) {
            $this->logger->warning(
                sprintf("EventLogService::log API ID not specified or inferred from data for type %s and data ID's '%s'",
                        $type, json_encode($dataIds)
                )
            );
            return;
        }

        // Make sure ID arrays are 0-indexed
        $dataIds = array_values($dataIds);
        $ids     = array_values($ids);

        $contestIds = [];
        if ($contestId !== null) {
            $contestIds[]   = $contestId;
            $expectedEvents = count($dataIds);
        } else {
            if ($type === 'problems') {
                $expectedEvents = 0;
                foreach ($dataIds as $dataId) {
                    $contestIdData   = $this->em->createQueryBuilder()
                        ->from('DOMJudgeBundle:ContestProblem', 'cp')
                        ->select('DISTINCT(cp.cid) AS contestId')
                        ->andWhere('cp.probid = :probid')
                        ->setParameter(':probid', $dataId)
                        ->getQuery()
                        ->getScalarResult();
                    $contestIdsForId = array_map(function (array $data) {
                        return $data['contestId'];
                    }, $contestIdData);
                    $expectedEvents  += count($contestIdsForId);
                    $contestIds      = array_unique(array_merge($contestIds, $contestIdsForId));
                }
            } elseif ($type === 'teams') {
                $expectedEvents = 0;
                foreach ($dataIds as $dataId) {
                    $contests        = $this->dj->getCurrentContests($dataId);
                    $contestIdsForId = array_map(function (Contest $contest) {
                        return $contest->getCid();
                    }, $contests);
                    $expectedEvents  += count($contestIdsForId);
                    $contestIds      = array_unique(array_merge($contestIds, $contestIdsForId));
                }
            } elseif ($type === 'contests') {
                $contestIds     = $dataIds;
                $expectedEvents = count($dataIds);
                if (count($contestIds) > 1) {
                    $this->logger->warning('EventLogService::log cannot handle multiple contests in single request');
                    return;
                }
                $contestId = $contestIds[0];
            } else {
                $contests       = $this->dj->getCurrentContests();
                $contestIds     = array_map(function (Contest $contest) {
                    return $contest->getCid();
                }, $contests);
                $expectedEvents = count($dataIds) * count($contestIds);
            }
        }

        if (count($contestIds) === 0) {
            $this->logger->info('EventLogService::log no active contests associated to update.');
            return;
        }

        // Generate JSON content if not set, for deletes this is only the ID.
        if ($action === self::ACTION_DELETE) {
            $json = array_values(array_map(function ($id) {
                return ['id' => $id];
            }, $ids));
        } elseif ($json === null) {
            $url = $endpoint[self::KEY_URL];

            // Temporary fix for single/multi contest API:
            if (isset($contestId)) {
                $externalContestIds = $this->getExternalIds('contests', [$contestId]);
                $url                = '/contests/' . reset($externalContestIds) . $url;
            }

            if (in_array($type, ['contests', 'state'])) {
                $query = [];
            } else {
                $query = ['ids' => $ids];
            }

            $this->dj->withAllRoles(function () use ($query, $url, &$json) {
                $json = $this->dj->internalApiRequest($url, Request::METHOD_GET, $query);
            });

            if ($json === null) {
                $this->logger->warning(sprintf("EventLogService::log got no JSON data from '%s'", $url));
                // If we didn't get data from the API, then that is probably
                // because this particular data is not visible, for example
                // because it belongs to an invisible jury team. If we don't
                // have data, there's also no point in trying to insert
                // anything in the eventlog table.
                return;
            }
        }

        // First acquire an advisory lock to prevent other event logging,
        // so that we can obtain a unique timestamp.
        if ($this->em->getConnection()->fetchColumn("SELECT GET_LOCK('domjudge.eventlog',1)") != 1) {
            throw new \Exception('EventLogService::log failed to obtain lock');
        }

        // Explicitly construct the time as string to prevent float
        // representation issues.
        $now = sprintf('%.3f', microtime(true));

        if ($jsonPassed) {
            $json = $this->dj->jsonDecode($json);
        }

        // TODO: can this be wrapped into a single query?
        $events = [];
        foreach ($contestIds as $contestId) {
            $table = ($endpoint[self::KEY_TABLES] ? $endpoint[self::KEY_TABLES][0] : null);
            foreach ($dataIds as $idx => $dataId) {
                if (in_array($type, ['contests', 'state']) || $jsonPassed) {
                    // Contest and state endpoint are singular
                    $jsonElement = $json;
                } else {
                    $jsonElement = $json[$idx];
                }
                $event = new Event();
                $event
                    ->setEventtime($now)
                    ->setContest($this->em->getRepository(Contest::class)->find($contestId))
                    ->setEndpointtype($type)
                    ->setEndpointid($ids[$idx])
                    ->setDatatype($table)
                    ->setDataid($dataId)
                    ->setAction($action)
                    ->setContent($jsonElement);
                $this->em->persist($event);
                $events[] = $event;
            }
        }

        // Now flush the entity manager, inserting all events
        $this->em->flush();

        if ($this->em->getConnection()->fetchColumn("SELECT RELEASE_LOCK('domjudge.eventlog')") != 1) {
            throw new \Exception('EventLogService::log failed to release lock');
        }

        if (count($events) !== $expectedEvents) {
            throw new \Exception(sprintf("EventLogService::log failed to %s %s with ID's %s (%d/%d events done)",
                                         $action, $type, $idsCombined, count($events), $expectedEvents));
        }

        $this->logger->debug(sprintf("EventLogService::log %sd %s with ID's %s for %d contest(s)",
                                     $action, $type, $idsCombined, count($contestIds)));
    }

    /**
     * Convert the given internal ID's into external ID's usable by the API
     * @param string $type
     * @param array  $ids
     * @return array
     * @throws Exception
     */
    protected function getExternalIds(string $type, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $endpointData = $this->apiEndpoints[$type];
        if (!isset($endpointData[self::KEY_EXTERNAL_ID])) {
            return $ids;
        }

        $entity = $endpointData[self::KEY_ENTITY];
        if (!$entity) {
            throw new \BadMethodCallException(sprintf('No entity defined for type \'%s\'', $type));
        }

        if (!$this->externalIdFieldForEntity($entity)) {
            return $ids;
        }

        $metadata = $this->em->getClassMetadata($entity);
        try {
            $primaryKeyField = $metadata->getSingleIdentifierColumnName();
        } catch (MappingException $e) {
            throw new \BadMethodCallException(sprintf('Entity \'%s\' as a composite primary key', $type));
        }

        return array_map(function (array $item) use ($endpointData) {
            return $item[$endpointData[self::KEY_EXTERNAL_ID]];
        }, $this->em->createQueryBuilder()
               ->from($entity, 'e')
               ->select(sprintf('e.%s', $endpointData[self::KEY_EXTERNAL_ID]))
               ->andWhere(sprintf('e.%s IN (:ids)', $primaryKeyField))
               ->setParameter(':ids', $ids)
               ->getQuery()
               ->getScalarResult());
    }

    /**
     * Get the external ID field for a given entity type. Will return null if
     * no external ID field should be used
     * @param object|string $entity
     * @return string|null
     * @throws Exception
     */
    public function externalIdFieldForEntity($entity)
    {
        // Allow to pass in a class instance: convert it to its class type
        if (is_object($entity)) {
            $entity = get_class($entity);
        }
        // Special case: strip of Doctrine proxies
        if (strpos($entity, 'Proxies\\__CG__\\') === 0) {
            $entity = substr($entity, strlen('Proxies\\__CG__\\'));
        }

        if (!isset($this->entityToEndpoint[$entity])) {
            throw new \BadMethodCallException(sprintf('Entity \'%s\' does not have a corresponding endpoint', $entity));
        }

        $endpointData = $this->apiEndpoints[$this->entityToEndpoint[$entity]];

        if (!isset($endpointData[self::KEY_EXTERNAL_ID])) {
            return null;
        }

        $lookupExternalid = false;
        if ($endpointData[self::KEY_ALWAYS_USE_EXTERNAL_ID] ?? false) {
            $lookupExternalid = true;
        } else {
            $dataSource = $this->dj->dbconfig_get('data_source', DOMJudgeService::DATA_SOURCE_LOCAL);

            if ($dataSource !== DOMJudgeService::DATA_SOURCE_LOCAL) {
                $endpointType = $endpointData[self::KEY_TYPE];
                if ($endpointType === self::TYPE_CONFIGURATION &&
                    in_array($dataSource, [
                        DOMJudgeService::DATA_SOURCE_CONFIGURATION_EXTERNAL,
                        DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL
                    ])) {
                    $lookupExternalid = true;
                } elseif ($endpointType === self::TYPE_LIVE &&
                    $dataSource === DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL) {
                    $lookupExternalid = true;
                }
            }
        }

        if ($lookupExternalid) {
            return $endpointData[self::KEY_EXTERNAL_ID];
        } else {
            return null;
        }
    }

    /**
     * Get the API ID field for a given entity type.
     * @param object $entity
     * @return string
     * @throws Exception
     */
    public function apiIdFieldForEntity($entity)
    {
        if ($field = $this->externalIdFieldForEntity($entity)) {
            return $field;
        }
        $class = get_class($entity);
        $metadata = $this->em->getClassMetadata($class);
        try {
            return $metadata->getSingleIdentifierFieldName();
        } catch (MappingException $e) {
            throw new \BadMethodCallException("Entity '$class' has a composite primary key");
        }
    }

    /**
     * Get the endpoint to use for the given entity
     * @param object|string $entity
     * @return string|null
     */
    public function endpointForEntity($entity)
    {
        // Allow to pass in a class instance: convert it to its class type
        if (is_object($entity)) {
            $entity = get_class($entity);
        }
        // Special case: strip of Doctrine proxies
        if (strpos($entity, 'Proxies\\__CG__\\') === 0) {
            $entity = substr($entity, strlen('Proxies\\__CG__\\'));
        }

        if (isset($this->entityToEndpoint[$entity])) {
            return $this->entityToEndpoint[$entity];
        }

        return null;
    }
}
