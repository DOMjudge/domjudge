<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Event;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ImportExportService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use JMS\Serializer\Metadata\PropertyMetadata;
use Metadata\MetadataFactoryInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Inflector\Inflector;
use Symfony\Component\Yaml\Yaml;

/**
 * @Rest\Route("/api/v4/contests", defaults={ "_format" = "json" })
 * @Rest\Prefix("/api/contests")
 * @Rest\NamePrefix("contest_")
 * @SWG\Tag(name="Contests")
 * @SWG\Response(response="404", ref="#/definitions/NotFound")
 * @SWG\Response(response="401", ref="#/definitions/Unauthorized")
 */
class ContestController extends AbstractRestController
{
    /**
     * @var ImportExportService
     */
    protected $importExportService;

    /**
     * @param EntityManagerInterface $entityManager
     * @param DOMJudgeService        $dj
     * @param ConfigurationService   $config
     * @param EventLogService        $eventLogService
     * @param ImportExportService    $importExportService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService,
        ImportExportService $importExportService
    ) {
        parent::__construct($entityManager, $dj, $config, $eventLogService);
        $this->importExportService = $importExportService;
    }

    /**
     * Add one or more contests.
     * @param Request $request
     * @return string
     * @Rest\Post("")
     * @IsGranted("ROLE_ADMIN")
     * @SWG\Post(consumes={"multipart/form-data"})
     * @SWG\Parameter(
     *     name="yaml",
     *     in="formData",
     *     type="file",
     *     required=true,
     *     description="The contest.yaml files to import."
     * )
     * @SWG\Response(
     *     response="200",
     *     description="Returns a (currently meaningless) status message.",
     * )
     * @throws BadRequestHttpException
     */
    public function addContestAction(Request $request)
    {
        /** @var UploadedFile $yamlFile */
        $yamlFile = $request->files->get('yaml') ?: [];
        $data = Yaml::parseFile($yamlFile->getRealPath(), Yaml::PARSE_DATETIME);
        if ($this->importExportService->importContestYaml($data, $message, $cid)) {
            return $cid;
        } else {
            throw new BadRequestHttpException("Error while adding contest: $message");
        }
    }

    /**
     * Get all the active contests
     * @param Request $request
     * @return Response
     * @Rest\Get("")
     * @SWG\Response(
     *     response="200",
     *     description="Returns all the active contests",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(ref=@Model(type=Contest::class))
     *     )
     * )
     * @SWG\Parameter(ref="#/parameters/idlist")
     * @SWG\Parameter(ref="#/parameters/strict")
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function listAction(Request $request)
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given contest
     * @param Request $request
     * @param string  $cid
     * @return Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @Rest\Get("/{cid}")
     * @SWG\Response(
     *     response="200",
     *     description="Returns the given contest",
     *     @Model(type=Contest::class)
     * )
     * @SWG\Parameter(ref="#/parameters/cid")
     * @SWG\Parameter(ref="#/parameters/strict")
     */
    public function singleAction(Request $request, string $cid)
    {
        return parent::performSingleAction($request, $cid);
    }

    /**
     * Change the start time of the given contest
     * @Rest\Patch("/{cid}")
     * @IsGranted("ROLE_API_WRITER")
     * @param Request $request
     * @param string  $cid
     * @return Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     * @SWG\Parameter(
     *     name="cid",
     *     in="path",
     *     type="string",
     *     description="The ID of the contest to change the start time for"
     * )
     * @SWG\Parameter(
     *     name="id",
     *     in="formData",
     *     type="string",
     *     description="The ID of the contest to change the start time for",
     *     required=true
     * )
     * @SWG\Parameter(
     *     name="start_time",
     *     in="formData",
     *     type="string",
     *     format="date-time",
     *     description="The new start time of the contest",
     *     required=false,
     *     allowEmptyValue=true
     * )
     * @SWG\Response(
     *     response="200",
     *     description="Contest start time changed successfully",
     * )
     * @SWG\Response(
     *     response="400",
     *     description="Invalid input data"
     * )
     * @SWG\Response(
     *     response="403",
     *     description="Changing start time not allowed"
     * )
     */
    public function changeStartTimeAction(Request $request, string $cid)
    {
        $contest  = $this->getContestWithId($request, $cid);
        $response = null;
        $now      = Utils::now();
        $changed  = false;
        if (!$request->request->has('id')) {
            $response = new JsonResponse('Missing "id" in request.', Response::HTTP_BAD_REQUEST);
        } elseif (!$request->request->has('start_time')) {
            $response = new JsonResponse('Missing "start_time" in request.', Response::HTTP_BAD_REQUEST);
        } elseif ($request->request->get('id') != $contest->getApiId($this->eventLogService)) {
            $response = new JsonResponse('Invalid "id" in request.', Response::HTTP_BAD_REQUEST);
        } elseif (!$request->request->has('force') &&
            $contest->getStarttime() != null &&
            $contest->getStarttime() < $now + 30) {
            $response = new JsonResponse('Current contest already started or about to start.',
                                         Response::HTTP_FORBIDDEN);
        } elseif ($request->request->get('start_time') === null) {
            $this->em->persist($contest);
            $contest->setStarttimeEnabled(false);
            $response = new JsonResponse('Contest paused :-/.', Response::HTTP_OK);
            $this->em->flush();
            $changed = true;
        } else {
            $date = date_create($request->request->get('start_time'));
            if ($date === false) {
                $response = new JsonResponse('Invalid "start_time" in request.', Response::HTTP_BAD_REQUEST);
            } else {
                $new_start_time = $date->getTimestamp();
                if (!$request->request->get('force') && $new_start_time < $now + 30) {
                    $response = new JsonResponse('New start_time not far enough in the future.',
                                                 Response::HTTP_FORBIDDEN);
                } else {
                    $this->em->persist($contest);
                    $newStartTimeString = date('Y-m-d H:i:s e', $new_start_time);
                    $contest->setStarttimeEnabled(true);
                    $contest->setStarttime($new_start_time);
                    $contest->setStarttimeString($newStartTimeString);
                    $response = new JsonResponse('Contest start time changed to ' . $newStartTimeString,
                                                 Response::HTTP_OK);
                    $this->em->flush();
                    $changed = true;
                }
            }
        }

        if ($changed) {
            $this->eventLogService->log('contests', $contest->getCid(), EventLogService::ACTION_UPDATE,
                                        $contest->getCid());
        }

        return $response;
    }

    /**
     * Get the contest in YAML format
     * @Rest\Get("/{cid}/contest-yaml")
     * @SWG\Get(produces={"application/x-yaml"})
     * @param Request $request
     * @param string  $cid
     * @return StreamedResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     * @SWG\Parameter(ref="#/parameters/cid")
     * @SWG\Response(
     *     response="200",
     *     description="The contest in YAML format"
     * )
     */
    public function getContestYamlAction(Request $request, string $cid)
    {
        $contest      = $this->getContestWithId($request, $cid);
        $penalty_time = $this->config->get('penalty_time');
        $response     = new StreamedResponse();
        $response->setCallback(function () use ($contest, $penalty_time) {
            echo "name:                     " . $contest->getName() . "\n";
            echo "short-name:               " . $contest->getExternalid() . "\n";
            echo "start-time:               " .
                Utils::absTime($contest->getStarttime(), true) . "\n";
            echo "duration:                 " .
                Utils::relTime($contest->getEndtime() - $contest->getStarttime(), true) . "\n";
            echo "scoreboard-freeze-length: " .
                Utils::relTime($contest->getEndtime() - $contest->getFreezetime(), true) . "\n";
            echo "penalty-time:             " . $penalty_time . "\n";
        });
        $response->headers->set('Content-Type', 'application/x-yaml');
        $response->headers->set('Content-Disposition', 'attachment; filename="contest.yaml"');
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        $response->headers->set('Connection', 'Keep-Alive');
        $response->headers->set('Accept-Ranges', 'bytes');

        return $response;
    }

    /**
     * Get the current contest state
     * @Rest\Get("/{cid}/state")
     * @param Request $request
     * @param string  $cid
     * @return array|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @SWG\Parameter(ref="#/parameters/cid")
     * @SWG\Response(
     *     response="200",
     *     description="The contest state",
     *     @SWG\Schema(ref="#/definitions/ContestState")
     * )
     */
    public function getContestStateAction(Request $request, string $cid)
    {
        $contest         = $this->getContestWithId($request, $cid);
        $inactiveAllowed = $this->isGranted('ROLE_API_READER');
        if (($inactiveAllowed && $contest->getEnabled()) || (!$inactiveAllowed && $contest->isActive())) {
            return $contest->getState();
        } else {
            throw new AccessDeniedHttpException();
        }
    }

    /**
     * Get the event feed for the given contest
     * @Rest\Get("/{cid}/event-feed")
     * @SWG\Get(produces={"application/x-ndjson"})
     * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_API_READER')")
     * @param Request                  $request
     * @param string                   $cid
     * @param MetadataFactoryInterface $metadataFactory
     * @param KernelInterface          $kernel
     * @return Response|StreamedResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @SWG\Parameter(ref="#/parameters/cid")
     * @SWG\Parameter(
     *     name="since_id",
     *     in="query",
     *     type="string",
     *     description="Only get events after this event"
     * )
     * @SWG\Parameter(
     *     name="types",
     *     in="query",
     *     type="array",
     *     description="Types to filter the event feed on",
     *     @SWG\Items(type="string", description="A single type")
     * )
     * @SWG\Parameter(
     *     name="strict",
     *     in="query",
     *     type="boolean",
     *     description="Whether to only include CCS compliant properties in the response",
     *     default="false"
     * )
     * @SWG\Parameter(
     *     name="stream",
     *     in="query",
     *     type="boolean",
     *     description="Whether to stream the output or stop immediately",
     *     default="true"
     * )
     * @SWG\Response(
     *     response="200",
     *     description="The events",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(
     *             type="object",
     *             @SWG\Property(property="id", type="string"),
     *             @SWG\Property(property="type", type="string"),
     *             @SWG\Property(property="op", type="string"),
     *             @SWG\Property(property="data", type="object"),
     *             @SWG\Property(property="time", type="string", format="date-time"),
     *         )
     *     )
     * )
     */
    public function getEventFeedAction(
        Request $request,
        string $cid,
        MetadataFactoryInterface $metadataFactory,
        KernelInterface $kernel
    ) {
        $contest = $this->getContestWithId($request, $cid);
        // Make sure this script doesn't hit the PHP maximum execution timeout.
        set_time_limit(0);
        if ($request->query->has('since_id')) {
            $since_id = $request->query->getInt('since_id');
            $event    = $this->em->getRepository(Event::class)->findOneBy([
                'eventid' => $since_id,
                'cid' => $contest->getCid(),
            ]);
            if ($event === null) {
                return new Response('Invalid parameter "since_id" requested.', Response::HTTP_BAD_REQUEST);
            }
        } else {
            $since_id = -1;
        }

        $response = new StreamedResponse();
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Content-Type', 'application/x-ndjson');
        $response->setCallback(function () use ($cid, $contest, $request, $since_id, $metadataFactory, $kernel) {
            $lastUpdate = 0;
            $lastIdSent = $since_id;
            $typeFilter = false;
            if ($request->query->has('types')) {
                $typeFilter = explode(',', $request->query->get('types'));
            }
            $strict     = $request->query->getBoolean('strict', false);
            $stream     = $request->query->getBoolean('stream', true);
            $canViewAll = $this->isGranted('ROLE_API_READER');

            $skippedProperties = [];
            // Determine which properties we should not send out for strict clients.
            // We do this here instead of every loop to speed up sending events at
            // the cost of sending out the first byte a bit slower.
            if ($strict) {
                $toCheck = [];
                $dir   = realpath($kernel->getProjectDir() . '/src/Entity');
                $files = glob($dir . '/*.php');
                foreach ($files as $file) {
                    $parts      = explode('/', $file);
                    $shortClass = str_replace('.php', '', $parts[count($parts) - 1]);
                    $class      = sprintf('App\\Entity\\%s', $shortClass);
                    if (class_exists($class)) {
                        $plural = strtolower(Inflector::pluralize($shortClass));
                        $toCheck[$plural] = $class;
                    }
                }

                // Change some specific endpoints that do not map to our own objects.
                $toCheck['problems'] = ContestProblem::class;
                $toCheck['groups'] = $toCheck['teamcategories'];
                $toCheck['organizations'] = $toCheck['teamaffiliations'];
                unset($toCheck['teamcategories']);
                unset($toCheck['teamaffiliations']);
                unset($toCheck['contestproblems']);

                foreach ($toCheck as $plural => $class) {
                    $serializerMetadata = $metadataFactory->getMetadataForClass($class);
                    /** @var PropertyMetadata $propertyMetadata */
                    foreach ($serializerMetadata->propertyMetadata as $propertyMetadata) {
                        if (is_array($propertyMetadata->groups) &&
                            !in_array('Default', $propertyMetadata->groups)) {
                            $skippedProperties[$plural][] = $propertyMetadata->serializedName;
                        }
                    }
                }
            }

            // Initialize all static events
            $this->eventLogService->initStaticEvents($contest);
            // Reload the contest as the above method will clear the entity manager
            $contest = $this->getContestWithId($request, $cid);

            while (true) {
                // Add missing state events that should have happened already
                $this->eventLogService->addMissingStateEvents($contest);

                $qb = $this->em->createQueryBuilder()
                    ->from(Event::class, 'e')
                    ->select('e')
                    ->andWhere('e.eventid > :lastIdSent')
                    ->setParameter('lastIdSent', $lastIdSent)
                    ->andWhere('e.cid = :cid')
                    ->setParameter('cid', $contest->getCid())
                    ->orderBy('e.eventid', 'ASC');

                if ($typeFilter !== false) {
                    $qb = $qb
                        ->andWhere('e.endpointtype IN (:types)')
                        ->setParameter(':types', $typeFilter);
                }
                if (!$canViewAll) {
                    $restricted_types = ['judgements', 'runs', 'clarifications'];
                    if ($contest->getStarttime() === null || Utils::now() < $contest->getStarttime()) {
                        $restricted_types[] = 'problems';
                    }
                    $qb = $qb
                        ->andWhere('e.endpointtype NOT IN (:restricted_types)')
                        ->setParameter(':restricted_types', $restricted_types);
                }

                $q = $qb->getQuery();

                $events = $q->getResult();
                /** @var Event $event */
                foreach ($events as $event) {
                    $data = $event->getContent();
                    // Filter fields with specific access restrictions.
                    if (!$canViewAll) {
                        if ($event->getEndpointtype() == 'submissions') {
                            unset($data['entry_point']);
                            unset($data['language_id']);
                        }
                        if ($event->getEndpointtype() == 'problems') {
                            unset($data['test_data_count']);
                        }
                    }
                    if ($strict) {
                        $toSkip = $skippedProperties[$event->getEndpointtype()] ?? [];
                        foreach ($toSkip as $property) {
                            unset($data[$property]);
                        }
                    }
                    $result = array(
                        'id' => (string)$event->getEventid(),
                        'type' => (string)$event->getEndpointtype(),
                        'op' => (string)$event->getAction(),
                        'data' => $data,
                    );
                    if (!$strict) {
                        $result['time'] = Utils::absTime($event->getEventtime());
                    }
                    echo json_encode($result, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES) . "\n";
                    ob_flush();
                    flush();
                    $lastUpdate = Utils::now();
                    $lastIdSent = $event->getEventid();
                }

                if (count($events) == 0) {
                    if (!$stream) {
                        break;
                    }
                    // No new events, check if it's time for a keep alive.
                    $now = Utils::now();
                    if ($lastUpdate + 10 < $now) {
                        # Send keep alive every 10s. Guarantee according to spec is 120s.
                        # However, nginx drops the connection if we don't update for 60s.
                        echo "\n";
                        ob_flush();
                        flush();
                        $lastUpdate = $now;
                    }
                    # Sleep for little while before checking for new events.
                    usleep(500 * 1000);
                }
            }
        });
        return $response;
    }

    /**
     * Get general status information
     * @Rest\Get("/{cid}/status")
     * @IsGranted("ROLE_API_READER")
     * @SWG\Parameter(ref="#/parameters/cid")
     * @SWG\Response(
     *     response="200",
     *     description="General status information for the given contest",
     *     @SWG\Schema(
     *         type="object",
     *         @SWG\Property(property="num_submissions", type="integer"),
     *         @SWG\Property(property="num_queued", type="integer"),
     *         @SWG\Property(property="num_judging", type="integer")
     *     )
     * )
     * @param Request $request
     * @param string  $cid
     * @return array
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getStatusAction(Request $request, string $cid)
    {
        return $this->dj->getContestStats($this->getContestWithId($request, $cid));
    }

    /**
     * @inheritdoc
     */
    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        return $this->getContestQueryBuilder();
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    protected function getIdField(): string
    {
        return sprintf('c.%s', $this->eventLogService->externalIdFieldForEntity(Contest::class) ?? 'cid');
    }

    /**
     * Get the contest with the given ID
     * @param Request $request
     * @param string  $id
     * @return Contest
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    protected function getContestWithId(Request $request, string $id): Contest
    {
        $queryBuilder = $this->getQueryBuilder($request)
            ->andWhere(sprintf('%s = :id', $this->getIdField()))
            ->setParameter(':id', $id);

        $contest = $queryBuilder->getQuery()->getOneOrNullResult();

        if ($contest === null) {
            throw new NotFoundHttpException(sprintf('Contest with ID \'%s\' not found', $id));
        }

        return $contest;
    }
}
