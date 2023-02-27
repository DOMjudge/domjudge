<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Contest;
use App\Entity\User;
use App\Service\CheckConfigService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ImportProblemService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use InvalidArgumentException;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Routing\RouterInterface;

/**
 * @OA\Tag(name="General")
 * @OA\Parameter(ref="#/components/parameters/strict")
 * @OA\Response(response="400", ref="#/components/responses/InvalidResponse")
 * @OA\Response(response="401", ref="#/components/responses/Unauthenticated")
 * @OA\Response(response="403", ref="#/components/responses/Unauthorized")
 */
class GeneralInfoController extends AbstractFOSRestController
{
    protected const API_VERSION = 4;

    public const CCS_SPEC_API_VERSION = '2022-07';
    public const CCS_SPEC_API_URL = 'https://ccs-specs.icpc.io/2022-07/contest_api';

    protected EntityManagerInterface $em;
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected EventLogService $eventLogService;
    protected CheckConfigService $checkConfigService;
    protected RouterInterface $router;
    protected LoggerInterface $logger;
    protected ImportProblemService $importProblemService;

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService,
        CheckConfigService $checkConfigService,
        RouterInterface $router,
        LoggerInterface $logger,
        ImportProblemService $importProblemService
    ) {
        $this->em                   = $em;
        $this->dj                   = $dj;
        $this->eventLogService      = $eventLogService;
        $this->checkConfigService   = $checkConfigService;
        $this->router               = $router;
        $this->config               = $config;
        $this->logger               = $logger;
        $this->importProblemService = $importProblemService;
    }

    /**
     * Get the current API version
     * @Rest\Get("/version")
     * @OA\Response(
     *     response="200",
     *     description="The current API version information",
     *     @OA\JsonContent(
     *         type="object",
     *         @OA\Property(property="api_version", type="integer")
     *     )
     * )
     */
    public function getVersionAction(): array
    {
        return ['api_version' => static::API_VERSION];
    }

    /**
     * Get information about the API and DOMjudge
     * @Rest\Get("/info")
     * @Rest\Get("", name="api_root")
     * @OA\Response(
     *     response="200",
     *     description="Information about the API and DOMjudge",
     *     @OA\JsonContent(
     *         type="object",
     *         @OA\Property(property="version", type="string", description="Version of the CCS Specs Contest API the API adheres to"),
     *         @OA\Property(property="version_url", type="string", description="URL with the specification of the Contest API"),
     *         @OA\Property(
     *             property="domjudge",
     *             type="object",
     *             description="DOMjudge information",
     *             properties={
     *                 @OA\Property(property="api_version", type="integer", description="Version of the API"),
     *                 @OA\Property(property="domjudge_version", type="string", description="Version of DOMjudge"),
     *                 @OA\Property(property="environment", type="string", description="Environment DOMjudge is running in"),
     *                 @OA\Property(property="doc_url", type="string", description="URL to DOMjudge API docs")
     *             }
     *         )
     *     )
     * )
     */
    public function getInfoAction(Request $request): array
    {
        $strict = $request->query->getBoolean('strict', false);

        $result = [
            'version' => self::CCS_SPEC_API_VERSION,
            'version_url' => self::CCS_SPEC_API_URL,
            'name' => 'DOMjudge',
        ];
        if (!$strict) {
            $result['domjudge'] = [
                'api_version' => static::API_VERSION,
                'version' => $this->getParameter('domjudge.version'),
                'environment' => $this->getParameter('kernel.environment'),
                'doc_url' => $this->router->generate('app.swagger_ui', [], RouterInterface::ABSOLUTE_URL),
            ];
        }

        return $result;
    }

    /**
     * Get general status information
     * @Rest\Get("/status")
     * @IsGranted("ROLE_API_READER")
     * @OA\Response(
     *     response="200",
     *     description="General status information for the currently active contests",
     *     @OA\JsonContent(
     *         type="array",
     *         @OA\Items(
     *             type="object",
     *             @OA\Property(property="cid", type="integer"),
     *             @OA\Property(property="num_submissions", type="integer"),
     *             @OA\Property(property="num_queued", type="integer"),
     *             @OA\Property(property="num_judging", type="integer")
     *         )
     *     )
     * )
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getStatusAction(): array
    {
        $contests = $this->dj->getCurrentContests(null);
        if (empty($contests)) {
            throw new BadRequestHttpException('No active contest');
        }

        $result = [];
        foreach ($contests as $contest) {
            $contestStats = $this->dj->getContestStats($contest);
            $contestStats['cid'] =
                $this->config->get('data_source') === DOMJudgeService::DATA_SOURCE_LOCAL
                    ? $contest->getCid() : $contest->getExternalid();
            $result[] = $contestStats;
        }

        return $result;
    }

    /**
     * Get information about the currently logged in user.
     * @Rest\Get("/user")
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     * @OA\Response(
     *     response="200",
     *     description="Information about the logged in user",
     *     @Model(type=User::class)
     * )
     */
    public function getUserAction(): User
    {
        return $this->dj->getUser();
    }

    /**
     * Get configuration variables.
     * @Rest\Get("/config")
     * @OA\Response(
     *     response="200",
     *     description="The configuration variables",
     *     @OA\JsonContent(type="object")
     * )
     * @OA\Parameter(
     *     name="name",
     *     in="query",
     *     description="Get only this configuration variable",
     *     required=false,
     *     @OA\Schema(type="string")
     * )
     */
    public function getDatabaseConfigurationAction(Request $request): array
    {
        $onlypublic = !($this->dj->checkrole('jury') || $this->dj->checkrole('judgehost'));
        $name       = $request->query->get('name');

        if ($name) {
            try {
                $result = $this->config->get($name, $onlypublic);
            } catch (InvalidArgumentException $e) {
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
     * @Rest\Put("/config")
     * @IsGranted("ROLE_ADMIN")
     * @OA\Response(
     *     response="200",
     *     description="The full configuration after change",
     *     @OA\JsonContent(type="object")
     * )
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(mediaType="application/x-www-form-urlencoded"),
     *     @OA\MediaType(mediaType="application/json")
     * )
     *
     * @throws NonUniqueResultException
     */
    public function updateConfigurationAction(Request $request): array
    {
        $this->config->saveChanges($request->request->all(), $this->eventLogService, $this->dj);
        return $this->config->all(false);
    }

    /**
     * Check the DOMjudge configuration.
     * @Rest\Get("/config/check")
     * @IsGranted("ROLE_ADMIN")
     * @OA\Response(
     *     response="200",
     *     description="Result of the various checks performed, no problems found",
     *     @OA\JsonContent(type="object")
     * )
     * @OA\Response(
     *     response="250",
     *     description="Result of the various checks performed, warnings found",
     *     @OA\JsonContent(type="object")
     * )
     * @OA\Response(
     *     response="260",
     *     description="Result of the various checks performed, errors found.",
     *     @OA\JsonContent(type="object")
     * )
     */
    public function getConfigCheckAction(): JsonResponse
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
                if ($test['result'] == 'E') {
                    $aggregate = max($aggregate, 260);
                } elseif ($test['result'] == 'W') {
                    $aggregate = max($aggregate, 250);
                }
                unset($test['escape']);
            }
            unset($test);
        }
        unset($cat);

        return $this->json($result, $aggregate);
    }

    /**
     * Get the flag for the given country.
     * @Rest\Get("/country-flags/{countryCode}/{size}")
     * @OA\Response(
     *     response="200",
     *     description="Returns the given country flag in SVG format",
     *     @OA\MediaType(mediaType="image/svg+xml")
     * )
     * @OA\Parameter(
     *     name="countryCode",
     *     in="path",
     *     description="The ISO 3166-1 alpha-3 code for the country to get the flag for",
     *     @OA\Schema(type="string")
     * )
     * @OA\Parameter(
     *     name="size",
     *     in="path",
     *     description="Preferred aspect ratio as <int>x<int>, currently only 1x1 and 4x3 are available.",
     *     @OA\Schema(type="string")
     * )
     */
    public function countryFlagAction(Request $request, string $countryCode, string $size): Response
    {
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
     * @Rest\Post("/problems")
     * @IsGranted("ROLE_ADMIN")
     * @OA\Tag(name="Problems")
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             required={"zip"},
     *             @OA\Property(
     *                 property="zip",
     *                 type="string",
     *                 format="binary",
     *                 description="The problem archive to import"
     *             ),
     *             @OA\Property(
     *                 property="problem",
     *                 description="Optional: problem id to update.",
     *                 type="string"
     *             )
     *         )
     *     )
     * )
     * @OA\Response(
     *     response="200",
     *     description="Returns the ID of the imported problem and any messages produced",
     *     @OA\JsonContent(
     *         type="object",
     *         @OA\Property(property="problem_id", type="integer", description="The ID of the imported problem"),
     *         @OA\Property(property="messages", type="array",
     *             @OA\Items(type="string", description="Messages produced while adding problems")
     *         )
     *     )
     * )
     */
    public function addProblemAction(Request $request): array
    {
        return $this->importProblemService->importProblemFromRequest($request);
    }

    /**
     * Get the field to use for getting contests by ID.
     */
    protected function getContestIdField(): string
    {
        try {
            return $this->eventLogService->externalIdFieldForEntity(Contest::class) ?? 'cid';
        } catch (Exception $e) {
            return 'cid';
        }
    }
}
