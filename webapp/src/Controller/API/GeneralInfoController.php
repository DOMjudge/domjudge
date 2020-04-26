<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Configuration;
use App\Entity\Contest;
use App\Entity\Judging;
use App\Entity\User;
use App\Service\CheckConfigService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\RouterInterface;

/**
 * @Rest\Route("/api/v4", defaults={ "_format" = "json" })
 * @Rest\Prefix("/api")
 * @Rest\NamePrefix("general_")
 * @SWG\Tag(name="General")
 */
class GeneralInfoController extends AbstractFOSRestController
{
    protected $apiVersion = 4;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var ConfigurationService
     */
    protected $config;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * @var EventLogService
     */
    protected $checkConfigService;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * GeneralInfoController constructor.
     *
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param ConfigurationService   $config
     * @param EventLogService        $eventLogService
     * @param CheckConfigService     $checkConfigService
     * @param RouterInterface        $router
     * @param LoggerInterface        $logger
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService,
        CheckConfigService $checkConfigService,
        RouterInterface $router,
        LoggerInterface $logger
    ) {
        $this->em                 = $em;
        $this->dj                 = $dj;
        $this->eventLogService    = $eventLogService;
        $this->checkConfigService = $checkConfigService;
        $this->router             = $router;
        $this->config             = $config;
        $this->logger             = $logger;
    }

    /**
     * Get the current API version
     * @Rest\Get("/version")
     * @SWG\Response(
     *     response="200",
     *     description="The current API version information",
     *     @SWG\Schema(
     *         type="object",
     *         @SWG\Property(property="api_version", type="integer")
     *     )
     * )
     */
    public function getVersionAction()
    {
        $data = ['api_version' => $this->apiVersion];
        return $data;
    }

    /**
     * Get information about the API and DOMjudge
     * @Rest\Get("/info")
     * @Rest\Get("", name="api_root")
     * @SWG\Response(
     *     response="200",
     *     description="Information about the API and DOMjudge",
     *     @SWG\Schema(
     *         type="object",
     *         @SWG\Property(property="api_version", type="integer"),
     *         @SWG\Property(property="domjudge_version", type="string"),
     *         @SWG\Property(property="environment", type="string"),
     *         @SWG\Property(property="doc_url", type="string")
     *     )
     * )
     */
    public function getInfoAction()
    {
        $data = [
            'api_version' => $this->apiVersion,
            'domjudge_version' => $this->getParameter('domjudge.version'),
            'environment' => $this->getParameter('kernel.environment'),
            'doc_url' => $this->router->generate('app.swagger_ui', [], RouterInterface::ABSOLUTE_URL),
        ];
        return $data;
    }

    /**
     * Get general status information
     * @Rest\Get("/status")
     * @IsGranted("ROLE_API_READER")
     * @SWG\Response(
     *     response="200",
     *     description="General status information for the currently active contests",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(
     *             type="object",
     *             @SWG\Property(property="cid", type="integer"),
     *             @SWG\Property(property="num_submissions", type="integer"),
     *             @SWG\Property(property="num_queued", type="integer"),
     *             @SWG\Property(property="num_judging", type="integer")
     *         )
     *     )
     * )
     * @return array
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getStatusAction()
    {
        if ($this->dj->checkrole('jury')) {
            $onlyOfTeam = null;
        } elseif ($this->dj->checkrole('team') && $this->dj->getUser()->getTeamid()) {
            $onlyOfTeam = $this->dj->getUser()->getTeamid();
        } else {
            $onlyOfTeam = -1;
        }
        $contests = $this->dj->getCurrentContests($onlyOfTeam);
        if (empty($contests)) {
            throw new BadRequestHttpException('No active contest');
        }

        $result = [];
        foreach ($contests as $contest) {
            $contestStats = $this->dj->getContestStats($contest);
            $contestStats['cid'] = $contest->getCid();
            $result[] = $contestStats;
        }

        return $result;
    }

    /**
     * Get information about the currently logged in user
     * @Rest\Get("/user")
     * @SWG\Response(
     *     response="200",
     *     description="Information about the logged in user",
     *     @Model(type=User::class)
     * )
     * @return \App\Entity\User|null
     */
    public function getUserAction()
    {
        $user = $this->dj->getUser();
        if ($user === null) {
            throw new HttpException(401, 'Permission denied');
        }

        return $user;
    }

    /**
     * Get configuration variables
     * @Rest\Get("/config")
     * @SWG\Response(
     *     response="200",
     *     description="The configuration variables",
     *     @SWG\Schema(type="object")
     * )
     * @SWG\Parameter(
     *     name="name",
     *     in="query",
     *     type="string",
     *     description="Get only this configuration variable",
     *     required=false
     * )
     * @param Request $request
     * @return \App\Entity\Configuration[]|mixed
     * @throws \Exception
     */
    public function getDatabaseConfigurationAction(Request $request)
    {
        $onlypublic = !($this->dj->checkrole('jury') || $this->dj->checkrole('judgehost'));
        $name       = $request->query->get('name');

        if ($name) {
            $result = $this->config->get($name, $onlypublic);
        } else {
            $result = $this->config->all($onlypublic);
        }

        if ($name !== null) {
            return [$name => $result];
        }

        return $result;
    }

    /**
     * Update configuration variables
     * @Rest\Put("/config")
     * @IsGranted("ROLE_ADMIN")
     * @SWG\Response(
     *     response="200",
     *     description="The full configuration after change",
     *     @SWG\Schema(type="object")
     * )
     * @SWG\Parameter(
     *     name="body",
     *     in="body",
     *     type="object",
     *     description="The config variables to update. Keys are configuration names, values are configuration values. For scalars, use scalars. For arrays, use arrays with scalars and for key-value arrays use objects.",
     *     required=true,
     *     schema={}
     * )
     * @param Request $request
     * @return \App\Entity\Configuration[]|mixed
     * @throws \Exception
     */
    public function updateConfigurationAction(Request $request)
    {
        $this->config->saveChanges($request->request->all(), $this->eventLogService, $this->dj);
        return $this->config->all(false);
    }

    /**
     * Check the DOMjudge configuration
     * @Rest\Get("/config/check")
     * @IsGranted("ROLE_ADMIN")
     * @SWG\Response(
     *     response="200",
     *     description="Result of the various checks performed",
     *     @SWG\Schema(type="object")
     * )
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getConfigCheckAction()
    {
        $result = $this->checkConfigService->runAll();

        // Determine HTTP response code.
        // If at least one test error: 500
        // If at least one test warning: 300
        // Otherwise 200
        // We use max() here to make sure that if it is 300/500 it will never be 'lowered'
        $aggregate = 200;
        foreach ($result as &$cat) {
            foreach ($cat as &$test) {
                if ($test['result'] == 'E') {
                    $aggregate = max($aggregate, 500);
                } elseif ($test['result'] == 'W') {
                    $aggregate = max($aggregate, 300);
                }
                unset($test['escape']);
            }
            unset($test);
        }
        unset($cat);

        return $this->json($result, $aggregate);
    }

    /**
     * Get the field to use for getting contests by ID
     * @return string
     */
    protected function getContestIdField(): string
    {
        try {
            return $this->eventLogService->externalIdFieldForEntity(Contest::class) ?? 'cid';
        } catch (\Exception $e) {
            return 'cid';
        }
    }

}
