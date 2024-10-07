<?php declare(strict_types=1);

namespace App\Controller\API;

use App\DataTransferObject\ContestState;
use App\DataTransferObject\ContestStatus;
use App\DataTransferObject\PatchContest;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Event;
use App\Service\AssetUpdateService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ImportExportService;
use App\Utils\EventFeedFormat;
use App\Utils\Utils;
use BadMethodCallException;
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
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Yaml\Yaml;
use TypeError;

/**
 * @extends AbstractRestController<Contest, Contest>
 */
#[Rest\Route('/contests')]
#[OA\Tag(name: 'Contests')]
#[OA\Parameter(ref: '#/components/parameters/strict')]
#[OA\Response(ref: '#/components/responses/InvalidResponse', response: 400)]
#[OA\Response(ref: '#/components/responses/Unauthenticated', response: 401)]
#[OA\Response(ref: '#/components/responses/Unauthorized', response: 403)]
#[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
class ContestController extends AbstractRestController
{
    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService,
        protected readonly ImportExportService $importExportService,
        protected readonly LoggerInterface $logger,
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
        /** @var UploadedFile|null $yamlFile */
        $yamlFile = $request->files->get('yaml');
        /** @var UploadedFile|null $jsonFile */
        $jsonFile = $request->files->get('json');
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
            items: new OA\Items(ref: new Model(type: Contest::class))
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
        content: new OA\JsonContent(ref: new Model(type: Contest::class))
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
        /** @var Contest|null $contest */
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
        $contest = $this->getContestAndCheckIfLocked($request, $cid);
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
        $contest = $this->getContestAndCheckIfLocked($request, $cid);

        /** @var UploadedFile|null $banner */
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
     * Delete the problemset document for the given contest.
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Rest\Delete('/{cid}/problemset', name: 'delete_contest_problemset')]
    #[OA\Response(response: 204, description: 'Deleting problemset document succeeded')]
    #[OA\Parameter(ref: '#/components/parameters/cid')]
    public function deleteProblemsetAction(Request $request, string $cid): Response
    {
        $contest = $this->getContestAndCheckIfLocked($request, $cid);
        $contest->setClearContestProblemset(true);
        $contest->processContestProblemset();
        $this->em->flush();

        $this->eventLogService->log('contests', $contest->getCid(), EventLogService::ACTION_UPDATE,
            $contest->getCid());

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Set the problemset document for the given contest.
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Rest\Post("/{cid}/problemset", name: 'post_contest_problemset')]
    #[Rest\Put("/{cid}/problemset", name: 'put_contest_problemset')]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['problemset'],
                properties: [
                    new OA\Property(
                        property: 'problemset',
                        description: 'The problemset document to use, as either text/html, text/plain or application/pdf.',
                        type: 'string',
                        format: 'binary'
                    ),
                ]
            )
        )
    )]
    #[OA\Response(response: 204, description: 'Setting problemset document succeeded')]
    #[OA\Parameter(ref: '#/components/parameters/cid')]
    public function setProblemsetAction(Request $request, string $cid, ValidatorInterface $validator): Response
    {
        $contest = $this->getContestAndCheckIfLocked($request, $cid);

        /** @var UploadedFile|null $problemset */
        $problemset = $request->files->get('problemset');
        if (!$problemset) {
            return new JsonResponse(['title' => 'Validation failed', 'errors' => ['Please supply a problemset document']], Response::HTTP_BAD_REQUEST);
        }
        if (!in_array($problemset->getMimeType(), ['text/html', 'text/plain', 'application/pdf'])) {
            return new JsonResponse(['title' => 'Validation failed', 'errors' => ['Invalid problemset document type']], Response::HTTP_BAD_REQUEST);
        }

        $contest->setContestProblemsetFile($problemset);

        if ($errorResponse = $this->responseForErrors($validator->validate($contest), true)) {
            return $errorResponse;
        }

        $contest->processContestProblemset();
        $this->em->flush();

        $this->eventLogService->log('contests', $contest->getCid(), EventLogService::ACTION_UPDATE,
            $contest->getCid());

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Get the problemset document for the given contest.
     */
    #[Rest\Get('/{cid}/problemset', name: 'contest_problemset')]
    #[OA\Response(
        response: 200,
        description: 'Returns the given contest problemset document in PDF, HTML or TXT format',
        content: [
            new OA\MediaType(mediaType: 'application/pdf'),
            new OA\MediaType(mediaType: 'text/plain'),
            new OA\MediaType(mediaType: 'text/html'),
        ]
    )]
    #[OA\Parameter(ref: '#/components/parameters/cid')]
    public function problemsetAction(Request $request, string $cid): Response
    {
        /** @var Contest|null $contest */
        $contest = $this->getQueryBuilder($request)
            ->andWhere(sprintf('%s = :id', $this->getIdField()))
            ->setParameter('id', $cid)
            ->getQuery()
            ->getOneOrNullResult();

        $hasAccess = $this->dj->checkrole('jury') ||
            $this->dj->checkrole('api_reader') ||
            $contest->getFreezeData()->started();

        if (!$hasAccess) {
            throw new AccessDeniedHttpException();
        }

        if ($contest === null) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $cid));
        }

        if (!$contest->getContestProblemsetType()) {
            throw new NotFoundHttpException(sprintf('Contest with ID \'%s\' has no problemset text', $cid));
        }

        return $contest->getContestProblemsetStreamedResponse();
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
            schema: new OA\Schema(ref: new Model(type: PatchContest::class))
        )
    )]
    #[OA\Response(
        response: 204,
        description: 'The change was successful'
    )]
    #[OA\Response(
        response: 200,
        description: 'Contest start time changed successfully',
        content: new OA\JsonContent(ref: new Model(type: Contest::class))
    )]
    public function changeStartTimeAction(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        PatchContest $patchContest,
        Request $request,
        #[OA\PathParameter(description: 'The ID of the contest to change the start time for')]
        string $cid
    ): Response {
        $response = new Response('', Response::HTTP_NO_CONTENT);
        $contest  = $this->getContestWithId($request, $cid);
        $now      = (int)Utils::now();
        $changed  = false;
        // We still need these checks explicit check since they can be null.
        if (!$request->request->has('start_time') && !$request->request->has('scoreboard_thaw_time')) {
            throw new BadRequestHttpException('Missing "start_time" or "scoreboard_thaw_time" in request.');
        }
        if ($request->request->get('id') != $contest->getExternalid()) {
            throw new BadRequestHttpException('Invalid "id" in request.');
        }
        if ($request->request->has('start_time') && $request->request->has('scoreboard_thaw_time')) {
            throw new BadRequestHttpException('Setting both "start_time" and "scoreboard_thaw_time" at the same time is not allowed.');
        }

        if ($request->request->has('start_time')) {
            // By default, it is not allowed to change the start time in the last 30 seconds before contest start.
            // We allow the "force" parameter to override this.
            if (!$patchContest->force &&
                $contest->getStarttime() != null &&
                $contest->getStarttime() < $now + 30) {
                throw new AccessDeniedHttpException('Current contest already started or about to start.');
            }

            if ($patchContest->startTime === null) {
                $contest->setStarttimeEnabled(false);
                $this->em->flush();
                $changed = true;
            } else {
                $date = date_create($patchContest->startTime);
                if ($date === false) {
                    throw new BadRequestHttpException('Invalid "start_time" in request.');
                }

                $new_start_time = $date->getTimestamp();
                if (!$patchContest->force && $new_start_time < $now + 30) {
                    throw new AccessDeniedHttpException('New start_time not far enough in the future.');
                }
                $newStartTimeString = date('Y-m-d H:i:s e', $new_start_time);
                $contest->setStarttimeEnabled(true);
                $contest->setStarttime($new_start_time);
                $contest->setStarttimeString($newStartTimeString);
                $this->em->flush();
                $changed = true;
            }
        }
        if ($request->request->has('scoreboard_thaw_time')) {
            if (!$patchContest->force && $contest->getUnfreezetime() !== null) {
                throw new AccessDeniedHttpException('Current contest already has an unfreeze time set.');
            }

            $date = date_create($patchContest->scoreboardThawTime ?? 'not a valid date');
            if ($date === false) {
                throw new BadRequestHttpException('Invalid "scoreboard_thaw_time" in request.');
            }

            $new_unfreeze_time = $date->getTimestamp();
            if (!$patchContest->force && $new_unfreeze_time < $now - 30) {
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
        $response     = new StreamedResponse();
        $response->setCallback(function () use ($contest) {
            echo Yaml::dump($this->importExportService->getContestYamlData($contest, false), 3);
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
        content: new OA\JsonContent(ref: new Model(type: ContestState::class))
    )]
    public function getContestStateAction(Request $request, string $cid): ContestState
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
        KernelInterface $kernel,
        #[MapQueryParameter(name: 'since_token')]
        ?string $sinceToken = null,
        #[MapQueryParameter(name: 'since_id')]
        ?string $sinceId = null,
        #[MapQueryParameter]
        ?string $types = null,
        #[MapQueryParameter]
        bool $strict = false,
        #[MapQueryParameter]
        bool $stream = true,
    ): Response {
        $contest = $this->getContestWithId($request, $cid);
        // Make sure this script doesn't hit the PHP maximum execution timeout.
        set_time_limit(0);

        if ($sinceToken !== null | $sinceId !== null) {
            // This parameter is a string in the spec, but we want an integer
            $since_id = (int)($sinceToken ?? $sinceId);
            $event    = $this->em->getRepository(Event::class)->findOneBy([
                'eventid' => $since_id,
                'contest' => $contest,
            ]);
            if ($event === null) {
                throw new BadRequestHttpException(
                    sprintf(
                        'Invalid parameter "%s" requested with value "%s".',
                        $request->query->has('since_token') ? 'since_token' : 'since_id',
                        $sinceToken ?? $sinceId
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
        $response->setCallback(function () use ($format, $cid, $contest, $request, $since_id, $types, $strict, $stream, $metadataFactory, $kernel) {
            $lastUpdate = 0;
            $lastIdSent = max(0, $since_id);
            $lastIdExists = $since_id !== -1; // Don't try to look for event_id=0
            $typeFilter = false;
            if ($types) {
                $typeFilter = explode(',', $types);
            }
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

            $missingEventRetries = 0;
            while (true) {
                // Add missing state events that should have happened already.
                $this->eventLogService->addMissingStateEvents($contest);

                // We fetch *all* events from the last seen to check that
                // we don't skip events that are committed out of order.
                // This includes the last seen event itself, just to check
                // that the database is consistent and, for example, has
                // not been reloaded while this process is (long) running.
                $q = $this->em->createQueryBuilder()
                    ->from(Event::class, 'e')
                    ->select('e')
                    ->andWhere('e.eventid >= :lastIdSent')
                    ->setParameter('lastIdSent', $lastIdSent)
                    ->orderBy('e.eventid', 'ASC')
                    ->getQuery();

                /** @var Event[] $events */
                $events = $q->getResult();

                if ($lastIdExists) {
                    if (count($events) == 0 || $events[0]->getEventid() !== $lastIdSent) {
                        throw new HttpException(500, sprintf('Cannot find event %d in database anymore', $lastIdSent));
                    }
                    // Remove the previously last sent event. We just fetched
                    // it to make sure it's there.
                    unset($events[0]);
                }

                // Look for any missing sequential events and wait for them to
                // be committed if so.
                $missingEvents = false;
                $expectedId = $lastIdSent + 1;
                $lastFoundId = null;
                foreach ($events as $event) {
                    if ($event->getEventid() !== $expectedId) {
                        $missingEvents = true;
                        $lastFoundId = $event->getEventid();
                        break;
                    }
                    $expectedId++;
                }
                if ($missingEvents) {
                    if ($missingEventRetries == 0) {
                        $this->logger->info(
                            'Detected missing events %d ... %d, waiting for these to appear',
                            [$expectedId, $lastFoundId-1]
                        );
                    }
                    if (++$missingEventRetries < 10) {
                        usleep(100 * 1000);
                        continue;
                    }

                    // We've decided to permanently ignore these non-existing
                    // events for this connection. The wait for any
                    // non-committed events was long enough.
                    //
                    // There might be multiple non-existing events. Log the
                    // first consecutive gap of non-existing events. A consecutive
                    // gap is guaranteed since the events are ordered.
                    $this->logger->warning(
                        'Waited too long for missing events %d ... %d, skipping',
                        [$expectedId, $lastFoundId-1]
                    );
                }
                $missingEventRetries = 0;

                $numEventsSent = 0;
                foreach ($events as $event) {
                    // Filter out unwanted events
                    if ($event->getContest()->getCid() !== $contest->getCid()) {
                        continue;
                    }
                    if ($typeFilter !== false &&
                        !in_array($event->getEndpointtype(), $typeFilter)) {
                        continue;
                    }
                    if (!$canViewAll) {
                        $restricted_types = ['judgements', 'runs', 'clarifications'];
                        if ($contest->getStarttime() === null || Utils::now() < $contest->getStarttime()) {
                            $restricted_types[] = 'problems';
                        }
                        if (in_array($event->getEndpointtype(), $restricted_types)) {
                            continue;
                        }
                    }

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
                        case EventFeedFormat::Format_2020_03:
                            $result = [
                                'id' => (string)$event->getEventid(),
                                'type' => (string)$event->getEndpointtype(),
                                'op' => (string)$event->getAction(),
                                'data' => $data,
                            ];
                            break;
                        case EventFeedFormat::Format_2022_07:
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
                        default:
                            throw new BadMethodCallException(sprintf('Invalid event feed format %s', $format));
                    }

                    if (!$strict) {
                        $result['time'] = Utils::absTime($event->getEventtime());
                    }

                    echo $this->dj->jsonEncode($result) . "\n";
                    ob_flush();
                    flush();
                    $lastUpdate = Utils::now();
                    $lastIdSent = $event->getEventid();
                    $lastIdExists = true;
                    $numEventsSent++;

                    if ($missingEvents && $event->getEventid() >= $lastFoundId) {
                        // The first event after the first gap has been emitted. Stop
                        // emitting events and restart the gap detection logic to find
                        // any potential gaps after this last emitted event.
                        break;
                    }
                }

                if ($numEventsSent == 0) {
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
        content: new OA\JsonContent(ref: new Model(type: ContestStatus::class))
    )]
    public function getStatusAction(Request $request, string $cid): ContestStatus
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
        return 'c.externalid';
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

    /** To be used when contest data is modified. */
    private function getContestAndCheckIfLocked(Request $request, string $cid): Contest
    {
        /** @var Contest|null $contest */
        $contest = $this->getQueryBuilder($request)
            ->andWhere(sprintf('%s = :id', $this->getIdField()))
            ->setParameter('id', $cid)
            ->getQuery()
            ->getOneOrNullResult();

        if ($contest === null) {
            throw new NotFoundHttpException(sprintf('Contest with ID \'%s\' not found', $cid));
        }

        if ($contest->isLocked()) {
            $contestUrl = $this->generateUrl('jury_contest', ['contestId' => $cid], UrlGeneratorInterface::ABSOLUTE_URL);
            throw new AccessDeniedHttpException('Contest is locked, go to ' . $contestUrl . ' to unlock it.');
        }
        return $contest;
    }
}
