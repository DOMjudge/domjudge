<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\Balloon;
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
use App\Entity\Testcase;
use App\Entity\User;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use ZipArchive;

class DOMJudgeService
{
    protected $em;
    protected $logger;
    /** @var Configuration[] */
    protected $configCache = [];

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var ParameterBagInterface
     */
    protected $params;

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

    /**
     * @var ConfigurationService
     */
    protected $config;

    const DATA_SOURCE_LOCAL = 0;
    const DATA_SOURCE_CONFIGURATION_EXTERNAL = 1;
    const DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL = 2;

    const CONFIGURATION_DEFAULT_PENALTY_TIME = 20;

    /**
     * DOMJudgeService constructor.
     *
     * @param EntityManagerInterface        $em
     * @param LoggerInterface               $logger
     * @param RequestStack                  $requestStack
     * @param ParameterBagInterface         $params
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param TokenStorageInterface         $tokenStorage
     * @param HttpKernelInterface           $httpKernel
     * @param ConfigurationService          $config
     */
    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger,
        RequestStack $requestStack,
        ParameterBagInterface $params,
        AuthorizationCheckerInterface $authorizationChecker,
        TokenStorageInterface $tokenStorage,
        HttpKernelInterface $httpKernel,
        ConfigurationService $config
    ) {
        $this->em                   = $em;
        $this->logger               = $logger;
        $this->requestStack         = $requestStack;
        $this->params               = $params;
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage         = $tokenStorage;
        $this->httpKernel           = $httpKernel;
        $this->config               = $config;
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
                ->leftJoin('c.team_categories', 'tc')
                ->leftJoin('tc.teams', 'tct')
                ->andWhere('ct.teamid = :teamid OR tct.teamid = :teamid OR c.openToAllTeams = 1')
                ->setParameter(':teamid', $onlyofteam);
        } elseif ($onlyofteam === -1) {
            $qb->andWhere('c.public = 1');
        }
        $qb->andWhere('c.enabled = 1')
            ->andWhere('c.deactivatetime IS NULL OR c.deactivatetime > :now')
            ->setParameter(':now', $now)
            ->orderBy('c.activatetime');

        if (!$alsofuture) {
            $qb->andWhere('c.activatetime <= :now');
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
        $contests = $this->getCurrentContests($onlyofteam, $alsofuture);
        if ($this->requestStack->getCurrentRequest()) {
            $selected_cid = $this->requestStack->getCurrentRequest()->cookies->get('domjudge_cid');
            if ($selected_cid == -1) {
                return null;
            }

            foreach ($contests as $contest) {
                if ($contest->getCid() == $selected_cid) {
                    return $contest;
                }
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
        if (!$this->requestStack->getCurrentRequest()) {
            return null;
        }
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

        $clarifications  = [];
        $judgehosts      = [];
        $rejudgings      = [];
        $internal_errors = [];
        $balloons        = [];

        if ($this->checkRole('jury')) {
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
                ->setParameter('i', time() - $this->config->get('judgehost_critical'))
                ->getQuery()->getResult();

            $rejudgings = $this->em->createQueryBuilder()
                ->select('r.rejudgingid, r.starttime, r.endtime')
                ->from(Rejudging::class, 'r')
                ->andWhere('r.endtime is null')
                ->getQuery()->getResult();
        }

        if ($this->checkrole('admin')) {
            $internal_errors = $this->em->createQueryBuilder()
                ->select('ie.errorid', 'ie.description')
                ->from(InternalError::class, 'ie')
                ->andWhere('ie.status = :status')
                ->setParameter('status', 'open')
                ->getQuery()->getResult();
        }

        if ($this->checkrole('balloon')) {
            $balloons = $this->em->createQueryBuilder()
            ->select('b.balloonid', 't.name', 't.room', 'p.name AS pname')
            ->from(Balloon::class, 'b')
            ->leftJoin('b.submission', 's')
            ->leftJoin('s.problem', 'p')
            ->leftJoin('s.contest', 'co')
            ->leftJoin('p.contest_problems', 'cp', Join::WITH, 'co.cid = cp.contest AND p.probid = cp.problem')
            ->leftJoin('s.team', 't')
            ->andWhere('co.cid = :cid')
            ->andWhere('b.done = 0')
            ->setParameter(':cid', $contest->getCid())
            ->getQuery()->getResult();
        }

        return [
            'clarifications' => $clarifications,
            'judgehosts' => $judgehosts,
            'rejudgings' => $rejudgings,
            'internal_errors' => $internal_errors,
            'balloons' => $balloons
        ];
    }

    public function getHttpKernel(): HttpKernelInterface
    {
        return $this->httpKernel;
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
        $currentToken = $this->tokenStorage->getToken();
        // We need a 'user' to create a token. However, even if you
        // are not logged in, a (anonymous) user is returned. This
        // check is just here to make sure the code does not crash
        // in strange circumstances.
        if ($currentToken && $currentToken->getUser()) {
            $this->tokenStorage->setToken(
                new UsernamePasswordToken(
                    $currentToken->getUser(),
                    null,
                    'main',
                    ['ROLE_ADMIN']
                )
            );
        }
        $callable();
        $this->tokenStorage->setToken($currentToken);
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
        $alert = $this->params->get('domjudge.libdir') . '/alert';
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
                    ->andWhere('p.problem = :probid')
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
            $this->logger->warning(
                "executing internal %s request to url %s: http status code: %d, response: %s",
                [ $method, $url, $status, $response ]
            );
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
        return $this->params->get('domjudge.etcdir');
    }

    /**
     * Get the tmp directory of this DOMjudge installation
     * @return string
     */
    public function getDomjudgeTmpDir(): string
    {
        return $this->params->get('domjudge.tmpdir');
    }

    /**
     * Get the webapp directory of this DOMjudge installation
     * @return string
     */
    public function getDomjudgeWebappDir(): string
    {
        return $this->params->get('domjudge.webappdir');
    }

    /**
     * Get the directory used for storing cache files
     * @return string
     */
    public function getCacheDir(): string
    {
        return $this->params->get('kernel.cache_dir');
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
     * Print the given file using the print command.
     *
     * Returns array with two elements: first a boolean indicating
     * overall success, and second the data returned from the print command.
     *
     * @param string      $filename The on-disk file to be printed out
     * @param string      $origname The original filename as submitted by the team
     * @param string|null $language Langid of the programming language this file is in
     * @param string      $username Username of the print job submitter
     * @param string|null $teamname Teamname of the team this user belongs to, if any
     * @param int|null    $teamid   Teamid of the team this user belongs to, if any
     * @param string|null $location Room/place of the team, if any.
     * @return array
     * @throws \Exception
     */
    public function printFile(
        string $filename,
        string $origname,
        ?string $language,
        string $username,
        ?string $teamname = null,
        ?int $teamid = null,
        ?string $location = null
    ): array {
        $printCommand = $this->config->get('print_command');
        if (empty($printCommand)) {
            return [false, 'Printing not enabled'];
        }

        $replaces = [
            '[file]' => escapeshellarg($filename),
            '[original]' => escapeshellarg($origname),
            '[language]' => escapeshellarg($language),
            '[username]' => escapeshellarg($username),
            '[teamname]' => escapeshellarg($teamname ?? ''),
            '[teamid]' => escapeshellarg((string)($teamid ?? '')),
            '[location]' => escapeshellarg($location ?? ''),
        ];

        $cmd = str_replace(
            array_keys($replaces),
            array_values($replaces),
            $printCommand
        );

        exec($cmd, $output, $retval);

        return [$retval == 0, implode("\n", $output)];
    }

    /**
     * Get a ZIP with sample data
     *
     * @param ContestProblem $contestProblem
     * @return string Filename of the location of the temporary ZIP file. Make sure to remove it after use
     */
    public function getSamplesZip(ContestProblem $contestProblem)
    {
        /** @var Testcase[] $testcases */
        $testcases = $this->em->createQueryBuilder()
            ->from(Testcase::class, 'tc')
            ->join('tc.problem', 'p')
            ->join('p.contest_problems', 'cp', Join::WITH, 'cp.contest = :contest')
            ->join('tc.content', 'tcc')
            ->select('tc', 'tcc')
            ->andWhere('tc.problem = :problem')
            ->andWhere('tc.sample = 1')
            ->andWhere('cp.allowSubmit = 1')
            ->setParameter(':problem', $contestProblem->getProblem())
            ->setParameter(':contest', $contestProblem->getContest())
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
                        $content = $testcase->getContent()->getInput();
                        break;
                    case 'output':
                        $content = $testcase->getContent()->getOutput();
                        break;
                }

                $zip->addFromString($filename, $content);
            }
        }

        $zip->close();

        return $tempFilename;
    }

    /**
     * @param Contest $contest
     * @return array
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getContestStats(Contest $contest): array
    {
        $stats = [];
        $stats['num_submissions'] = (int)$this->em
            ->createQuery(
                'SELECT COUNT(s)
                FROM App\Entity\Submission s
                WHERE s.cid = :cid')
            ->setParameter(':cid', $contest->getCid())
            ->getSingleScalarResult();
        $stats['num_queued'] = (int)$this->em
            ->createQuery(
                'SELECT COUNT(s)
                FROM App\Entity\Submission s
                LEFT JOIN App\Entity\Judging j WITH (j.submitid = s.submitid AND j.valid != 0)
                WHERE s.cid = :cid
                AND j.result IS NULL
                AND s.valid = 1')
            ->setParameter(':cid', $contest->getCid())
            ->getSingleScalarResult();
        $stats['num_judging'] = (int)$this->em
            ->createQuery(
                'SELECT COUNT(s)
                FROM App\Entity\Submission s
                LEFT JOIN App\Entity\Judging j WITH (j.submitid = s.submitid)
                WHERE s.cid = :cid
                AND j.result IS NULL
                AND j.valid = 1
                AND s.valid = 1')
            ->setParameter(':cid', $contest->getCid())
            ->getSingleScalarResult();
        return $stats;
    }

    public function getTwigDataForProblemsAction(int $teamId): array {
        $contest            = $this->getCurrentContest($teamId);
        $showLimits         = (bool)$this->config->get('show_limits_on_team_page');
        $defaultMemoryLimit = (int)$this->config->get('memory_limit');
        $timeFactorDiffers  = false;
        if ($showLimits) {
            $timeFactorDiffers = $this->em->createQueryBuilder()
                    ->from(Language::class, 'l')
                    ->select('COUNT(l)')
                    ->andWhere('l.allowSubmit = true')
                    ->andWhere('l.timeFactor <> 1')
                    ->getQuery()
                    ->getSingleScalarResult() > 0;
        }

        $problems = [];
        if ($contest && $contest->getFreezeData()->started()) {
            $problems = $this->em->createQueryBuilder()
                ->from(ContestProblem::class, 'cp')
                ->join('cp.problem', 'p')
                ->leftJoin('p.testcases', 'tc')
                ->select('partial p.{probid,name,externalid,problemtext_type,timelimit,memlimit}', 'cp', 'SUM(tc.sample) AS numsamples')
                ->andWhere('cp.contest = :contest')
                ->andWhere('cp.allowSubmit = 1')
                ->setParameter(':contest', $contest)
                ->addOrderBy('cp.shortname')
                ->groupBy('cp.problem')
                ->getQuery()
                ->getResult();
        }

        return [
            'problems' => $problems,
            'showLimits' => $showLimits,
            'defaultMemoryLimit' => $defaultMemoryLimit,
            'timeFactorDiffers' => $timeFactorDiffers,
        ];
    }
}
