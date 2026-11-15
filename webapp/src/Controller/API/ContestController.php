<?php declare(strict_types=1);

namespace App\Controller\API;

use App\DataTransferObject\ContestProblemWrapper;
use App\DataTransferObject\ContestState;
use App\DataTransferObject\ContestStatus;
use App\DataTransferObject\PatchContest;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Event;
use App\Entity\TeamCategory;
use App\Service\AssetUpdateService;
use App\Service\AuthorizedUserService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ImportExportService;
use App\Service\ScoreboardService;
use App\Utils\CcsApiVersion;
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
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
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
#[Rest\Route(path: '/contests')]
#[OA\Tag(name: 'Contests')]
#[OA\Parameter(ref: '#/components/parameters/strict')]
#[OA\Response(ref: '#/components/responses/InvalidResponse', response: 400)]
#[OA\Response(ref: '#/components/responses/Unauthenticated', response: 401)]
#[OA\Response(ref: '#/components/responses/Unauthorized', response: 403)]
#[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
class ContestController extends AbstractRestController
{
    private const EVENT_FEED_BATCH_SIZE = 500;

    public function __construct(
        AuthorizedUserService $authService,
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService,
        protected readonly ImportExportService $importExportService,
        protected readonly LoggerInterface $logger,
        protected readonly AssetUpdateService $assetUpdater,
        protected readonly ScoreboardService $scoreboardService
    ) {
        parent::__construct($authService, $em, $dj, $config, $eventLogService);
    }

    /**
     * Get the scoreboard for the given contest as a ZIP archive.
     * @throws NonUniqueResultException
     */
    #[IsGranted('ROLE_API_READER')]
    #[Rest\Get(path: '/{cid}/scoreboard.zip', name: 'contest_scoreboard_zip')]
    #[OA\Response(
        response: 200,
        description: 'The contest scoreboard as a ZIP archive',
        content: new OA\MediaType(mediaType: 'application/zip')
    )]
    #[OA\Parameter(ref: '#/components/parameters/cid')]
    #[OA\Parameter(
        name: 'unfrozen',
        description: 'Whether to include unfrozen results',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false)
    )]
    public function scoreboardZipAction(
        Request $request,
        RequestStack $requestStack,
        string $cid,
        #[MapQueryParameter] bool $unfrozen = false
    ): Response {
        $contest = $this->getContestWithId($request, $cid);
        return $this->dj->getScoreboardZip($request, $requestStack, $contest, $this->scoreboardService, $unfrozen);
    }

    /**
     * Get the results for the given contest as a TSV file.
     * @throws NonUniqueResultException
     */
    #[IsGranted('ROLE_API_READER')]
    #[Rest\Get(path: '/{cid}/results.tsv', name: 'contest_results_tsv')]
    #[OA\Response(
        response: 200,
        description: 'The contest results as a TSV file',
        content: new OA\MediaType(mediaType: 'text/tab-separated-values')
    )]
    #[OA\Parameter(ref: '#/components/parameters/cid')]
    #[OA\Parameter(
        name: 'sortorder',
        description: 'The sort order to get the results for',
        in: 'query',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'honors',
        description: 'Whether to include honors',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: true)
    )]
    #[OA\Parameter(
        name: 'individually_ranked',
        description: 'Whether to rank individually',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false)
    )]
    public function resultsTsvAction(
        Request $request,
        string $cid,
        #[MapQueryParameter] ?int $sortorder = null,
        #[MapQueryParameter] bool $honors = true,
        #[MapQueryParameter(name: 'individually_ranked')] bool $individuallyRanked = false
    ): Response {
        $contest = $this->getContestWithId($request, $cid);

        if ($sortorder === null) {
            // Get the lowest available sortorder.
            $sortorder = (int)$this->em->createQueryBuilder()
                ->from(TeamCategory::class, 'c')
                ->select('MIN(c.sortorder)')
                ->getQuery()
                ->getSingleScalarResult();
        }

        return $this->importExportService->getResultsTsvResponse($contest, $sortorder, $individuallyRanked, $honors);
    }

    /**
     * Add a new contest.
     * @throws BadRequestHttpException
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Rest\Post(path: '')]
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
            $data = Utils::jsonDecode(file_get_contents($jsonFile->getRealPath()));
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
    #[Rest\Get(path: '')]
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
        schema: new OA\Schema(type: 'boolean', default: true)
    )]
    public function listAction(Request $request): Response
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given contest.
     * @throws NonUniqueResultException
     */
    #[Rest\Get(path: '/{cid}')]
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
    #[Rest\Get(path: '/{cid}/banner', name: 'contest_banner')]
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
        $contest = $this->getQueryBuilder($request, filterBeforeContest: false)
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
    #[Rest\Delete(path: '/{cid}/banner', name: 'delete_contest_banner')]
    #[OA\Response(response: 204, description: 'Deleting banner succeeded')]
    #[OA\Parameter(ref: '#/components/parameters/cid')]
    public function deleteBannerAction(Request $request, string $cid): Response
    {
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
    #[Rest\Delete(path: '/{cid}/problemset', name: 'delete_contest_problemset')]
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
    #[Rest\Get(path: '/{cid}/problemset', name: 'contest_problemset')]
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
        $contest = $this->getQueryBuilder($request, filterBeforeContest: true)
            ->andWhere(sprintf('%s = :id', $this->getIdField()))
            ->setParameter('id', $cid)
            ->getQuery()
            ->getOneOrNullResult();

        if ($contest === null) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $cid));
        }

        $hasAccess = $this->authService->checkRole('jury') ||
            $this->authService->checkRole('api_reader') ||
            $contest->getFreezeData()->started();

        if (!$hasAccess) {
            throw new AccessDeniedHttpException();
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
    #[Rest\Patch(path: '/{cid}')]
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
        if ($request->request->get('id') !== $contest->getExternalid()) {
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
    #[Rest\Get(path: '/{cid}/contest-yaml')]
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
        $response->setCallback(function () use ($contest): void {
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
    #[Rest\Get(path: '/{cid}/state')]
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
    #[IsGranted(new Expression("is_granted('ROLE_API_READER')"))]
    #[Rest\Get(path: '/{cid}/event-feed')]
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

        /** @var CcsApiVersion $format */
        $format = $this->config->get('ccs_api_version');
        $contentVersion = $format->getContentVersion();

        if ($sinceToken !== null || $sinceId !== null) {
            // This parameter is a string in the spec, but we want an integer
            $since_id = (int)($sinceToken ?? $sinceId);
            $event    = $this->em->getRepository(Event::class)->findOneBy([
                'eventid' => $since_id,
                'contest' => $contest,
                'version' => $contentVersion,
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

        $response = new StreamedResponse();
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Content-Type', 'application/x-ndjson');
        $response->setCallback(function () use ($format, $contentVersion, $cid, $contest, $request, $since_id, $types, $strict, $stream, $metadataFactory, $kernel): void {
            $lastUpdate = 0;
            $lastIdSent = max(0, $since_id);
            $lastIdExists = $since_id !== -1; // Don't try to look for event_id=0
            $cursorId = $lastIdSent;
            $typeFilter = false;
            if ($types) {
                $typeFilter = explode(',', $types);
            }

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
                        $toCheck[$plural] = [$class];
                    }
                }

                // Change some specific endpoints that do not map to our own objects.
                $toCheck['problems'] = [ContestProblem::class, ContestProblemWrapper::class];
                $toCheck['judgements'] = $toCheck['judgings'];
                $toCheck['groups'] = $toCheck['teamcategories'];
                $toCheck['organizations'] = $toCheck['teamaffiliations'];
                unset($toCheck['teamcategories']);
                unset($toCheck['teamaffiliations']);
                unset($toCheck['contestproblems']);

                /** @var CcsApiVersion $ccsApiVersion */
                $ccsApiVersion = $this->config->get('ccs_api_version');

                foreach ($toCheck as $plural => $classes) {
                    foreach ($classes as $class) {
                        $serializerMetadata = $metadataFactory->getMetadataForClass($class);
                        /** @var PropertyMetadata $propertyMetadata */
                        foreach ($serializerMetadata->propertyMetadata as $propertyMetadata) {
                            if (is_array($propertyMetadata->groups) &&
                                !in_array('Default', $propertyMetadata->groups) &&
                                !in_array($ccsApiVersion->value, $propertyMetadata->groups)) {
                                $skippedProperties[$plural][] = $propertyMetadata->serializedName;
                            }
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

            $emitEvent = function (Event $event) use (
                $format,
                $strict,
                $skippedProperties,
                &$lastState,
                &$lastUpdate,
                &$lastIdSent,
                &$lastIdExists,
            ): bool {
                $data = $event->getContent();

                // Do not send out the same state event twice
                if ($event->getEndpointtype() === 'state') {
                    if ($data === $lastState) {
                        return false;
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
                    case CcsApiVersion::Format_2020_03:
                        $result = [
                            'id' => (string)$event->getEventid(),
                            'type' => $event->getEndpointtype(),
                            'op' => $event->getAction(),
                            'data' => $data,
                        ];
                        break;
                    case CcsApiVersion::Format_2023_06:
                    case CcsApiVersion::Format_2026_01:
                        if ($event->getAction() === EventLogService::ACTION_DELETE) {
                            $data = null;
                        }
                        $id   = $event->getEndpointid() ?: null;
                        $type = $event->getEndpointtype();
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
                        throw new BadMethodCallException(sprintf('Invalid event feed format %s', $format->value));
                }

                if (!$strict) {
                    $result['time'] = Utils::absTime($event->getEventtime());
                }

                echo Utils::jsonEncode($result) . "\n";
                Utils::ob_flush_if_possible();
                flush();
                $lastUpdate = Utils::now();
                $lastIdSent = $event->getEventid();
                $lastIdExists = true;
                return true;
            };

            while (true) {
                // Add missing state events that should have happened already.
                $this->eventLogService->addMissingStateEvents($contest);

                // Check that the last seen event still exists so we fail if,
                // for example, the database was reloaded while streaming.
                if ($lastIdExists && !$this->eventExists($lastIdSent)) {
                    throw new HttpException(500, sprintf('Cannot find event %d in database anymore', $lastIdSent));
                }

                $numEventsSent = 0;
                while (true) {
                    $events = $this->fetchEventFeedBatch($contest, $contentVersion, $cursorId, $typeFilter);
                    if ($events === []) {
                        break;
                    }

                    $numBatchEvents = count($events);
                    $batchMaxId = $events[$numBatchEvents - 1]->getEventid();
                    $gaps = [];
                    if ($batchMaxId - $cursorId !== $numBatchEvents) {
                        // Check global event IDs up to $batchMaxId so we do not skip events of
                        // this contest that are still being committed in an earlier transaction.
                        $gaps = $this->findMissingEventRanges($cursorId, $batchMaxId);
                        if ($this->countMatchingEvents($contest, $contentVersion, $cursorId, $batchMaxId, $typeFilter) !== $numBatchEvents) {
                            // A matching event in ($cursorId, $batchMaxId] committed after the
                            // batch was read; re-fetch so it is not skipped when advancing.
                            continue;
                        }
                    }

                    $eventIdx = 0;
                    if ($gaps !== []) {
                        ['start' => $gapStart, 'end' => $gapEnd] = $gaps[0];
                        while ($eventIdx < $numBatchEvents && $events[$eventIdx]->getEventid() < $gapStart) {
                            if ($emitEvent($events[$eventIdx])) {
                                $numEventsSent++;
                            }
                            $eventIdx++;
                        }
                        $cursorId = $gapStart - 1;

                        if (!$this->waitForMissingEvents($gapStart, $gapEnd)) {
                            $cursorId = $gapEnd;
                        }
                        // Re-fetch after waiting: either missing events in the gap committed,
                        // or later gaps may have committed while we waited.
                        continue;
                    }

                    while ($eventIdx < $numBatchEvents) {
                        if ($emitEvent($events[$eventIdx])) {
                            $numEventsSent++;
                        }
                        $eventIdx++;
                    }
                    $cursorId = $batchMaxId;
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
                        Utils::ob_flush_if_possible();
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
    #[Rest\Get(path: '/{cid}/status')]
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

    #[Rest\Get(path: '/{cid}/samples.zip', name: 'samples_data_zip')]
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

    protected function shouldFilterBeforeContest(): bool
    {
        return false;
    }

    protected function getQueryBuilder(Request $request, ?bool $filterBeforeContest = null): QueryBuilder
    {
        try {
            return $this->getContestQueryBuilder(
                $request->query->getBoolean('onlyActive', true),
                $filterBeforeContest ?? $this->shouldFilterBeforeContest()
            );
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
        $queryBuilder = $this->getQueryBuilder($request, filterBeforeContest: false)
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
        $contest = $this->getQueryBuilder($request, filterBeforeContest: false)
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

    private function eventExists(int $eventId): bool
    {
        return (bool)$this->em->getConnection()->fetchOne(
            'SELECT 1 FROM event WHERE eventid = :eventId',
            ['eventId' => $eventId]
        );
    }

    /**
     * @param string[]|false $typeFilter
     */
    private function createEventFeedQueryBuilder(
        Contest $contest,
        CcsApiVersion $contentVersion,
        int $afterEventId,
        array|false $typeFilter,
    ): QueryBuilder {
        $qb = $this->em->createQueryBuilder()
            ->from(Event::class, 'e')
            ->andWhere('e.contest = :contest')
            ->andWhere('e.version = :version')
            ->andWhere('e.eventid > :afterEventId')
            ->setParameter('contest', $contest)
            ->setParameter('version', $contentVersion)
            ->setParameter('afterEventId', $afterEventId);

        if ($typeFilter !== false) {
            $qb->andWhere('e.endpointtype IN (:types)')
                ->setParameter('types', $typeFilter);
        }

        return $qb;
    }

    /**
     * @param string[]|false $typeFilter
     * @return list<Event>
     */
    private function fetchEventFeedBatch(
        Contest $contest,
        CcsApiVersion $contentVersion,
        int $afterEventId,
        array|false $typeFilter,
    ): array {
        /** @var list<array{eventid: int, eventtime: string|float, endpointtype: string, endpointid: string, action: string, content: array<string, mixed>}> $rows */
        $rows = $this->createEventFeedQueryBuilder($contest, $contentVersion, $afterEventId, $typeFilter)
            ->select('e')
            ->orderBy('e.eventid', 'ASC')
            ->setMaxResults(self::EVENT_FEED_BATCH_SIZE)
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn(array $row): Event => (new Event())
                ->setEventid($row['eventid'])
                ->setEventtime($row['eventtime'])
                ->setEndpointtype($row['endpointtype'])
                ->setEndpointid($row['endpointid'])
                ->setAction($row['action'])
                ->setContent($row['content'])
                ->setVersion($contentVersion)
                ->setContest($contest),
            $rows
        );
    }

    /**
     * @param string[]|false $typeFilter
     */
    private function countMatchingEvents(
        Contest $contest,
        CcsApiVersion $contentVersion,
        int $afterEventId,
        int $upToEventId,
        array|false $typeFilter,
    ): int {
        return (int)$this->createEventFeedQueryBuilder($contest, $contentVersion, $afterEventId, $typeFilter)
            ->select('COUNT(e.eventid)')
            ->andWhere('e.eventid <= :upToEventId')
            ->setParameter('upToEventId', $upToEventId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find missing global event ID ranges strictly between $fromExclusive and $toInclusive.
     *
     * @return list<array{start: int, end: int}>
     */
    private function findMissingEventRanges(int $fromExclusive, int $toInclusive): array
    {
        if ($toInclusive <= $fromExclusive + 1) {
            return [];
        }

        $presentCount = (int)$this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM event WHERE eventid > :fromId AND eventid < :toId',
            ['fromId' => $fromExclusive, 'toId' => $toInclusive]
        );
        if ($presentCount === $toInclusive - $fromExclusive - 1) {
            return [];
        }

        $rows = $this->em->getConnection()->fetchAllAssociative(
            <<<'SQL'
            SELECT prev_id + 1 AS gap_start, next_id - 1 AS gap_end
            FROM (
                SELECT
                    COALESCE(LAG(eventid) OVER (ORDER BY eventid), :fromId) AS prev_id,
                    eventid AS next_id
                FROM event
                WHERE eventid > :fromId AND eventid <= :toId
            ) gaps
            WHERE next_id > prev_id + 1
            ORDER BY gap_start ASC
            SQL,
            ['fromId' => $fromExclusive, 'toId' => $toInclusive]
        );

        return array_map(
            static fn(array $row): array => [
                'start' => (int)$row['gap_start'],
                'end' => (int)$row['gap_end'],
            ],
            $rows
        );
    }

    /**
     * Wait up to ~1 second for missing sequential event IDs to be committed.
     *
     * @return bool True if at least one event in the gap appeared while waiting.
     */
    private function waitForMissingEvents(int $gapStart, int $gapEnd): bool
    {
        $this->logger->info(
            'Detected missing events %d ... %d, waiting for these to appear',
            [$gapStart, $gapEnd]
        );

        $expectedCount = $gapEnd - $gapStart + 1;
        $presentCount = 0;
        for ($retry = 0; $retry < 10; $retry++) {
            usleep(100 * 1000);
            $presentCount = (int)$this->em->getConnection()->fetchOne(
                'SELECT COUNT(*) FROM event WHERE eventid BETWEEN :gapStart AND :gapEnd',
                ['gapStart' => $gapStart, 'gapEnd' => $gapEnd]
            );
            if ($presentCount === $expectedCount) {
                return true;
            }
        }

        if ($presentCount > 0) {
            return true;
        }

        // We've decided to permanently ignore these non-existing events for this
        // connection. The wait for any non-committed events was long enough.
        $this->logger->warning(
            'Waited too long for missing events %d ... %d, skipping',
            [$gapStart, $gapEnd]
        );
        return false;
    }
}
