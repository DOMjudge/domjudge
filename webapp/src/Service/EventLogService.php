<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Clarification;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Event;
use App\Entity\Judging;
use App\Entity\JudgingRun;
use App\Entity\Submission;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Entity\User;
use App\Utils\Utils;
use BadMethodCallException;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Expr\Join;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * This class is used to log events in the events table.
 */
class EventLogService implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    // Keys used in below config:
    const KEY_TYPE = 'type';
    const KEY_URL = 'url';
    const KEY_ENTITY = 'entity';
    const KEY_TABLES = 'tables';
    const KEY_USE_EXTERNAL_ID = 'use-external-id';
    const KEY_ALWAYS_USE_EXTERNAL_ID = 'always-use-external-id';
    const KEY_SKIP_IN_EVENT_FEED = 'skip-in-event-feed';

    // Types of endpoints:
    const TYPE_CONFIGURATION = 'configuration';
    const TYPE_LIVE = 'live';
    const TYPE_AGGREGATE = 'aggregate';

    // Allowed actions:
    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';

    // TODO: Add a way to specify when to use external ID using some (DB)
    // config instead of hardcoding it here. Also relates to
    // AbstractRestController::getIdField.
    public array $apiEndpoints = [
        'contests' => [
            self::KEY_TYPE => self::TYPE_CONFIGURATION,
            self::KEY_URL => '',
            self::KEY_USE_EXTERNAL_ID => true,
        ],
        'judgement-types' => [ // hardcoded in $VERDICTS and the API
            self::KEY_TYPE => self::TYPE_CONFIGURATION,
            self::KEY_ENTITY => null,
            self::KEY_TABLES => [],
        ],
        'languages' => [
            self::KEY_TYPE => self::TYPE_CONFIGURATION,
            self::KEY_USE_EXTERNAL_ID => true,
            self::KEY_ALWAYS_USE_EXTERNAL_ID => true,
        ],
        'problems' => [
            self::KEY_TYPE => self::TYPE_CONFIGURATION,
            self::KEY_TABLES => ['problem', 'contestproblem'],
            self::KEY_USE_EXTERNAL_ID => true,
        ],
        'groups' => [
            self::KEY_TYPE => self::TYPE_CONFIGURATION,
            self::KEY_ENTITY => TeamCategory::class,
            self::KEY_TABLES => ['team_category'],
            self::KEY_USE_EXTERNAL_ID => true,
        ],
        'organizations' => [
            self::KEY_TYPE => self::TYPE_CONFIGURATION,
            self::KEY_ENTITY => TeamAffiliation::class,
            self::KEY_TABLES => ['team_affiliation'],
            self::KEY_USE_EXTERNAL_ID => true,
        ],
        'teams' => [
            self::KEY_TYPE => self::TYPE_CONFIGURATION,
            self::KEY_TABLES => ['team', 'contestteam'],
            self::KEY_USE_EXTERNAL_ID => true,
        ],
        'state' => [
            self::KEY_TYPE => self::TYPE_AGGREGATE,
            self::KEY_ENTITY => null,
            self::KEY_TABLES => [],
        ],
        'submissions' => [
            self::KEY_TYPE => self::TYPE_LIVE,
            self::KEY_USE_EXTERNAL_ID => true,
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
            self::KEY_USE_EXTERNAL_ID => true,
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
        'accounts' => [
            self::KEY_TYPE => self::TYPE_CONFIGURATION,
            self::KEY_ENTITY => User::class,
            self::KEY_TABLES => ['user'],
            self::KEY_USE_EXTERNAL_ID => true,
            self::KEY_SKIP_IN_EVENT_FEED => true,
        ],
    ];

    // Entities to endpoints. Will be filled automatically except for special cases.
    protected array $entityToEndpoint = [
        // Special case for contest problems, as they should map to problems.
        ContestProblem::class => 'problems',
    ];

    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected EntityManagerInterface $em;
    protected LoggerInterface $logger;

    public function __construct(
        DOMJudgeService $dj,
        ConfigurationService $config,
        EntityManagerInterface $em,
        LoggerInterface $logger
    ) {
        $this->dj     = $dj;
        $this->config = $config;
        $this->em     = $em;
        $this->logger = $logger;

        foreach ($this->apiEndpoints as $endpoint => $data) {
            if (!array_key_exists(self::KEY_URL, $data)) {
                $this->apiEndpoints[$endpoint][self::KEY_URL] = '/' . $endpoint;
            }
            if (!array_key_exists(self::KEY_ENTITY, $data)) {
                // Determine default controller
                $inflector = InflectorFactory::create()->build();
                $singular  = $inflector->singularize($endpoint);
                $entity    = $inflector->classify($singular);
                $fullClass = sprintf('App\Entity\%s', $entity);
                if (!class_exists($fullClass)) {
                    throw new BadMethodCallException(
                        sprintf('Class \'%s\' does not exist', $fullClass)
                    );
                }
                $this->apiEndpoints[$endpoint][self::KEY_ENTITY] = $fullClass;
            }
            if (!array_key_exists(self::KEY_TABLES, $data)) {
                $this->apiEndpoints[$endpoint][self::KEY_TABLES] = [
                    preg_replace('/s$/', '', $endpoint)
                ];
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
     * @param string      $type        Either an API endpoint or a DB table
     * @param mixed       $dataIds     Identifier(s) of the row in the associated
     *                                 DB table as either one ID or an array of ID's.
     * @param string      $action      One of the self::ACTION_* constants
     * @param int|null    $contestId   Contest ID to log this event for. If null,
     *                                 log it for all currently active contests.
     * @param string|null $json        JSON content after the change. Generated if null.
     * @param mixed|null  $ids         Identifier(s) as shown in the REST API. If null it is
     *                                 inferred from the content in the database or $json
     *                                 passed as argument. Must be specified when deleting an
     *                                 entry or if no DB table is associated to $type.
     *                                 Can be null, one ID or an array of ID's.
     * @param bool        $checkEvents If true, check for missing dependent and contest
     *                                 state events and insert them
     * @throws NonUniqueResultException
     */
    public function log(
        string $type,
        $dataIds,
        string $action,
        ?int $contestId = null,
        ?string $json = null,
        $ids = null,
        bool $checkEvents = true
    ) {
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
        $dataidsCombined = $this->dj->jsonEncode($dataIds);
        $idsCombined     = $ids === null ? null : (is_array($ids) ? $this->dj->jsonEncode($ids) : $ids);

        $this->logger->debug(
            "EventLogService::log arguments: '%s' '%s' '%s' '%s' '%s' '%s'",
            [ $type, $dataidsCombined, $action, $contestId, $json, $idsCombined ]
        );

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
            $this->logger->warning(
                "EventLogService::log: invalid endpoint '%s' specified", [ $type ]
            );
            return;
        }
        if (!in_array($action, [self::ACTION_CREATE, self::ACTION_UPDATE, self::ACTION_DELETE])) {
            $this->logger->warning(
                "EventLogService::log: invalid action '%s' specified", [ $action ]
            );
            return;
        }
        if ($endpoint[self::KEY_URL] === null) {
            $this->logger->warning(
                "EventLogService::log: no endpoint for '%s', ignoring", [ $type ]
            );
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

        $idsCombined = $ids === null ? null : (is_array($ids) ? $this->dj->jsonEncode($ids) : $ids);

        // State is a special case, as it works without an ID
        if ($type !== 'state' && count(array_filter($ids)) !== count($dataIds)) {
            $this->logger->warning(
                "EventLogService::log API ID not specified or inferred ".
                "from data for type %s and data ID's '%s'",
                [ $type, $this->dj->jsonEncode($dataIds) ]
            );
            return;
        }

        // Make sure ID arrays are 0-indexed.
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
                        ->from(ContestProblem::class, 'cp')
                        ->select('DISTINCT(cp.contest) AS contestId')
                        ->andWhere('cp.problem = :problem')
                        ->setParameter('problem', $dataId)
                        ->getQuery()
                        ->getScalarResult();
                    $contestIdsForId = array_map(fn(array $data) => $data['contestId'], $contestIdData);
                    $expectedEvents  += count($contestIdsForId);
                    $contestIds      = array_unique(array_merge($contestIds, $contestIdsForId));
                }
            } elseif ($type === 'teams') {
                $expectedEvents = 0;
                foreach ($dataIds as $dataId) {
                    $contests        = $this->dj->getCurrentContests($dataId);
                    $contestIdsForId = array_map(fn(Contest $contest) => $contest->getCid(), $contests);
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
                $contestIds     = array_map(fn(Contest $contest) => $contest->getCid(), $contests);
                $expectedEvents = count($dataIds) * count($contestIds);
            }
        }

        if (count($contestIds) === 0) {
            $this->logger->info('EventLogService::log no active contests associated to update.');
            return;
        }

        // Generate JSON content if not set, for deletes this is only the ID.
        if ($action === self::ACTION_DELETE) {
            $json = array_values(array_map(fn($id) => ['id' => (string)$id], $ids));
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
                $this->logger->warning(
                    "EventLogService::log got no JSON data from '%s'", [ $url ]
                );
                // If we didn't get data from the API, then that is probably
                // because this particular data is not visible, for example
                // because it belongs to an invisible jury team. If we don't
                // have data, there's also no point in trying to insert
                // anything in the eventlog table.
                return;
            }
        }

        // Explicitly construct the time as string to prevent float
        // representation issues.
        $now = sprintf('%.3f', microtime(true));

        if ($jsonPassed) {
            $json = $this->dj->jsonDecode($json);
        } elseif (!in_array($type, ['contests', 'state'])) {
            // Re-index JSON so we can look up the elements by ID.
            $tmp  = $json;
            $json = [];
            foreach ($tmp as $item) {
                $json[$item['id']] = $item;
            }
        }

        // TODO: can this be wrapped into a single query?
        $events = [];
        foreach ($contestIds as $contestId) {
            foreach ($dataIds as $idx => $dataId) {
                $contest = $this->em->getRepository(Contest::class)->find($contestId);

                if (in_array($type, ['contests', 'state']) || $jsonPassed) {
                    // Contest and state endpoint are singular.
                    $jsonElement = $json;
                } elseif (isset($json[$ids[$idx]])) {
                    $jsonElement = $json[$ids[$idx]];
                } else {
                    continue;
                }

                if ($checkEvents) {
                    // Check if all references for this event are present; if not, add all static data.
                    if ($action !== static::ACTION_DELETE && !$this->hasAllDependentObjectEvents($contest, $type, $jsonElement)) {
                        // Not all dependent objects are present, so insert all static events.
                        $this->initStaticEvents($contest);
                        // If new references are added, we need to reload the contest,
                        // because the entity manager has been cleared.
                        $contest = $this->em->getRepository(Contest::class)->find($contest->getCid());
                    }

                    // Add missing state events that should have happened already.
                    $this->addMissingStateEvents($contest);
                }

                $event = new Event();
                $event
                    ->setEventtime($now)
                    ->setContest($contest)
                    ->setEndpointtype($type)
                    ->setEndpointid((string)$ids[$idx])
                    ->setAction($action)
                    ->setContent($jsonElement);
                $this->em->persist($event);
                $events[] = $event;
            }
        }

        // Now flush the entity manager, inserting all events.
        $this->em->flush();

        if (count($events) !== $expectedEvents) {
            $this->logger->warning(
                "EventLogService::log failed to %s %s with ID's %s (%d/%d events done)",
                [ $action, $type, $idsCombined, count($events), $expectedEvents ]
            );
        }

        $this->logger->debug(
            "EventLogService::log %sd %s with ID's %s for %d contest(s)",
            [ $action, $type, $idsCombined, count($contestIds) ]
        );
    }

    /**
     * Add all state events for the given contest that are not added yet but
     * should have happened already.
     */
    public function addMissingStateEvents(Contest $contest): void
    {
        // Make sure we get a fresh contest.
        $this->em->refresh($contest);

        // Because some states can happen in multiple different orders, we need to check per
        // field to see if we have a state event where that field matches the current value.
        $states = [
            'started' => $contest->getStarttime(),
            'ended' => $contest->getEndtime(),
            'frozen' => $contest->getFreezetime(),
            'thawed' => $contest->getUnfreezetime(),
            'finalized' => $contest->getFinalizetime(),
        ];

        // Because we have the events in order now, we can keep 'growing' the data to insert,
        // because every next state event will have the data from the previous one, together with
        // the new data.
        $dataToInsert = [];

        foreach ($states as $state => $time) {
            $dataToInsert[$state] = null;
        }

        $dataToInsert['end_of_updates'] = null;

        // First, remove all times that are still null or will happen in the future,
        // as we do not need to check them.
        $states = array_filter($states, fn($time) => $time !== null && Utils::now() >= $time);

        // Now sort the remaining times in increasing order,
        // as that is the order in which we want to add the events.
        asort($states);

        // Get all state events.
        /** @var Event[] $stateEvents */
        $stateEvents = $this->em->createQueryBuilder()
            ->from(Event::class, 'e')
            ->select('e')
            ->andWhere('e.contest = :contest')
            ->andWhere('e.endpointtype = :state')
            ->setParameter('contest', $contest)
            ->setParameter('state', 'state')
            ->orderBy('e.eventid')
            ->getQuery()
            ->getResult();

        $dataPresent = [];
        foreach ($stateEvents as $stateEvent) {
            foreach ($stateEvent->getContent() as $field => $value) {
                if ($value !== null) {
                    $dataPresent[$field] = true;
                }
            }
        }

        // Now loop over the states and check if we already have an event for them.
        foreach ($states as $field => $time) {
            $dataToInsert[$field] = Utils::absTime($time);

            // If we already have an event with this field, continue.
            if (isset($dataPresent[$field])) {
                continue;
            }

            // Insert the event.
            $this->insertEvent($contest, 'state', '', $dataToInsert);

            if ($field === 'finalized') {
                // Insert all awards events.
                $url = sprintf('/contests/%s/awards', $contest->getApiId($this));
                $awards = [];
                $this->dj->withAllRoles(function () use ($url, &$awards) {
                    $awards = $this->dj->internalApiRequest($url);
                });
                foreach ($awards as $award) {
                    $this->insertEvent($contest, 'awards', $award['id'], $award);
                }
            }
        }

        // If we already have an event with end_of_updates, we are done.
        if (isset($dataPresent['end_of_updates'])) {
            return;
        }

        // Special case, if both thawed and finalized are non-null or finalized is
        // non-null but frozen, we also need to add an end_of_updates event.
        if ($dataToInsert['finalized'] !== null &&
            ($dataToInsert['thawed'] !== null || $dataToInsert['frozen'] === null)) {
            $dataToInsert['end_of_updates'] = max(
                $dataToInsert['thawed'] ?? Utils::absTime(0),
                $dataToInsert['finalized']
            );

            // Insert the end_of_updates event.
            $this->insertEvent($contest, 'state', '', $dataToInsert);
        }
    }

    /**
     * Insert the given events, if it doesn't exist yet.
     *
     * This method will make sure that the events are all only inserted once,
     * even if called simultaneously from different processes.
     *
     * @throws NonUniqueResultException
     */
    protected function insertEvents(
        Contest $contest,
        string $endpointType,
        array $endpointIds,
        array $contents
    ): void {
        if (empty($endpointIds)) {
            return;
        }

        $events = [];
        $firstEndpointId = null;
        foreach ($endpointIds as $index => $endpointId) {
            $event = new Event();
            $event
                ->setEventtime(Utils::now())
                ->setContest($contest)
                ->setEndpointtype($endpointType)
                ->setEndpointid($endpointId)
                ->setContent($contents[$index]);
            $events[] = $event;
            if ($firstEndpointId === null) {
                $firstEndpointId = $endpointId;
            }
        }

        // Note that for events without an ID (i.e. state), the endpointid
        // is set to ''. This means this call will also work for these
        // kinds of events.
        $existingEvents = $this->getExistingEvents($events);

        foreach ($events as $event) {
            // If we have no event or the data is different, add it.
            $existingEvent = $existingEvents[$event->getEndpointid()] ?? null;
            $existingData = $existingEvent === null ?
                null :
                $this->dj->jsonEncode($existingEvent->getContent());
            $data = $this->dj->jsonEncode($event->getContent());
            if ($existingEvent === null || $existingData !== $data) {
                // Special case for state: this is always an update event
                if ($endpointType === 'state') {
                    $event->setAction(self::ACTION_UPDATE);
                } else {
                    // Set the action based on whether there was already an event
                    // for the same endpoint and ID.
                    $event->setAction(
                        $existingEvent === null ?
                            self::ACTION_CREATE :
                            self::ACTION_UPDATE
                    );
                }
                $this->em->persist($event);
            }
        }
        $this->em->flush();
    }

    /**
     * Insert the given event, if it doesn't exist yet.
     *
     * This method will make sure that the event is only inserted once,
     * even if called simultaneously from different processes.
     *
     * @throws NonUniqueResultException
     */
    protected function insertEvent(
        Contest $contest,
        string $endpointType,
        string $endpointId,
        array $content
    ): void {
        $this->insertEvents($contest, $endpointType, [$endpointId], [$content]);
    }

    /**
     * Add all static events that are still missing. Static events are events for endpoints that
     * are marked as 'Configuration' on
     * https://ccs-specs.icpc.io/2021-11/contest_api#types-of-endpoints
     *
     * @throws NonUniqueResultException
     */
    public function initStaticEvents(Contest $contest): void
    {
        // Loop over all configuration endpoints with an URL and check if we have all data.
        foreach ($this->apiEndpoints as $endpoint => $endpointData) {
            if ($endpointData[static::KEY_SKIP_IN_EVENT_FEED] ?? false) {
                continue;
            }
            if ($endpointData[EventLogService::KEY_TYPE] === EventLogService::TYPE_CONFIGURATION &&
                isset($endpointData[EventLogService::KEY_URL])) {
                $contestId = $contest->getApiId($this);

                // Do an internal API request to the overview URL
                // of the endpoint to get current data.
                $url = sprintf('/contests/%s%s', $contestId,
                               $endpointData[EventLogService::KEY_URL]);
                $this->dj->withAllRoles(function () use ($url, &$data) {
                    $data = $this->dj->internalApiRequest($url);
                });

                // Get a partial reference to the contest,
                // because calling internalApiRequest above will clear the entity manager.
                /** @var Contest $contest */
                $contest = $this->em->getPartialReference(Contest::class, $contest->getCid());

                if ($data === null) {
                    throw new Exception(sprintf("EventLogService::initializeStaticEvents no response data for endpoint '%s'.",
                                                $endpoint));
                }

                // Special case 'contests' since it is a single object:
                if ($endpoint === 'contests') {
                    if (!is_array($data) || is_numeric(array_keys($data)[0])) {
                        throw new Exception(sprintf("EventLogService::initializeStaticEvents Endpoint '%s' did not return a JSON object.",
                                                    $endpoint));
                    }

                    $this->insertEvent($contest, $endpoint, $data['id'], $data);
                    continue;
                }

                if (!is_array($data) || (!empty($data) && !is_numeric(array_keys($data)[0]))) {
                    throw new Exception(sprintf("EventLogService::initializeStaticEvents Endpoint '%s' did not return a JSON list.",
                                                $endpoint));
                }

                foreach ($data as $i => $row) {
                    if (!is_array($row) || is_numeric(array_keys($row)[0])) {
                        throw new Exception(sprintf("EventLogService::initializeStaticEvents Endpoint '%s' did not return a JSON object for index %d.",
                                                    $endpoint, $i));
                    }

                    if (!isset($row['id'])) {
                        throw new Exception(sprintf("EventLogService::initializeStaticEvents Endpoint '%s' did not return an `id` field for index %d.",
                                                    $endpoint, $i));
                    }
                }

                // Sort the data on ID to get a consistent order.
                usort($data, function ($a, $b) {
                    if (is_int($a['id']) && is_int($b['id'])) {
                        return $a['id'] <=> $b['id'];
                    }
                    return strcmp((string)$a['id'], (string)$b['id']);
                });

                // Insert the events.
                $ids = array_map(function (array $row) {
                    return $row['id'];
                }, $data);
                $this->insertEvents($contest, $endpoint, $ids, $data);
            }
        }
    }

    /**
     * Check if all events for dependent objects are present for the given type and data.
     * @return bool True if and only if all references are present
     */
    protected function hasAllDependentObjectEvents(Contest $contest, string $type, array $data): bool
    {
        // Build up the referenced data to check for.
        $toCheck = [
            'contests' => $contest->getApiId($this),
        ];
        switch ($type) {
            case 'teams':
                $toCheck = array_merge($toCheck, [
                    'organizations' => $data['organization_id'],
                    'groups' => $data['group_ids'],
                ]);
                break;
            case 'submissions':
                $toCheck = array_merge($toCheck, [
                    'languages' => $data['language_id'],
                    'problems' => $data['problem_id'],
                    'teams' => $data['team_id'],
                ]);
                break;
            case 'judgements':
            case 'runs':
                $toCheck = array_merge($toCheck, [
                    'judgement-types' => $data['judgement_type_id'],
                ]);
                break;
            case 'clarifications':
                $toCheck = array_merge($toCheck, [
                    'teams' => [$data['from_team_id'], $data['to_team_id']],
                    'problems' => $data['problem_id'],
                ]);
                break;
        }

        // Now check every reference to see if we have an event for it
        foreach ($toCheck as $endpointType => $endpointIds) {
            // Make sure we always use arrays to check, even if we have a single value
            $endpointIds = (array)$endpointIds;
            foreach ($endpointIds as $endpointId) {
                if ($endpointId === null) {
                    continue;
                }

                // Now check if we have at least one event for the given
                // endpoint type and ID for the current contest.
                $numExistingEvents = (int)$this->em->createQueryBuilder()
                    ->from(Event::class, 'e')
                    ->select('COUNT(e)')
                    ->andWhere('e.contest = :contest')
                    ->andWhere('e.endpointtype = :endpoint')
                    ->andWhere('e.endpointid = :endpointid')
                    ->setParameter('contest', $contest)
                    ->setParameter('endpoint', $endpointType)
                    ->setParameter('endpointid', $endpointId)
                    ->getQuery()
                    ->getSingleScalarResult();

                if ($numExistingEvents === 0) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get the existing events with the same API data as these events,
     * indexed by event endpoint ID. Note that this function expects at least
     * one event and all events should have the same contest and endpoint.
     *
     * @param Event[] $events
     * @return Event[]
     */
    protected function getExistingEvents(array $events): array
    {
        $endpointIds = array_map(function (Event $event) {
            return $event->getEndpointid();
        }, $events);
        /** @var Event[] $events */
        $events = $this->em->createQueryBuilder()
            ->from(Event::class, 'e', 'e.endpointid')
            ->leftJoin(Event::class, 'e2', Join::WITH,
                'e2.contest = e.contest AND e2.endpointtype = e.endpointtype AND e2.endpointid = e.endpointid AND e2.eventid > e.eventid'
            )
            ->select('e')
            ->andWhere('e.contest = :contest')
            ->andWhere('e.endpointtype = :endpoint')
            ->andWhere('e.endpointid IN (:endpointids)')
            ->andWhere('e2.eventid IS NULL')
            ->setParameter('contest', $events[0]->getContest())
            ->setParameter('endpoint', $events[0]->getEndpointtype())
            ->setParameter('endpointids', $endpointIds)
            ->orderBy('e.eventid', 'DESC')
            ->getQuery()
            ->getResult();

        return array_filter($events, function (Event $event) {
            return $event->getAction() !== self::ACTION_DELETE;
        });
    }

    /**
     * Convert the given internal ID's into external ID's usable by the API
     */
    protected function getExternalIds(string $type, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $endpointData = $this->apiEndpoints[$type];
        if (!isset($endpointData[self::KEY_USE_EXTERNAL_ID])) {
            return $ids;
        }

        $entity = $endpointData[self::KEY_ENTITY];
        if (!$entity) {
            throw new BadMethodCallException(sprintf('No entity defined for type \'%s\'', $type));
        }

        // Special case for submissions and clarifications: they can have an external ID even if when running in
        // full local mode, because one can use the API to upload one with an external ID.
        $externalIdAlwaysAllowed = [
            Submission::class    => 's.submitid',
            Clarification::class => 'clar.clarid',
        ];
        if (isset($externalIdAlwaysAllowed[$entity])) {
            $fullField = $externalIdAlwaysAllowed[$entity];
            [$table, $field] = explode('.', $fullField);
            return array_map(fn(array $item) => $item['externalid'] ?? $item[$field], $this->em->createQueryBuilder()
                ->from($entity, $table)
                ->select($fullField, sprintf('%s.externalid', $table))
                ->andWhere(sprintf('%s IN (:ids)', $fullField))
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getScalarResult());
        }

        if (!$this->externalIdFieldForEntity($entity)) {
            return $ids;
        }

        $metadata = $this->em->getClassMetadata($entity);
        try {
            $primaryKeyField = $metadata->getSingleIdentifierColumnName();
        } catch (MappingException $e) {
            throw new BadMethodCallException(sprintf('Entity \'%s\' as a composite primary key',
                                                      $type));
        }

        return array_map(
            fn(array $item) => $item['externalid'],
            $this->em->createQueryBuilder()
               ->from($entity, 'e')
               ->select('e.externalid')
               ->andWhere(sprintf('e.%s IN (:ids)', $primaryKeyField))
               ->setParameter('ids', $ids)
               ->getQuery()
               ->getScalarResult()
        );
    }

    /**
     * Get the external ID field for a given entity type. Will return null if
     * no external ID field should be used.
     * @param object|string $entity
     */
    public function externalIdFieldForEntity($entity): ?string
    {
        // Allow passing in a class instance: convert it to its class type.
        if (is_object($entity)) {
            $entity = get_class($entity);
        }
        // Special case: strip of Doctrine proxies.
        if (strpos($entity, 'Proxies\\__CG__\\') === 0) {
            $entity = substr($entity, strlen('Proxies\\__CG__\\'));
        }

        if (!isset($this->entityToEndpoint[$entity])) {
            throw new BadMethodCallException(sprintf('Entity \'%s\' does not have a corresponding endpoint',
                                                      $entity));
        }

        $endpointData = $this->apiEndpoints[$this->entityToEndpoint[$entity]];

        if (!isset($endpointData[self::KEY_USE_EXTERNAL_ID])) {
            return null;
        }

        $useExternalId = false;
        if ($endpointData[self::KEY_ALWAYS_USE_EXTERNAL_ID] ?? false) {
            $useExternalId = true;
        } else {
            $dataSource = $this->config->get('data_source');

            if ($dataSource !== DOMJudgeService::DATA_SOURCE_LOCAL) {
                $endpointType = $endpointData[self::KEY_TYPE];
                if ($endpointType === self::TYPE_CONFIGURATION &&
                    in_array($dataSource, [
                        DOMJudgeService::DATA_SOURCE_CONFIGURATION_EXTERNAL,
                        DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL
                    ])) {
                    $useExternalId = true;
                } elseif ($endpointType === self::TYPE_LIVE &&
                    $dataSource === DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL) {
                    $useExternalId = true;
                }
            }
        }

        if ($useExternalId) {
            return 'externalid';
        } else {
            return null;
        }
    }

    /**
     * Get the API ID field for a given entity type.
     * @param object|string $entity
     */
    public function apiIdFieldForEntity($entity): string
    {
        if ($field = $this->externalIdFieldForEntity($entity)) {
            return $field;
        }
        $class    = is_object($entity) ? get_class($entity) : $entity;
        $metadata = $this->em->getClassMetadata($class);
        try {
            return $metadata->getSingleIdentifierFieldName();
        } catch (MappingException $e) {
            throw new BadMethodCallException("Entity '$class' has a composite primary key");
        }
    }

    /**
     * Get the endpoint to use for the given entity.
     * @param object|string $entity
     */
    public function endpointForEntity($entity): ?string
    {
        // Allow passing in a class instance: convert it to its class type.
        if (is_object($entity)) {
            $entity = get_class($entity);
        }
        // Special case: strip of Doctrine proxies.
        if (strpos($entity, 'Proxies\\__CG__\\') === 0) {
            $entity = substr($entity, strlen('Proxies\\__CG__\\'));
        }

        if (isset($this->entityToEndpoint[$entity])) {
            return $this->entityToEndpoint[$entity];
        }

        return null;
    }
}
