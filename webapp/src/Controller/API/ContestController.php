<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Event;
use App\Service\AssetUpdateService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ImportExportService;
use App\Utils\Utils;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use JMS\Serializer\Metadata\PropertyMetadata;
use Metadata\MetadataFactoryInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Yaml\Yaml;
use TypeError;

#[Rest\Route('/contests')]
#[OA\Tag(name: 'Contests')]
#[OA\Parameter(ref: '#/components/parameters/strict')]
#[OA\Response(ref: '#/components/responses/InvalidResponse', response: 400)]
#[OA\Response(ref: '#/components/responses/Unauthenticated', response: 401)]
#[OA\Response(ref: '#/components/responses/Unauthorized', response: 403)]
#[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
class ContestController extends AbstractRestController
{
    final public const EVENT_FEED_FORMAT_2020_03 = 0;
    final public const EVENT_FEED_FORMAT_2022_07 = 1;

    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService,
        protected readonly ImportExportService $importExportService,
        protected readonly AssetUpdateService $assetUpdater
    ) {
        parent::__construct($entityManager, $dj, $config, $eventLogService);
    }

    /**
     * Add a new contest.
     * @throws BadRequestHttpException
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Rest\Post('')]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(
                        property: 'yaml',
                        description: 'The contest.yaml file to import.',
                        type: 'string',
                        format: 'binary'
                    ),
                    new OA\Property(
                        property: 'json',
                        description: 'The contest.json file to import.',
                        type: 'string',
                        format: 'binary'
                    ),
                ]))
    )]
    #[OA\Response(response: 200, description: 'Returns the API ID of the added contest.')]
    public function addContestAction(Request $request): string
    {
        /** @var UploadedFile $yamlFile */
        $yamlFile = $request->files->get('yaml') ?: [];
        /** @var UploadedFile $jsonFile */
        $jsonFile = $request->files->get('json') ?: [];
        if ((!$yamlFile && !$jsonFile) || ($yamlFile && $jsonFile)) {
            throw new BadRequestHttpException('Supply exactly one of \'json\' or \'yaml\'');
        }
        $message = null;
        if ($yamlFile) {
            $data = Yaml::parseFile($yamlFile->getRealPath(), Yaml::PARSE_DATETIME);
            if ($this->importExportService->importContestData($data, $message, $cid)) {
                return $cid;
            }
        } elseif ($jsonFile) {
            $data = $this->dj->jsonDecode(file_get_contents($jsonFile->getRealPath()));
            if ($this->importExportService->importContestData($data, $message, $cid)) {
                return $cid;
            }
        }
        throw new BadRequestHttpException("Error while adding contest: $message");
    }

    /**
     * Get all the contests.
     * @throws NonUniqueResultException
     */
    #[Rest\Get('')]
    #[OA\Response(
        response: 200,
        description: 'Returns all contests visible to the user (all contests for privileged users, active contests otherwise)',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                allOf: [
                    new OA\Schema(ref: new Model(type: Contest::class)),
                    new OA\Schema(ref: '#/components/schemas/Banner'),
                ]
            )
        )
    )]
    #[OA\Parameter(ref: '#/components/parameters/idlist')]
    #[OA\Parameter(
        name: 'onlyActive',
        description: 'Whether to only return data pertaining to contests that are active',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false)
    )]
    public function listAction(Request $request): Response
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given contest.
     * @throws NonUniqueResultException
     */
    #[Rest\Get('/{cid}')]
    #[OA\Response(
        response: 200,
        description: 'Returns the given contest',
        content: new OA\JsonContent(
            allOf: [
                new OA\Schema(ref: new Model(type: Contest::class)),
                new OA\Schema(ref: '#/components/schemas/Banner'),
            ]
        )
    )]
    #[OA\Parameter(ref: '#/components/parameters/cid')]
    public function singleAction(Request $request, string $cid): Response
    {
        return parent::performSingleAction($request, $cid);
    }

    /**
     * Get the banner for the given contest.
     */
    #[Rest\Get('/{cid}/banner', name: 'contest_banner')]
    #[OA\Response(
        response: 200,
        description: 'Returns the given contest banner in PNG, JPG or SVG format',
        content: [
            new OA\MediaType(mediaType: 'image/png'),
            new OA\MediaType(mediaType: 'image/jpeg'),
            new OA\MediaType(mediaType: 'image/svg+xml'),
        ]
    )]
    #[OA\Parameter(ref: '#/components/parameters/cid')]
    public function bannerAction(Request $request, string $cid): Response
    {
        /** @var Contest $contest */
        $contest = $this->getQueryBuilder($request)
            ->andWhere(sprintf('%s = :id', $this->getIdField()))
            ->setParameter('id', $cid)
            ->getQuery()
            ->getOneOrNullResult();

        if ($contest === null) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $cid));
        }

        $banner = $this->dj->assetPath($cid, 'contest', true);

        if ($banner && file_exists($banner)) {
            return static::sendBinaryFileResponse($request, $banner);
        }
        throw new NotFoundHttpException('Contest banner not found');
    }

    /**
     * Delete the banner for the given contest.
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Rest\Delete('/{cid}/banner', name: 'delete_contest_banner')]
    #[OA\Response(response: 204, description: 'Deleting banner succeeded')]
    #[OA\Parameter(ref: '#/components/parameters/cid')]
    public function deleteBannerAction(Request $request, string $cid): Response
    {
        /** @var Contest $contest */
        $contest = $this->getQueryBuilder($request)
            ->andWhere(sprintf('%s = :id', $this->getIdField()))
            ->setParameter('id', $cid)
            ->getQuery()
            ->getOneOrNullResult();

        if ($contest === null) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $cid));
        }

        if ($contest->isLocked()) {
            $contestUrl = $this->generateUrl('jury_contest', ['contestId' => $cid], UrlGeneratorInterface::ABSOLUTE_URL);
            throw new AccessDeniedHttpException('Contest is locked, go to ' . $contestUrl . ' to unlock it.');
        }

        $contest->setClearBanner(true);

        $this->assetUpdater->updateAssets($contest);
        $this->eventLogService->log('contests', $contest->getCid(), EventLogService::ACTION_UPDATE,
            $contest->getCid());

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Set the banner for the given contest.
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Rest\Post("/{cid}/banner", name: 'post_contest_banner')]
    #[Rest\Put("/{cid}/banner", name: 'put_contest_banner')]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['banner'],
                properties: [
                    new OA\Property(
                        property: 'banner',
                        description: 'The banner to use.',
                        type: 'string',
                        format: 'binary'
                    ),
                ]
            )
        )
    )]
    #[OA\Response(response: 204, description: 'Setting banner succeeded')]
    #[OA\Parameter(ref: '#/components/parameters/cid')]
    public function setBannerAction(Request $request, string $cid, ValidatorInterface $validator): Response
    {
        /** @var Contest $contest */
        $contest = $this->getQueryBuilder($request)
            ->andWhere(sprintf('%s = :id', $this->getIdField()))
            ->setParameter('id', $cid)
            ->getQuery()
            ->getOneOrNullResult();

        if ($contest === null) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $cid));
        }

        if ($contest->isLocked()) {
            $contestUrl = $this->generateUrl('jury_contest', ['contestId' => $cid], UrlGeneratorInterface::ABSOLUTE_URL);
            throw new AccessDeniedHttpException('Contest is locked, go to ' . $contestUrl . ' to unlock it.');
        }

        /** @var UploadedFile $banner */
        $banner = $request->files->get('banner');

        if (!$banner) {
            return new JsonResponse(['title' => 'Validation failed', 'errors' => ['Please supply a banner']], Response::HTTP_BAD_REQUEST);
        }

        $contest->setBannerFile($banner);

        if ($errorResponse = $this->responseForErrors($validator->validate($contest), true)) {
            return $errorResponse;
        }

        $this->assetUpdater->updateAssets($contest);
        $this->eventLogService->log('contests', $contest->getCid(), EventLogService::ACTION_UPDATE,
            $contest->getCid());

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Change the start time or unfreeze (thaw) time of the given contest.
     * @throws NonUniqueResultException
     */
    #[IsGranted('ROLE_API_WRITER')]
    #[Rest\Patch('/{cid}')]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['id'],
                properties: [
                    new OA\Property(
                        property: 'id',
                        description: 'The ID of the contest to change the start time for',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'start_time',
                        description: 'The new start time of the contest',
                        type: 'string',
                        format: 'date-time'
                    ),
                    new OA\Property(
                        property: 'scoreboard_thaw_time',
                        description: 'The new unfreeze (thaw) time of the contest',
                        type: 'string',
                        format: 'date-time'
                    ),
                    new OA\Property(
                        property: 'force',
                        description: '"Force overwriting the start_time even when in next 30s or the scoreboard_thaw_time when already set or too much in the past"',
                        type: 'boolean'
                    ),
                ]
            )
        )
    )]
    #[OA\Response(
        response: 204,
        description: 'The change was successful'
    )]
    #[OA\Response(
        response: 200,
        description: 'Contest start time changed successfully',
        content: new OA\JsonContent(allOf: [
            new OA\Schema(ref: new Model(type: Contest::class)),
            new OA\Schema(ref: '#/components/schemas/Banner'),
        ])
    )]
    public function changeStartTimeAction(
        Request $request,
        #[OA\PathParameter(description: 'The ID of the contest to change the start time for')]
        string $cid
    ): Response {
        $contest  = $this->getContestWithId($request, $cid);
        $now      = (int)Utils::now();
        $changed  = false;
        if (!$request->request->has('id')) {
            throw new BadRequestHttpException('Missing "id" in request.');
        }
        if (!$request->request->has('start_time') && !$request->request->has('scoreboard_thaw_time')) {
            throw new BadRequestHttpException('Missing "start_time" or "scoreboard_thaw_time" in request.');
        }
        if ($request->request->get('id') != $contest->getApiId($this->eventLogService)) {
            throw new BadRequestHttpException('Invalid "id" in request.');
        }
        if ($request->request->has('start_time') && $request->request->has('scoreboard_thaw_time')) {
            throw new BadRequestHttpException('Setting both "start_time" and "scoreboard_thaw_time" at the same time is not allowed.');
        }

        if ($request->request->has('start_time')) {
            // By default, it is not allowed to change the start time in the last 30 seconds before contest start.
            // We allow the "force" parameter to override this.
            if (!$request->request->getBoolean('force') &&
                $contest->getStarttime() != null &&
                $contest->getStarttime() < $now + 30) {
                throw new AccessDeniedHttpException('Current contest already started or about to start.');
            }

            if ($request->request->get('start_time') === null) {
                $contest->setStarttimeEnabled(false);
                $response = new Response('', Response::HTTP_NO_CONTENT);
                $this->em->flush();
                $changed = true;
            } else {
                $date = date_create($request->request->get('start_time'));
                if ($date === false) {
                    throw new BadRequestHttpException('Invalid "start_time" in request.');
                }

                $new_start_time = $date->getTimestamp();
                if (!$request->request->getBoolean('force') && $new_start_time < $now + 30) {
                    throw new AccessDeniedHttpException('New start_time not far enough in the future.');
                }
                $newStartTimeString = date('Y-m-d H:i:s e', $new_start_time);
                $contest->setStarttimeEnabled(true);
                $contest->setStarttime($new_start_time);
                $contest->setStarttimeString($newStartTimeString);
                $response = new Response('', Response::HTTP_NO_CONTENT);
                $this->em->flush();
                $changed = true;
            }
        }
        if ($request->request->has('scoreboard_thaw_time')) {
            if (!$request->request->getBoolean('force') && $contest->getUnfreezetime() !== null) {
                throw new AccessDeniedHttpException('Current contest already has an unfreeze time set.');
            }

            $date = date_create($request->request->get('scoreboard_thaw_time') ?? 'not a valid date');
            if ($date === false) {
                throw new BadRequestHttpException('Invalid "scoreboard_thaw_time" in request.');
            }

            $new_unfreeze_time = $date->getTimestamp();
            if (!$request->request->getBoolean('force') && $new_unfreeze_time < $now - 30) {
                throw new AccessDeniedHttpException('New scoreboard_thaw_time too far in the past.');
            }

            $returnContest = false;
            if ($new_unfreeze_time < $now) {
                $new_unfreeze_time = $now;
                $returnContest = true;
            }
            $newUnfreezeTimeString = date('Y-m-d H:i:s e', $new_unfreeze_time);
            $contest->setUnfreezetime($new_unfreeze_time);
            $contest->setUnfreezetimeString($newUnfreezeTimeString);
            $this->em->flush();
            $changed = true;
            $response = new Response('', Response::HTTP_NO_CONTENT);
            if ($returnContest) {
                $response = $this->renderData($request, $contest);
            }
        }

        if ($changed) {
            $this->eventLogService->log('contests', $contest->getCid(), EventLogService::ACTION_UPDATE,
                                        $contest->getCid());
        }

        return $response;
    }

    /**
     * Get the contest in YAML format.
     * @throws NonUniqueResultException
     */
    #[Rest\Get('/{cid}/contest-yaml')]
    #[OA\Parameter(ref: '#/components/parameters/cid')]
    #[OA\Response(
        response: 200,
        description: 'The contest in YAML format',
        content: new OA\MediaType(mediaType: 'application/x-yaml')
    )]
    public function getContestYamlAction(Request $request, string $cid): StreamedResponse
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
     * @throws NonUniqueResultException
     */
    #[Rest\Get('/{cid}/state')]
    #[OA\Parameter(ref: '#/components/parameters/cid')]
    #[OA\Response(
        response: 200,
        description: 'The contest state',
        content: new OA\JsonContent(ref: '#/components/schemas/ContestState')
    )]
    public function getContestStateAction(Request $request, string $cid): ?array
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
     * Get the event feed for the given contest.
     * @throws NonUniqueResultException
     */
    #[IsGranted(new Expression("is_granted('ROLE_JURY') or is_granted('ROLE_API_READER')"))]
    #[Rest\Get('/{cid}/event-feed')]
    #[OA\Parameter(ref: '#/components/parameters/cid')]
    #[OA\Parameter(
        name: 'since_id',
        description: 'Only get events after this event',
        in: 'query',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'types',
        description: 'Types to filter the event feed on',
        in: 'query',
        schema: new OA\Schema(
            type: 'array',
            items: new OA\Items(description: 'A single type', type: 'string')
        )
    )]
    #[OA\Parameter(
        name: 'stream',
        description: 'Whether to stream the output or stop immediately',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: true)
    )]
    #[OA\Response(
        response: 200,
        description: 'The events',
        content: new OA\MediaType(
            mediaType: 'application/x-ndjson',
            schema: new OA\Schema(
                type: 'array',
                items: new OA\Items(
                    properties: [
                        new OA\Property(
                            property: 'id',
                            type: 'string'
                        ),
                        new OA\Property(
                            property: 'type',
                            type: 'string'
                        ),
                        new OA\Property(
                            property: 'op',
                            type: 'string'
                        ),
                        new OA\Property(
                            property: 'data',
                            type: 'object'
                        ),
                        new OA\Property(
                            property: 'time',
                            type: 'string',
                            format: 'date-time'
                        ),
                    ],
                    type: 'object'
                )
            )
        )
    )]
    public function getEventFeedAction(
        Request $request,
        string $cid,
        #[Autowire(service: 'jms_serializer.metadata_factory')]
        MetadataFactoryInterface $metadataFactory,
        KernelInterface $kernel
    ): Response {
        $contest = $this->getContestWithId($request, $cid);
        // Make sure this script doesn't hit the PHP maximum execution timeout.
        set_time_limit(0);

        if ($request->query->has('since_token') || $request->query->has('since_id')) {
            $since_id = (int)$request->query->get('since_token', $request->query->get('since_id'));
            $event    = $this->em->getRepository(Event::class)->findOneBy([
                'eventid' => $since_id,
                'contest' => $contest,
            ]);
            if ($event === null) {
                throw new BadRequestHttpException(
                    sprintf(
                        'Invalid parameter "%s" requested.',
                        $request->query->has('since_token') ? 'since_token' : 'since_id'
                    )
                );
            }
        } else {
            $since_id = -1;
        }

        $format = $this->config->get('event_feed_format');

        $response = new StreamedResponse();
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Content-Type', 'application/x-ndjson');
        $response->setCallback(function () use ($format, $cid, $contest, $request, $since_id, $metadataFactory, $kernel) {
            $lastUpdate = 0;
            $lastIdSent = $since_id;
            $typeFilter = false;
            if ($request->query->has('types')) {
                $typeFilter = explode(',', $request->query->get('types'));
            }
            $strict     = $request->query->getBoolean('strict', false);
            $stream     = $request->query->getBoolean('stream', true);
            $canViewAll = $this->isGranted('ROLE_API_READER');

            // Keep track of the last send state event; we may have the same
            // event more than once in our table and we want to make sure we
            // only send it out once.
            $lastState = null;

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
                        $inflector = InflectorFactory::create()->build();
                        $plural = strtolower($inflector->pluralize($shortClass));
                        $toCheck[$plural] = $class;
                    }
                }

                // Change some specific endpoints that do not map to our own objects.
                $toCheck['problems'] = ContestProblem::class;
                $toCheck['judgements'] = $toCheck['judgings'];
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

                // Special case: do not send external ID for problems in strict mode
                // This needs to be here since externalid is a property of the Problem
                // entity, not the ContestProblem entity, so the above loop will not
                // detect it.
                $skippedProperties['problems'][] = 'externalid';
            }

            // Initialize all static events.
            $this->eventLogService->initStaticEvents($contest);
            // Reload the contest as the above method will clear the entity manager.
            $contest = $this->getContestWithId($request, $cid);

            while (true) {
                // Add missing state events that should have happened already.
                $this->eventLogService->addMissingStateEvents($contest);

                $qb = $this->em->createQueryBuilder()
                    ->from(Event::class, 'e')
                    ->select('e')
                    ->andWhere('e.eventid > :lastIdSent')
                    ->setParameter('lastIdSent', $lastIdSent)
                    ->andWhere('e.contest = :cid')
                    ->setParameter('cid', $contest->getCid())
                    ->orderBy('e.eventid', 'ASC');

                if ($typeFilter !== false) {
                    $qb = $qb
                        ->andWhere('e.endpointtype IN (:types)')
                        ->setParameter('types', $typeFilter);
                }
                if (!$canViewAll) {
                    $restricted_types = ['judgements', 'runs', 'clarifications'];
                    if ($contest->getStarttime() === null || Utils::now() < $contest->getStarttime()) {
                        $restricted_types[] = 'problems';
                    }
                    $qb = $qb
                        ->andWhere('e.endpointtype NOT IN (:restricted_types)')
                        ->setParameter('restricted_types', $restricted_types);
                }

                $q = $qb->getQuery();

                /** @var Event[] $events */
                $events = $q->getResult();
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

                    // Do not send out the same state event twice
                    if ($event->getEndpointtype() === 'state') {
                        if ($data === $lastState) {
                            continue;
                        }

                        $lastState = $data;
                    }

                    if ($strict) {
                        $toSkip = $skippedProperties[$event->getEndpointtype()] ?? [];
                        foreach ($toSkip as $property) {
                            unset($data[$property]);
                        }
                    }
                    switch ($format) {
                        case static::EVENT_FEED_FORMAT_2020_03:
                            $result = [
                                'id' => (string)$event->getEventid(),
                                'type' => (string)$event->getEndpointtype(),
                                'op' => (string)$event->getAction(),
                                'data' => $data,
                            ];
                            break;
                        case static::EVENT_FEED_FORMAT_2022_07:
                            if ($event->getAction() === EventLogService::ACTION_DELETE) {
                                $data = null;
                            }
                            $id   = (string)$event->getEndpointid() ?: null;
                            $type = (string)$event->getEndpointtype();
                            if ($type === 'contests') {
                                // Special case: the type for a contest is singular and the ID must not be set
                                $id   = null;
                                $type = 'contest';
                            }
                            $result = [
                                'token' => (string)$event->getEventid(),
                                'id'    => $id,
                                'type'  => $type,
                                'data'  => $data,
                            ];
                            break;
                    }

                    if (!$strict) {
                        $result['time'] = Utils::absTime($event->getEventtime());
                    }

                    echo $this->dj->jsonEncode($result) . "\n";
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
     * Get general status information.
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    #[IsGranted('ROLE_API_READER')]
    #[Rest\Get('/{cid}/status')]
    #[OA\Parameter(ref: '#/components/parameters/cid')]
    #[OA\Response(
        response: 200,
        description: 'General status information for the given contest',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'num_submissions',
                    type: 'integer'
                ),
                new OA\Property(
                    property: 'num_queued',
                    type: 'integer'
                ),
                new OA\Property(
                    property: 'num_judging',
                    type: 'integer'
                ),
            ],
            type: 'object'
        )
    )]
    public function getStatusAction(Request $request, string $cid): array
    {
        return $this->dj->getContestStats($this->getContestWithId($request, $cid));
    }

    #[Rest\Get('/{cid}/samples.zip', name: 'samples_data_zip')]
    #[OA\Response(
        response: 200,
        description: 'The problem samples, statement & attachments as a ZIP archive',
        content: new OA\MediaType(mediaType: 'application/zip')
    )]
    public function samplesDataZipAction(Request $request): Response
    {
        // getContestQueryBuilder add filters to only get the contests that the user
        // has access to.
        /** @var Contest|null $contest */
        $contest = $this->getContestQueryBuilder()
            ->andWhere('c.cid = :cid')
            ->setParameter('cid', $this->getContestId($request))
            ->getQuery()
            ->getOneOrNullResult();

        if ($contest === null) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $request->attributes->get('cid')));
        }

        return $this->dj->getSamplesZipForContest($contest);
    }

    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        try {
            return $this->getContestQueryBuilder($request->query->getBoolean('onlyActive', false));
        } catch (TypeError) {
            throw new BadRequestHttpException('\'onlyActive\' must be a boolean.');
        }
    }

    protected function getIdField(): string
    {
        return sprintf('c.%s', $this->eventLogService->externalIdFieldForEntity(Contest::class) ?? 'cid');
    }

    /**
     * Get the contest with the given ID.
     * @throws NonUniqueResultException
     */
    protected function getContestWithId(Request $request, string $id): Contest
    {
        $queryBuilder = $this->getQueryBuilder($request)
            ->andWhere(sprintf('%s = :id', $this->getIdField()))
            ->setParameter('id', $id);

        $contest = $queryBuilder->getQuery()->getOneOrNullResult();

        if ($contest === null) {
            throw new NotFoundHttpException(sprintf('Contest with ID \'%s\' not found', $id));
        }

        return $contest;
    }
}
