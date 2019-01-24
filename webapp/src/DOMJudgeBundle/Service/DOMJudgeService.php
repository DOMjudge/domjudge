<?php declare(strict_types=1);

namespace DOMJudgeBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\AuditLog;
use DOMJudgeBundle\Entity\Configuration;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\Problem;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Entity\User;
use DOMJudgeBundle\Utils\Utils;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use ZipArchive;

class DOMJudgeService
{
    protected $em;
    protected $logger;
    protected $request;
    protected $container;
    protected $hasAllRoles = false;
    /** @var Configuration[] */
    protected $configCache = [];

    const DATA_SOURCE_LOCAL = 0;
    const DATA_SOURCE_CONFIGURATION_EXTERNAL = 1;
    const DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL = 2;

    const CONFIGURATION_DEFAULT_PENALTY_TIME = 20;

    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger,
        RequestStack $requestStack,
        Container $container
    ) {
        $this->em        = $em;
        $this->logger    = $logger;
        $this->request   = $requestStack->getCurrentRequest();
        $this->container = $container;
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEntityManager()
    {
        return $this->em;
    }

    public function getCurrentContest()
    {
        $selected_cid = $this->request->cookies->get('domjudge_cid');
        if ($selected_cid == -1) {
            return null;
        }

        $contests = $this->getCurrentContests();
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
            $configs           = $this->em->getRepository('DOMJudgeBundle:Configuration')->findAll();
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
     * Will return all the contests that are currently active.
     * When fulldata is true, returns the total row as an array
     * instead of just the ID (array indices will be contest ID's then).
     * If $onlyofteam is not null, only show contests that team is part
     * of. If it is -1, only show publicly visible contests.
     * If $alsofuture is true, also show the contests that start in the future.
     * The results will have the value of field $key in the database as key.
     *
     * This is equivalent to $cdata in the old codebase.
     *
     * @return Contest[]
     */
    public function getCurrentContests(
        bool $fulldata = false,
        $onlyofteam = null,
        bool $alsofuture = false,
        string $key = 'cid'
    ) {
        $now = Utils::now();
        $qb  = $this->em->createQueryBuilder();
        $qb->select('c')->from('DOMJudgeBundle:Contest', 'c', 'c.cid');
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

        $authchecker = $this->container->get('security.authorization_checker');
        if ($check_superset) {
            if ($authchecker->isGranted('ROLE_ADMIN') &&
                ($rolename == 'team' && $user->getTeam() != null)) {
                return true;
            }
        }
        return $authchecker->isGranted('ROLE_' . strtoupper($rolename));
    }

    public function getClientIp()
    {
        $clientIP = $this->container->get('request_stack')->getMasterRequest()->getClientIp();
        return $clientIP;
    }

    /**
     * Get the logged in user
     * @return User|null
     */
    public function getUser()
    {
        $token = $this->container->get('security.token_storage')->getToken();
        if ($token == null) {
            return null;
        }

        $user = $token->getUser();

        // Ignore user objects if they aren't a DOMJudgeBundle user
        // Covers cases where users are not logged in
        if (!is_a($user, 'DOMJudgeBundle\Entity\User')) {
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
        if (!$this->request->cookies) {
            return null;
        }
        return $this->request->cookies->get($cookieName);
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
            $path = $this->request->getBasePath();
        }

        $response->headers->setCookie(new Cookie($cookieName, $value, $expire, $path, $domain, $secure, $httponly));

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
            $path = $this->request->getBasePath();
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
                ->from('DOMJudgeBundle:Clarification', 'clar')
                ->andWhere('clar.contest = :contest')
                ->andWhere('clar.sender is not null')
                ->andWhere('clar.answered = 0')
                ->setParameter('contest', $contest)
                ->getQuery()->getResult();
        }

        $judgehosts = $this->em->createQueryBuilder()
            ->select('j.hostname', 'j.polltime')
            ->from('DOMJudgeBundle:Judgehost', 'j')
            ->andWhere('j.active = 1')
            ->andWhere('j.polltime < :i')
            ->setParameter('i', time() - $this->dbconfig_get('judgehost_critical', 120))
            ->getQuery()->getResult();

        $rejudgings = $this->em->createQueryBuilder()
            ->select('r.rejudgingid, r.starttime, r.endtime')
            ->from('DOMJudgeBundle:Rejudging', 'r')
            ->andWhere('r.endtime is null')
            ->getQuery()->getResult();

        $internal_error = $this->em->createQueryBuilder()
            ->select('ie.errorid', 'ie.description')
            ->from('DOMJudgeBundle:InternalError', 'ie')
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

    public function getHttpKernel()
    {
        return $this->container->get('http_kernel');
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
     * @param array     $disabled
     * @param int|null  $cid
     * @param bool|null $enabled
     */
    public function setInternalError($disabled, $cid, $enabled)
    {
        switch ($disabled['kind']) {
            case 'problem':
                $this->em->createQueryBuilder()
                    ->update('DOMJudgeBundle:ContestProblem', 'p')
                    ->set('p.allowJudge', ':enabled')
                    ->andWhere('p.cid = :cid')
                    ->andWhere('p.probid = :probid')
                    ->setParameter(':enabled', $enabled)
                    ->setParameter(':cid', $cid)
                    ->setParameter(':probid', $disabled['probid'])
                    ->getQuery()
                    ->execute();
                break;
            case 'judgehost':
                $this->em->createQueryBuilder()
                    ->update('DOMJudgeBundle:Judgehost', 'j')
                    ->set('j.active', ':active')
                    ->andWhere('j.hostname = :hostname')
                    ->setParameter(':active', $enabled)
                    ->setParameter(':hostname', $disabled['hostname'])
                    ->getQuery()
                    ->execute();
                break;
            case 'language':
                $this->em->createQueryBuilder()
                    ->update('DOMJudgeBundle:Language', 'lang')
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
     * Get the web directory of this DOMjudge installation
     * @return string
     */
    public function getDomjudgeWebDir(): string
    {
        return $this->container->getParameter('domjudge.wwwdir');
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
    public function sendPrint(...$args) : array
    {
        return \DOMJudgeBundle\Utils\Printing::send(...$args);
    }
}
