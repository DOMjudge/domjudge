<?php declare(strict_types=1);

namespace App\Controller\API;

use App\DataTransferObject\ApiInfo;
use App\DataTransferObject\ApiInfoProvider;
use App\DataTransferObject\ApiVersion;
use App\DataTransferObject\DomJudgeApiInfo;
use App\DataTransferObject\ExtendedContestStatus;
use App\Entity\User;
use App\Service\CheckConfigService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ImportProblemService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use InvalidArgumentException;
use JMS\Serializer\SerializerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'General')]
#[OA\Parameter(ref: '#/components/parameters/strict')]
#[OA\Response(ref: '#/components/responses/InvalidResponse', response: 400)]
#[OA\Response(ref: '#/components/responses/Unauthenticated', response: 401)]
#[OA\Response(ref: '#/components/responses/Unauthorized', response: 403)]
class GeneralInfoController extends AbstractFOSRestController
{
    protected const API_VERSION = 4;

    final public const CCS_SPEC_API_VERSION = '2023-06';
    final public const CCS_SPEC_API_URL = 'https://ccs-specs.icpc.io/2023-06/contest_api';

    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly EventLogService $eventLogService,
        protected readonly CheckConfigService $checkConfigService,
        protected readonly RouterInterface $router,
        protected readonly LoggerInterface $logger,
        protected readonly ImportProblemService $importProblemService
    ) {}

    /**
     * Get the current API version
     */
    #[Rest\Get('/version')]
    #[OA\Response(
        response: 200,
        description: 'The current API version information',
        content: new OA\JsonContent(ref: new Model(type: ApiVersion::class))
    )]
    public function getVersionAction(): ApiVersion
    {
        return new ApiVersion(static::API_VERSION);
    }

    /**
     * Get information about the API and DOMjudge
     */
    #[Rest\Get('/info')]
    #[Rest\Get('', name: 'api_root')]
    #[OA\Response(
        response: 200,
        description: 'Information about the API and DOMjudge',
        content: new OA\JsonContent(ref: new Model(type: ApiInfo::class))
    )]
    public function getInfoAction(
        #[MapQueryParameter]
        bool $strict = false
    ): ApiInfo {
        $domjudge = null;
        if (!$strict) {
            $domjudge = new DomJudgeApiInfo(
                apiversion: static::API_VERSION,
                version: $this->getParameter('domjudge.version'),
                environment: $this->getParameter('kernel.environment'),
                docUrl: $this->router->generate('app.swagger_ui', [], RouterInterface::ABSOLUTE_URL)
            );
        }

        return new ApiInfo(
            version: self::CCS_SPEC_API_VERSION,
            versionUrl: self::CCS_SPEC_API_URL,
            name: 'DOMjudge',
            //TODO: Add DOMjudge logo
            provider: new ApiInfoProvider(
                name: 'DOMjudge',
                version: $this->getParameter('domjudge.version'),
            ),
            domjudge: $domjudge
        );
    }

    /**
     * Get general status information
     * @throws NoResultException
     * @throws NonUniqueResultException
     *
     * @return ExtendedContestStatus[]
     */
    #[IsGranted('ROLE_API_READER')]
    #[Rest\Get('/status')]
    #[OA\Response(
        response: 200,
        description: 'General status information for the currently active contests',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: ExtendedContestStatus::class))
        )
    )]
    public function getStatusAction(): array
    {
        $contests = $this->dj->getCurrentContests();
        if (empty($contests)) {
            throw new BadRequestHttpException('No active contest');
        }

        $result = [];
        foreach ($contests as $contest) {
            $contestStats = $this->dj->getContestStats($contest);
            $result[] = new ExtendedContestStatus(
                $contest->getExternalid(),
                $contestStats
            );
        }

        return $result;
    }

    /**
     * Get information about the currently logged in user.
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Rest\Get('/user')]
    #[OA\Response(
        response: 200,
        description: 'Information about the logged in user',
        content: new Model(type: User::class)
    )]
    public function getUserAction(): User
    {
        return $this->dj->getUser();
    }

    /**
     * Get configuration variables.
     *
     * @return array<string, bool|int|string|array<string, string>>
     */
    #[Rest\Get('/config')]
    #[OA\Response(
        response: 200,
        description: 'The configuration variables',
        content: new OA\JsonContent(type: 'object')
    )]
    #[OA\Parameter(
        name: 'name',
        description: 'Get only this configuration variable',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    public function getDatabaseConfigurationAction(
        #[MapQueryParameter]
        ?string $name = null
    ): array {
        $onlypublic = !($this->dj->checkrole('jury') || $this->dj->checkrole('judgehost'));

        if ($name) {
            try {
                $result = $this->config->get($name, $onlypublic);
            } catch (InvalidArgumentException) {
                throw new BadRequestHttpException(sprintf('Parameter with name: %s not found', $name));
            }
        } else {
            $result = $this->config->all($onlypublic);
        }

        if ($name !== null) {
            return [$name => $result];
        }

        return $result;
    }

    /**
     * Update configuration variables.
     * @return JsonResponse|array<string, bool|int|array<string, string>|string>
     *
     * @throws NonUniqueResultException
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Rest\Put('/config')]
    #[OA\Response(
        response: 200,
        description: 'The full configuration after change',
        content: new OA\JsonContent(type: 'object')
    )]
    #[OA\Response(
        response: 422,
        description: 'An error occurred while saving the configuration',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'errors',
                    type: 'object'
                )
            ]
        )
    )]
    #[OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(mediaType: 'application/x-www-form-urlencoded'),
            new OA\MediaType(mediaType: 'application/json'),
        ]
    )]
    public function updateConfigurationAction(Request $request): JsonResponse|array
    {
        $errors = $this->config->saveChanges($request->request->all(), $this->eventLogService, $this->dj);
        if (!empty($errors)) {
            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        return $this->config->all(false);
    }

    /**
     * Check the DOMjudge configuration.
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Rest\Get('/config/check')]
    #[OA\Response(
        response: 200,
        description: 'Result of the various checks performed, no problems found',
        content: new OA\JsonContent(type: 'object')
    )]
    #[OA\Response(
        response: 250,
        description: 'Result of the various checks performed, warnings found',
        content: new OA\JsonContent(type: 'object')
    )]
    #[OA\Response(
        response: 260,
        description: 'Result of the various checks performed, errors found.',
        content: new OA\JsonContent(type: 'object')
    )]
    public function getConfigCheckAction(SerializerInterface $serializer): Response
    {
        $result = $this->checkConfigService->runAll();

        // Determine HTTP response code.
        // If at least one test error: 260
        // If at least one test warning: 250
        // Otherwise 200
        // We use max() here to make sure that if it is 250/260 it will never be 'lowered'
        $aggregate = 200;
        foreach ($result as &$cat) {
            foreach ($cat as &$test) {
                if ($test->result == 'E') {
                    $aggregate = max($aggregate, 260);
                } elseif ($test->result == 'W') {
                    $aggregate = max($aggregate, 250);
                }
            }
            unset($test);
        }
        unset($cat);

        return new Response($serializer->serialize($result, 'json'), $aggregate);
    }

    /**
     * Get the flag for the given country.
     */
    #[Rest\Get('/country-flags/{countryCode}/{size}')]
    #[OA\Response(
        response: 200,
        description: 'Returns the given country flag in SVG format',
        content: new OA\MediaType(mediaType: 'image/svg+xml')
    )]
    public function countryFlagAction(
        Request $request,
        #[OA\PathParameter(description: 'The ISO 3166-1 alpha-3 code for the country to get the flag for')]
        string $countryCode,
        #[OA\PathParameter(description: 'Preferred aspect ratio as <int>x<int>, currently only 1x1 and 4x3 are available.')]
        string $size
    ): Response {
        // This API action exists for two reasons
        // - Relative URLs are relative to the API root according to the CCS spec. This
        //   means that we need to have an API endpoint for files.
        // - This makes it that we can not return a flag if flags are disabled.

        if (!$this->config->get('show_flags')) {
            throw new NotFoundHttpException('country flags disabled');
        }

        $alpha3code = strtoupper($countryCode);
        if (!Countries::alpha3CodeExists($alpha3code)) {
            throw new NotFoundHttpException("country $alpha3code does not exist");
        }
        if (!preg_match('/^\d+x\d+$/', $size)) {
            throw new BadRequestHttpException('invalid format for size parameter, should be "4x3" or "1x1"');
        }
        $alpha2code = strtolower(Countries::getAlpha2Code($alpha3code));
        $flagFile = sprintf('%s/public/flags/%s/%s.svg', $this->dj->getDomjudgeWebappDir(), $size, $alpha2code);

        if (!file_exists($flagFile)) {
            throw new NotFoundHttpException("country flag for $alpha3code of size $size not found");
        }

        return AbstractRestController::sendBinaryFileResponse($request, $flagFile);
    }

    /**
     * Add a problem without linking it to a contest.
     *
     * @return array{problem_id: string, messages: array<string, string[]>}
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Rest\Post('/problems')]
    #[OA\Tag(name: 'Problems')]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['zip'],
                properties: [
                    new OA\Property(
                        property: 'zip',
                        description: 'The problem archive to import',
                        type: 'string',
                        format: 'binary'
                    ),
                    new OA\Property(
                        property: 'problem',
                        description: 'Optional: problem id to update.',
                        type: 'string'),
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns the ID of the imported problem and any messages produced',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'problem_id',
                    description: 'The ID of the imported problem',
                    type: 'integer'
                ),
                new OA\Property(
                    property: 'messages',
                    type: 'array',
                    items: new OA\Items(
                        description: 'Messages produced while adding problems',
                        type: 'string'
                    )
                ),
            ],
            type: 'object'
        )
    )]
    public function addProblemAction(Request $request): array
    {
        return $this->importProblemService->importProblemFromRequest($request);
    }
}
