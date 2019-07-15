<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\Clarification;
use App\Entity\Configuration;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\InternalError;
use App\Entity\Judgehost;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Rejudging;
use App\Entity\Team;
use App\Entity\TestcaseWithContent;
use App\Entity\User;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use ZipArchive;

class DOMJudgeService
{
    protected $em;
    protected $logger;
    protected $hasAllRoles = false;
    /** @var Configuration[] */
    protected $configCache = [];

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var AuthorizationCheckerInterface
     */
    protected $authorizationChecker;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var HttpKernelInterface
     */
    protected $httpKernel;

    const DATA_SOURCE_LOCAL = 0;
    const DATA_SOURCE_CONFIGURATION_EXTERNAL = 1;
    const DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL = 2;

    const CONFIGURATION_DEFAULT_PENALTY_TIME = 20;

    /**
     * DOMJudgeService constructor.
     * @param EntityManagerInterface        $em
     * @param LoggerInterface               $logger
     * @param RequestStack                  $requestStack
     * @param ContainerInterface            $container
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param TokenStorageInterface         $tokenStorage
     * @param HttpKernelInterface           $httpKernel
     */
    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger,
        RequestStack $requestStack,
        ContainerInterface $container,
        AuthorizationCheckerInterface $authorizationChecker,
        TokenStorageInterface $tokenStorage,
        HttpKernelInterface $httpKernel
    ) {
        $this->em                   = $em;
        $this->logger               = $logger;
        $this->requestStack         = $requestStack;
        $this->container            = $container;
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage         = $tokenStorage;
        $this->httpKernel           = $httpKernel;
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEntityManager()
    {
        return $this->em;
    }

    /**
     * Query configuration variable, with optional default value in case
     * the variable does not exist and boolean to indicate if cached
     * values can be used.
     *
     * When $name is null, then all variables will be returned.
     * @param string|null $name
     * @param mixed       $default
     * @param bool        $onlyIfPublic
     * @return Configuration[]|mixed
     * @throws \Exception
     */
    public function dbconfig_get($name, $default = null, bool $onlyIfPublic = false)
    {
        if (empty($this->configCache)) {
            $configs           = $this->em->getRepository(Configuration::class)->findAll();
            $this->configCache = [];
            foreach ($configs as $config) {
                $this->configCache[$config->getName()] = $config;
            }
        }

        if (is_null($name)) {
            $ret = [];
            foreach ($this->configCache as $config) {
                if (!$onlyIfPublic || $config->getPublic()) {
                    $ret[$config->getName()] = $config->getValue();
                }
            }
            return $ret;
        }

        if (!empty($this->configCache[$name]) &&
            (!$onlyIfPublic || $this->configCache[$name]->getPublic())) {
            return $this->configCache[$name]->getValue();
        }

        if ($default === null) {
            throw new \Exception("Configuration variable '$name' not found.");
        }
        $this->logger->warning("Configuration variable '$name' not found, using default.");
        return $default;
    }

    /**
     * Return all the contests that are currently active indexed by contest ID
     * @param int|null $onlyofteam If -1, get only public contests. If > 0 get only contests for the given team
     * @param bool     $alsofuture If true, also get future contests
     * @return Contest[]
     */
    public function getCurrentContests($onlyofteam = null, bool $alsofuture = false)
    {
        $now = Utils::now();
        $qb  = $this->em->createQueryBuilder();
        $qb->select('c')->from(Contest::class, 'c', 'c.cid');
        if ($onlyofteam !== null && $onlyofteam > 0) {
            $qb->leftJoin('c.teams', 'ct')
                ->andWhere('ct.teamid = :teamid OR c.public = 1')
                ->setParameter(':teamid', $onlyofteam);
            // $contests = $DB->q("SELECT * FROM contest
            //                     LEFT JOIN contestteam USING (cid)
            //                     WHERE (contestteam.teamid = %i OR contest.public = 1)
            //                     AND enabled = 1 ${extra}
            //                     AND ( deactivatetime IS NULL OR
            //                           deactivatetime > UNIX_TIMESTAMP() )
            //                     ORDER BY activatetime", $onlyofteam);
        } elseif ($onlyofteam === -1) {
            $qb->andWhere('c.public = 1');
            // $contests = $DB->q("SELECT * FROM contest
            //                     WHERE enabled = 1 AND public = 1 ${extra}
            //                     AND ( deactivatetime IS NULL OR
            //                           deactivatetime > UNIX_TIMESTAMP() )
            //                     ORDER BY activatetime");
        }
        $qb->andWhere('c.enabled = 1')
            ->andWhere($qb->expr()->orX(
                'c.deactivatetime is null',
                $qb->expr()->gt('c.deactivatetime', $now)
            ))
            ->orderBy('c.activatetime');

        if (!$alsofuture) {
            $qb->andWhere($qb->expr()->lte('c.activatetime', $now));
        }

        $contests = $qb->getQuery()->getResult();
        return $contests;
    }

    /**
     * Get the currently selected contest
     * @param int|null $onlyofteam If -1, get only public contests. If > 0 get only contests for the given team
     * @param bool     $alsofuture If true, also get future contests
     * @return Contest|null
     */
    public function getCurrentContest($onlyofteam = null, bool $alsofuture = false)
    {
        $selected_cid = $this->requestStack->getCurrentRequest()->cookies->get('domjudge_cid');
        if ($selected_cid == -1) {
            return null;
        }

        $contests = $this->getCurrentContests($onlyofteam, $alsofuture);
        foreach ($contests as $contest) {
            if ($contest->getCid() == $selected_cid) {
                return $contest;
            }
        }
        if (count($contests) > 0) {
            return reset($contests);
        }
        return null;
    }

    /**
     * Get the contest with the given contest ID
     * @param int $cid
     * @return Contest|null
     */
    public function getContest($cid)
    {
        return $this->em->getRepository(Contest::class)->find($cid);
    }

    /**
     * Get the team with the given team ID
     * @param int $teamid
     * @return Team|null
     */
    public function getTeam($teamid)
    {
        return $this->em->getRepository(Team::class)->find($teamid);
    }

    /**
     * Get the problem with the given team ID
     * @param int $probid
     * @return Problem|null
     */
    public function getProblem($probid)
    {
        return $this->em->getRepository(Problem::class)->find($probid);
    }

    public function checkrole(string $rolename, bool $check_superset = true): bool
    {
        if ($this->hasAllRoles) {
            return true;
        }

        $user = $this->getUser();
        if ($user === null) {
            return false;
        }

        if ($check_superset) {
            if ($this->authorizationChecker->isGranted('ROLE_ADMIN') &&
                ($rolename == 'team' && $user->getTeam() != null)) {
                return true;
            }
        }
        return $this->authorizationChecker->isGranted('ROLE_' . strtoupper($rolename));
    }

    public function getClientIp()
    {
        $clientIP = $this->requestStack->getMasterRequest()->getClientIp();
        return $clientIP;
    }

    /**
     * Get the logged in user
     * @return User|null
     */
    public function getUser()
    {
        $token = $this->tokenStorage->getToken();
        if ($token == null) {
            return null;
        }

        $user = $token->getUser();

        // Ignore user objects if they aren't an App user
        // Covers cases where users are not logged in
        if (!is_a($user, 'App\Entity\User')) {
            return null;
        }

        return $user;
    }

    /**
     * Get the value of the cookie with the given name
     * @param string $cookieName
     * @return mixed|null
     */
    public function getCookie(string $cookieName)
    {
        if (!$this->requestStack->getCurrentRequest()->cookies) {
            return null;
        }
        return $this->requestStack->getCurrentRequest()->cookies->get($cookieName);
    }

    /**
     * Set the given cookie on the response, returning the response again to allow chaining
     * @param string        $cookieName
     * @param string        $value
     * @param int           $expire
     * @param string|null   $path
     * @param string        $domain
     * @param bool          $secure
     * @param bool          $httponly
     * @param Response|null $response
     * @return Response
     */
    public function setCookie(
        string $cookieName,
        string $value = '',
        int $expire = 0,
        string $path = null,
        string $domain = '',
        bool $secure = false,
        bool $httponly = false,
        Response $response = null
    ) {
        if ($response === null) {
            $response = new Response();
        }
        if ($path === null) {
            $path = $this->requestStack->getCurrentRequest()->getBasePath();
        }

        $response->headers->setCookie(new Cookie($cookieName, $value, $expire, $path, $domain, $secure, $httponly, false, null));

        return $response;
    }

    /**
     * Clear the given cookie on the response, returning the response again to allow chaining
     * @param string        $cookieName
     * @param string|null   $path
     * @param string        $domain
     * @param bool          $secure
     * @param bool          $httponly
     * @param Response|null $response
     * @return Response
     */
    public function clearCookie(
        string $cookieName,
        string $path = null,
        string $domain = '',
        bool $secure = false,
        bool $httponly = false,
        Response $response = null
    ) {
        if ($response === null) {
            $response = new Response();
        }
        if ($path === null) {
            $path = $this->requestStack->getCurrentRequest()->getBasePath();
        }

        $response->headers->clearCookie($cookieName, $path, $domain, $secure, $httponly);
        return $response;
    }

    public function getUpdates(): array
    {
        $contest = $this->getCurrentContest();

        $clarifications = [];
        if ($contest) {
            $clarifications = $this->em->createQueryBuilder()
                ->select('clar.clarid', 'clar.body')
                ->from(Clarification::class, 'clar')
                ->andWhere('clar.contest = :contest')
                ->andWhere('clar.sender is not null')
                ->andWhere('clar.answered = 0')
                ->setParameter('contest', $contest)
                ->getQuery()->getResult();
        }

        $judgehosts = $this->em->createQueryBuilder()
            ->select('j.hostname', 'j.polltime')
            ->from(Judgehost::class, 'j')
            ->andWhere('j.active = 1')
            ->andWhere('j.polltime < :i')
            ->setParameter('i', time() - $this->dbconfig_get('judgehost_critical', 120))
            ->getQuery()->getResult();

        $rejudgings = $this->em->createQueryBuilder()
            ->select('r.rejudgingid, r.starttime, r.endtime')
            ->from(Rejudging::class, 'r')
            ->andWhere('r.endtime is null')
            ->getQuery()->getResult();

        $internal_error = $this->em->createQueryBuilder()
            ->select('ie.errorid', 'ie.description')
            ->from(InternalError::class, 'ie')
            ->andWhere('ie.status = :status')
            ->setParameter('status', 'open')
            ->getQuery()->getResult();

        return [
            'clarifications' => $clarifications,
            'judgehosts' => $judgehosts,
            'rejudgings' => $rejudgings,
            'internal_error' => $internal_error,
        ];
    }

    public function getHttpKernel(): HttpKernelInterface
    {
        return $this->httpKernel;
    }

    /**
     * @return bool
     */
    public function getHasAllRoles(): bool
    {
        return $this->hasAllRoles;
    }

    /**
     * Run the given callable with all roles.
     *
     * This will result in all calls to checkrole() to return true.
     *
     * @param callable $callable
     */
    public function withAllRoles(callable $callable)
    {
        $this->hasAllRoles = true;
        $callable();
        $this->hasAllRoles = false;
    }

    /**
     * Log an action to the auditlog table
     *
     * @param string     $datatype
     * @param mixed      $dataid
     * @param string     $action
     * @param mixed|null $extraInfo
     * @param mixed|null $forceUsername
     * @param int|null   $cid
     */
    public function auditlog(
        string $datatype,
        $dataid,
        string $action,
        $extraInfo = null,
        $forceUsername = null,
        $cid = null
    ) {
        if (!empty($forceUsername)) {
            $user = $forceUsername;
        } else {
            $user = $this->getUser() ? $this->getUser()->getUsername() : null;
        }

        $auditLog = new AuditLog();
        $auditLog
            ->setLogtime(Utils::now())
            ->setCid($cid)
            ->setUser($user)
            ->setDatatype($datatype)
            ->setDataid($dataid)
            ->setAction($action)
            ->setExtrainfo($extraInfo);

        $this->em->persist($auditLog);
        $this->em->flush();
    }

    /**
     * Call alert plugin program to perform user configurable action on
     * important system events. See default alert script for more details.
     *
     * @param string $messageType
     * @param string $description
     */
    public function alert(string $messageType, string $description = '')
    {
        $alert = $this->container->getParameter('domjudge.libdir') . '/alert';
        system(sprintf('%s %s %s &', $alert, escapeshellarg($messageType), escapeshellarg($description)));
    }

    /**
     * Decode a JSON string and handle errors
     * @param string $str
     * @return mixed
     */
    public function jsonDecode(string $str)
    {
        $res = json_decode($str, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new HttpException(500, sprintf("Error decoding JSON data '%s': %s", $str, json_last_error_msg()));
        }
        return $res;
    }

    /**
     * Decode a JSON string and handle errors
     * @param $data
     * @return string
     */
    public function jsonEncode($data): string
    {
        $res = json_encode($data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new HttpException(500, sprintf("Error encoding data to JSON: %s", json_last_error_msg()));
        }
        return $res;
    }

    /**
     * Dis- or re-enable what caused an internal error
     * @param array        $disabled
     * @param Contest|null $contest
     * @param bool|null    $enabled
     */
    public function setInternalError($disabled, $contest, $enabled)
    {
        switch ($disabled['kind']) {
            case 'problem':
                $this->em->createQueryBuilder()
                    ->update(ContestProblem::class, 'p')
                    ->set('p.allowJudge', ':enabled')
                    ->andWhere('p.contest = :cid')
                    ->andWhere('p.probid = :probid')
                    ->setParameter(':enabled', $enabled)
                    ->setParameter(':cid', $contest)
                    ->setParameter(':probid', $disabled['probid'])
                    ->getQuery()
                    ->execute();
                break;
            case 'judgehost':
                $this->em->createQueryBuilder()
                    ->update(Judgehost::class, 'j')
                    ->set('j.active', ':active')
                    ->andWhere('j.hostname = :hostname')
                    ->setParameter(':active', $enabled)
                    ->setParameter(':hostname', $disabled['hostname'])
                    ->getQuery()
                    ->execute();
                break;
            case 'language':
                $this->em->createQueryBuilder()
                    ->update(Language::class, 'lang')
                    ->set('lang.allowJudge', ':enabled')
                    ->andWhere('lang.langid = :langid')
                    ->setParameter(':enabled', $enabled)
                    ->setParameter(':langid', $disabled['langid'])
                    ->getQuery()
                    ->execute();
                break;
            default:
                throw new HttpException(500, sprintf("unknown internal error kind '%s'", $disabled['kind']));
        }
    }

    /**
     * Perform an internal API request to the given URL with the given data
     *
     * @param string $url
     * @param string $method
     * @param array  $queryData
     * @return mixed|null
     * @throws \Exception
     */
    public function internalApiRequest(string $url, string $method = Request::METHOD_GET, array $queryData = [])
    {
        $request  = Request::create('/api' . $url, $method, $queryData);
        $response = $this->getHttpKernel()->handle($request, HttpKernelInterface::SUB_REQUEST);

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            $this->logger->warning(sprintf("executing internal %s request to url %s: http status code: %d, response: %s",
                                           $method, $url, $status, $response));
            return null;
        }

        return $this->jsonDecode($response->getContent());
    }

    /**
     * Get the etc directory of this DOMjudge installation
     * @return string
     */
    public function getDomjudgeEtcDir(): string
    {
        return $this->container->getParameter('domjudge.etcdir');
    }

    /**
     * Get the tmp directory of this DOMjudge installation
     * @return string
     */
    public function getDomjudgeTmpDir(): string
    {
        return $this->container->getParameter('domjudge.tmpdir');
    }

    /**
     * Get the submit directory of this DOMjudge installation
     * @return string
     */
    public function getDomjudgeSubmitDir(): string
    {
        return $this->container->getParameter('domjudge.submitdir');
    }

    /**
     * Get the webapp directory of this DOMjudge installation
     * @return string
     */
    public function getDomjudgeWebappDir(): string
    {
        return $this->container->getParameter('domjudge.webappdir');
    }

    /**
     * Open the given ZIP file
     * @param string $filename
     * @return ZipArchive
     */
    public function openZipFile(string $filename): ZipArchive
    {
        $zip = new ZipArchive();
        $res = $zip->open($filename, ZIPARCHIVE::CHECKCONS);
        if ($res === ZIPARCHIVE::ER_NOZIP || $res === ZIPARCHIVE::ER_INCONS) {
            throw new ServiceUnavailableHttpException(null, 'No valid zip archive given');
        } elseif ($res === ZIPARCHIVE::ER_MEMORY) {
            throw new ServiceUnavailableHttpException(null, 'Not enough memory to extract zip archive');
        } elseif ($res !== true) {
            throw new ServiceUnavailableHttpException(null, 'Unknown error while extracting zip archive');
        }

        return $zip;
    }

    /**
     * Legacy function to make print send method available outside
     * Symfony. Can be removed if the team interface uses Symfony.
     */
    public function sendPrint(...$args): array
    {
        return \App\Utils\Printing::send(...$args);
    }

    /**
     * Get a ZIP with sample data
     *
     * @param ContestProblem $contestProblem
     * @return string Filename of the location of the temporary ZIP file. Make sure to remove it after use
     */
    public function getSamplesZip(ContestProblem $contestProblem)
    {
        /** @var TestcaseWithContent[] $testcases */
        $testcases = $this->em->createQueryBuilder()
            ->from(TestcaseWithContent::class, 'tc')
            ->join('tc.problem', 'p')
            ->join('p.contest_problems', 'cp', Join::WITH, 'cp.contest = :contest')
            ->select('tc')
            ->andWhere('tc.probid = :problem')
            ->andWhere('tc.sample = 1')
            ->andWhere('cp.allowSubmit = 1')
            ->setParameter(':problem', $contestProblem->getProbid())
            ->setParameter(':contest', $contestProblem->getCid())
            ->orderBy('tc.testcaseid')
            ->getQuery()
            ->getResult();


        $zip = new ZipArchive();
        if (!($tempFilename = tempnam($this->getDomjudgeTmpDir(), "export-"))) {
            throw new ServiceUnavailableHttpException(null, 'Could not create temporary file.');
        }

        $res = $zip->open($tempFilename, ZipArchive::OVERWRITE);
        if ($res !== true) {
            throw new ServiceUnavailableHttpException(null, 'Could not create temporary zip file.');
        }

        foreach ($testcases as $index => $testcase) {
            foreach (['input', 'output'] as $type) {
                $extension = substr($type, 0, -3);

                $filename = sprintf("%s.%s", $index + 1, $extension);
                $content  = null;

                switch ($type) {
                    case 'input':
                        $content = $testcase->getInput();
                        break;
                    case 'output':
                        $content = $testcase->getOutput();
                        break;
                }

                $zip->addFromString($filename, $content);
            }
        }

        $zip->close();

        return $tempFilename;
    }
}
