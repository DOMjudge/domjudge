<?php declare(strict_types=1);

namespace App\Service;

use App\Controller\API\ClarificationController;
use App\Doctrine\DBAL\Types\JudgeTaskType;
use App\Entity\AuditLog;
use App\Entity\Balloon;
use App\Entity\Clarification;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Executable;
use App\Entity\ExecutableFile;
use App\Entity\ImmutableExecutable;
use App\Entity\InternalError;
use App\Entity\Judgehost;
use App\Entity\JudgeTask;
use App\Entity\Judging;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\ProblemAttachment;
use App\Entity\Rejudging;
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\Testcase;
use App\Entity\User;
use App\Utils\FreezeData;
use App\Utils\Utils;
use Doctrine\DBAL\FetchMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use ZipArchive;

class DOMJudgeService
{
    protected $em;
    protected $logger;

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

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var Executable|null
     */
    protected $defaultCompareExecutable = null;

    /**
     * @var Executable|null
     */
    protected $defaultRunExecutable = null;

    /**
     * @var array
     */
    protected $affiliationLogos;

    /**
     * @var array
     */
    protected $teamImages;

    const DATA_SOURCE_LOCAL = 0;
    const DATA_SOURCE_CONFIGURATION_EXTERNAL = 1;
    const DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL = 2;

    // Regex external identifiers must adhere to. Note that we are not checking whether it
    // does not start with a dot or dash or ends with a dot. We could but it would make the
    // regex way more complicated and would also complicate the logic in ImportExportService::importContestYaml
    const EXTERNAL_IDENTIFIER_REGEX = '/^[a-zA-Z0-9_.-]+$/';

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
     * @param RouterInterface               $router
     * @param array                         $affiliationLogos
     * @param array                         $teamImages
     */
    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger,
        RequestStack $requestStack,
        ParameterBagInterface $params,
        AuthorizationCheckerInterface $authorizationChecker,
        TokenStorageInterface $tokenStorage,
        HttpKernelInterface $httpKernel,
        ConfigurationService $config,
        RouterInterface $router,
        array $affiliationLogos,
        array $teamImages
    ) {
        $this->em                   = $em;
        $this->logger               = $logger;
        $this->requestStack         = $requestStack;
        $this->params               = $params;
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage         = $tokenStorage;
        $this->httpKernel           = $httpKernel;
        $this->config               = $config;
        $this->router               = $router;
        $this->affiliationLogos     = $affiliationLogos;
        $this->teamImages           = $teamImages;
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEntityManager()
    {
        return $this->em;
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

        return $qb->getQuery()->getResult();
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
        return $this->requestStack->getMasterRequest()->getClientIp();
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
                ->andWhere('j.hidden = 0')
                ->andWhere('j.polltime < :i')
                ->setParameter('i', time() - $this->config->get('judgehost_critical'))
                ->getQuery()->getResult();

            $rejudgings = $this->em->createQueryBuilder()
                ->select('r.rejudgingid, r.starttime, r.endtime')
                ->from(Rejudging::class, 'r')
                ->andWhere('r.endtime is null');
            $curContest = $this->getCurrentContest();
            if ($curContest !== NULL) {
                $rejudgings = $rejudgings->join('r.submissions', 's')
                    ->andWhere('s.contest = :contest')
                    ->setParameter(':contest', $curContest->getCid())
                    ->distinct();
            }
            $rejudgings = $rejudgings->getQuery()->getResult();
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

        if (gettype($cid) == 'string') {
            $cid = (int) $cid;
        }

        $auditLog = new AuditLog();
        $auditLog
            ->setLogtime(Utils::now())
            ->setCid($cid)
            ->setUser($user)
            ->setDatatype($datatype)
            ->setDataid((string)$dataid)
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
     * Dis- or re-enable what caused an internal error.
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
            case 'executable':
                /** @var Executable $executable */
                $executable = $this->em->getRepository(Executable::class)
                    ->findOneBy(['execid' => $disabled['execid']]);
                foreach ($executable->getLanguages() as $language) {
                    /** @var Language $language */
                    $language->setAllowJudge($enabled);
                }
                foreach ($this->getProblemsForExecutable($executable) as $problem) {
                    /** @var Problem $problem */
                    foreach ($problem->getContestProblems() as $contestProblem) {
                        /** @var ContestProblem $contestProblem */
                        $contestProblem->setAllowJudge($enabled);
                    }
                }
                $this->em->flush();
                if ($enabled) {
                    foreach ($executable->getLanguages() as $language) {
                        /** @var Language $language */
                        if ($language->getAllowJudge()) {
                            $this->unblockJudgeTasksForLanguage($language->getLangid());
                        }
                    }
                    foreach ($this->getProblemsForExecutable($executable) as $problem) {
                        /** @var Problem $problem */
                        $this->unblockJudgeTasksForProblem($problem->getProbid());
                    }
                }
                break;
            case 'testcase':
                /** @var Testcase $testcase */
                $testcase = $this->em->getRepository(Testcase::class)
                    ->findOneBy(['testcaseid' => $disabled['testcaseid']]);
                /** @var Problem $problem */
                $problem = $testcase->getProblem();
                foreach ($problem->getContestProblems() as $contestProblem) {
                    /** @var ContestProblem $contestProblem */
                    $contestProblem->setAllowJudge($enabled);
                }
                $this->em->flush();
                if ($enabled) {
                    $this->unblockJudgeTasksForProblem($problem->getProbid());
                }
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
            throw new BadRequestHttpException('No valid zip archive given');
        } elseif ($res === ZIPARCHIVE::ER_MEMORY) {
            throw new ServiceUnavailableHttpException(null, 'Not enough memory to extract zip archive');
        } elseif ($res !== true) {
            throw new ServiceUnavailableHttpException(null,
                'Unknown error while extracting zip archive: ' . print_r($res, TRUE));
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
     * @return string Content of samples zip file.
     */
    public function getSamplesZipContent(ContestProblem $contestProblem)
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
        $zipFileContents = file_get_contents($tempFilename);
        unlink($tempFilename);
        return $zipFileContents;
    }

    public function getSamplesZipStreamedResponse(ContestProblem $contestProblem): StreamedResponse
    {
        $zipFileContent = $this->getSamplesZipContent($contestProblem);
        $outputFilename = sprintf('samples-%s.zip', $contestProblem->getShortname());
        return Utils::streamAsBinaryFile($zipFileContent, $outputFilename, 'zip');
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getSampleTestcaseStreamedResponse(
        ContestProblem $contestProblem,
        int $index,
        string $type
    ): StreamedResponse {
        /** @var Testcase $testcase */
        $testcase = $this->em->createQueryBuilder()
            ->from(Testcase::class, 'tc')
            ->join('tc.problem', 'p')
            ->join('p.contest_problems', 'cp', Join::WITH,
                'cp.contest = :contest')
            ->join('tc.content', 'tcc')
            ->select('tc', 'tcc')
            ->andWhere('tc.problem = :problem')
            ->andWhere('tc.sample = 1')
            ->andWhere('cp.allowSubmit = 1')
            ->setParameter(':problem', $contestProblem->getProbid())
            ->setParameter(':contest', $contestProblem->getContest())
            ->orderBy('tc.testcaseid')
            ->setMaxResults(1)
            ->setFirstResult($index - 1)
            ->getQuery()
            ->getOneOrNullResult();
        if (!$testcase) {
            throw new NotFoundHttpException(sprintf('Problem p%d not found or not available',
                $contestProblem->getProbid()));
        }

        $extension = substr($type, 0, -3);
        $mimetype  = 'text/plain';

        $filename = sprintf("sample-%s.%s.%s", $contestProblem->getShortname(),
            $index, $extension);
        $content  = null;

        switch ($type) {
            case 'input':
                $content = $testcase->getContent()->getInput();
                break;
            case 'output':
                $content = $testcase->getContent()->getOutput();
                break;
        }

        $response = new StreamedResponse();
        $response->setCallback(function () use ($content) {
            echo $content;
        });
        $response->headers->set('Content-Type',
            sprintf('%s; name="%s', $mimetype, $filename));
        $response->headers->set('Content-Disposition',
            sprintf('attachment; filename="%s"', $filename));
        $response->headers->set('Content-Length', strlen($content));

        return $response;
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getAttachmentStreamedResponse(ContestProblem $contestProblem, int $attachmentId): StreamedResponse
    {
        /** @var ProblemAttachment $attachment */
        $attachment = $this->em->createQueryBuilder()
            ->from(ProblemAttachment::class, 'a')
            ->join('a.problem', 'p')
            ->join('p.contest_problems', 'cp', Join::WITH, 'cp.contest = :contest')
            ->join('a.content', 'ac')
            ->select('a', 'ac')
            ->andWhere('a.problem = :problem')
            ->andWhere('a.attachmentid = :attachmentid')
            ->setParameter(':problem', $contestProblem->getProbid())
            ->setParameter(':contest', $contestProblem->getContest())
            ->setParameter(':attachmentid', $attachmentId)
            ->getQuery()
            ->getOneOrNullResult();
        if (!$attachment) {
            throw new NotFoundHttpException(sprintf('Problem p%d not found or not available', $contestProblem->getProbid()));
        }

        return $attachment->getStreamedResponse();
    }

    /**
     * @param Contest $contest
     * @return array
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getContestStats(Contest $contest): array
    {
        $stats = [];
        $stats['num_submissions'] = (int)$this->em
            ->createQuery(
                'SELECT COUNT(s)
                FROM App\Entity\Submission s
                WHERE s.contest = :cid')
            ->setParameter(':cid', $contest->getCid())
            ->getSingleScalarResult();
        $stats['num_queued'] = (int)$this->em
            ->createQuery(
                'SELECT COUNT(s)
                FROM App\Entity\Submission s
                LEFT JOIN App\Entity\Judging j WITH (j.submission = s.submitid AND j.valid != 0)
                WHERE s.contest = :cid
                AND j.result IS NULL
                AND s.valid = 1')
            ->setParameter(':cid', $contest->getCid())
            ->getSingleScalarResult();
        $stats['num_judging'] = (int)$this->em
            ->createQuery(
                'SELECT COUNT(s)
                FROM App\Entity\Submission s
                LEFT JOIN App\Entity\Judging j WITH (j.submission = s.submitid)
                WHERE s.contest = :cid
                AND j.result IS NULL
                AND j.valid = 1
                AND s.valid = 1')
            ->setParameter(':cid', $contest->getCid())
            ->getSingleScalarResult();
        return $stats;
    }

    public function getTwigDataForProblemsAction(int $teamId, StatisticsService $statistics): array {
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
                ->leftJoin('p.attachments', 'a')
                ->select('partial p.{probid,name,externalid,problemtext_type,timelimit,memlimit}', 'cp', 'SUM(tc.sample) AS numsamples', 'a')
                ->andWhere('cp.contest = :contest')
                ->andWhere('cp.allowSubmit = 1')
                ->setParameter(':contest', $contest)
                ->addOrderBy('cp.shortname')
                ->groupBy('cp.problem')
                ->getQuery()
                ->getResult();
        }

        $data = [
            'problems' => $problems,
            'showLimits' => $showLimits,
            'defaultMemoryLimit' => $defaultMemoryLimit,
            'timeFactorDiffers' => $timeFactorDiffers,
        ];

        if ($contest && $this->config->get('show_public_stats')) {
            $freezeData = new FreezeData($contest);
            $data['stats'] = $statistics->getGroupedProblemsStats(
                $contest,
                array_map(function (ContestProblem $problem) {
                    return $problem->getProblem();
                }, array_column($problems, 0)),
                $freezeData->showFinal(false),
                (bool)$this->config->get('verification_required')
            );
        }

        return $data;
    }

    public function createImmutableExecutable(ZipArchive $zip): ImmutableExecutable
    {
        $propertyFile = 'domjudge-executable.ini';
        $immutableExecutable = new ImmutableExecutable();
        $this->em->persist($immutableExecutable);
        $rank = 0;
        for ($idx = 0; $idx < $zip->numFiles; $idx++) {
            $filename = basename($zip->getNameIndex($idx));
            if ($filename === $propertyFile) {
                continue;
            }

            // In doubt make files executable, but try to read it from the zip file.
            $executableBit = true;
            if ($zip->getExternalAttributesIndex($idx, $opsys, $attr)
                && $opsys==ZipArchive::OPSYS_UNIX
                && (($attr >> 16) & 0100) === 0) {
                $executableBit = false;
            }
            $executableFile = new ExecutableFile();
            $executableFile
                ->setRank($rank)
                ->setFilename($filename)
                ->setFileContent($zip->getFromIndex($idx))
                ->setImmutableExecutable($immutableExecutable)
                ->setIsExecutable($executableBit);
            $this->em->persist($executableFile);
            $immutableExecutable->addFile($executableFile);
            $rank++;
        }
        $immutableExecutable->updateHash();
        $this->em->flush();
        return $immutableExecutable;
    }

    public function unblockJudgeTasksForLanguage(string $langId): void
    {
        // These are all the judgings that don't have associated judgetasks yet. Check whether we unblocked them.
        $judgings = $this->em->createQueryBuilder()
            ->select('j')
            ->from(Judging::class, 'j')
            ->leftJoin(JudgeTask::class, 'jt', Join::WITH, 'j.judgingid = jt.jobid')
            ->join(Submission::class, 's', Join::WITH, 'j.submission = s.submitid')
            ->join(Language::class, 'l', Join::WITH, 's.language = l.langid')
            ->where('jt.jobid IS NULL')
            ->andWhere('l.langid = :langid')
            ->setParameter(':langid', $langId)
            ->getQuery()
            ->getResult();
        foreach ($judgings as $judging) {
            $this->maybeCreateJudgeTasks($judging);
        }
    }

    public function unblockJudgeTasksForProblem(int $probId): void
    {
        // These are all the judgings that don't have associated judgetasks yet. Check whether we unblocked them.
        $judgings = $this->em->createQueryBuilder()
            ->select('j')
            ->from(Judging::class, 'j')
            ->leftJoin(JudgeTask::class, 'jt', Join::WITH, 'j.judgingid = jt.jobid')
            ->join(Submission::class, 's', Join::WITH, 'j.submission = s.submitid')
            ->join(Problem::class, 'p', Join::WITH, 's.problem = p.probid')
            ->where('jt.jobid IS NULL')
            ->andWhere('p.probid = :probid')
            ->setParameter(':probid', $probId)
            ->getQuery()
            ->getResult();
        foreach ($judgings as $judging) {
            $this->maybeCreateJudgeTasks($judging);
        }
    }

    public function maybeCreateJudgeTasks(Judging $judging, int $priority = JudgeTask::PRIORITY_DEFAULT): void
    {
        $submission = $judging->getSubmission();
        $problem    = $submission->getContestProblem();
        $language   = $submission->getLanguage();

        if (!$problem->getAllowJudge() || !$language->getAllowJudge()) {
            return;
        }

        $memoryLimit = $problem->getProblem()->getMemlimit();
        $outputLimit = $problem->getProblem()->getOutputlimit();
        if (empty($memoryLimit)) {
            $memoryLimit = $this->config->get('memory_limit');
        }
        if (empty($outputLimit)) {
            $outputLimit = $this->config->get('output_limit');
        }

        // We use a mass insert query, since that is way faster than doing a separate insert for each testcase.
        // We first insert judgetasks, then select their ID's and finally insert the judging runs.
        $compileExecutable = $submission->getLanguage()->getCompileExecutable()->getImmutableExecutable();
        $runExecutable = $this->getImmutableRunExecutable($problem);
        $compareExecutable = $this->getImmutableCompareExecutable($problem);

        // Step 1: Create the template for the judgetasks.
        $judgetaskInsertParams = [
            ':type'              => JudgeTaskType::JUDGING_RUN,
            ':submitid'          => $submission->getSubmitid(),
            ':priority'          => $priority,
            ':jobid'             => $judging->getJudgingid(),
            ':compile_script_id' => $compileExecutable->getImmutableExecId(),
            ':compare_script_id' => $compareExecutable->getImmutableExecId(),
            ':run_script_id'     => $runExecutable->getImmutableExecId(),
            // TODO: store this in the database as well instead of recomputing it here over and over again, doing
            // this will also help to make the whole data immutable.
            ':compile_config'    => json_encode(
                [
                    'script_timelimit'      => $this->config->get('script_timelimit'),
                    'script_memory_limit'   => $this->config->get('script_memory_limit'),
                    'script_filesize_limit' => $this->config->get('script_filesize_limit'),
                    'language_extensions'   => $submission->getLanguage()->getExtensions(),
                    'filter_compiler_files' => $submission->getLanguage()->getFilterCompilerFiles(),
                    'hash'                  => $compileExecutable->getHash(),
                ]
            ),
            ':run_config'        => json_encode(
                [
                    'time_limit'    => $problem->getProblem()->getTimelimit() * $submission->getLanguage()->getTimeFactor(),
                    'memory_limit'  => $memoryLimit,
                    'output_limit'  => $outputLimit,
                    'process_limit' => $this->config->get('process_limit'),
                    'entry_point'   => $submission->getEntryPoint(),
                    'hash'          => $runExecutable->getHash(),
                ]
            ),
            ':compare_config'    => json_encode(
                [
                    'script_timelimit'      => $this->config->get('script_timelimit'),
                    'script_memory_limit'   => $this->config->get('script_memory_limit'),
                    'script_filesize_limit' => $this->config->get('script_filesize_limit'),
                    'compare_args'          => $problem->getProblem()->getSpecialCompareArgs(),
                    'combined_run_compare'  => $problem->getProblem()->getCombinedRunCompare(),
                    'hash'                  => $compareExecutable->getHash(),
                ]
            ),
        ];

        $judgetaskDefaultParamNames = array_keys($judgetaskInsertParams);

        // Step 2: Create and insert the judgetasks.
        $judgetaskInsertParts = [];
        /** @var Testcase $testcase */
        foreach ($problem->getProblem()->getTestcases() as $testcase) {
            $judgetaskInsertParts[]                                             = sprintf(
                '(%s, :testcase_id%d)',
                implode(', ', $judgetaskDefaultParamNames),
                $testcase->getTestcaseid()
            );
            $judgetaskInsertParams[':testcase_id' . $testcase->getTestcaseid()] = $testcase->getTestcaseid();
        }
        $judgetaskColumns = array_map(function (string $column) {
            return substr($column, 1);
        }, $judgetaskDefaultParamNames);
        $judgetaskInsertQuery = sprintf(
            'INSERT INTO judgetask (%s, testcase_id) VALUES %s',
            implode(', ', $judgetaskColumns),
            implode(', ', $judgetaskInsertParts)
        );
        $this->em->getConnection()->executeQuery($judgetaskInsertQuery, $judgetaskInsertParams);

        // Step 3: Fetch the judgetasks ID's per testcase.
        $judgetaskData = $this->em->getConnection()->executeQuery(
            'SELECT judgetaskid, testcase_id FROM judgetask WHERE jobid = :jobid ORDER BY judgetaskid',
            [':jobid' => $judging->getJudgingid()]
        )->fetchAll(FetchMode::ASSOCIATIVE);

        // Step 4: Create and insert the corresponding judging runs.
        $judgingRunInsertParams = [':judgingid' => $judging->getJudgingid()];
        $judgingRunInsertParts  = [];
        foreach ($judgetaskData as $judgetaskItem) {
            $judgingRunInsertParts[] = sprintf(
                '(:judgingid, :testcaseid%d, :judgetaskid%d)',
                $judgetaskItem['judgetaskid'],
                $judgetaskItem['judgetaskid']
            );
            $judgingRunInsertParams[':testcaseid' . $judgetaskItem['judgetaskid']]  = $judgetaskItem['testcase_id'];
            $judgingRunInsertParams[':judgetaskid' . $judgetaskItem['judgetaskid']] = $judgetaskItem['judgetaskid'];
        }
        $judgingRunInsertQuery = sprintf(
            'INSERT INTO judging_run (judgingid, testcaseid, judgetaskid) VALUES %s',
            implode(', ', $judgingRunInsertParts)
        );

        $this->em->getConnection()->executeQuery($judgingRunInsertQuery, $judgingRunInsertParams);
    }

    public function getImmutableCompareExecutable(ContestProblem $problem): ImmutableExecutable
    {
        /** @var Executable $executable */
        $executable = $problem
            ->getProblem()
            ->getCompareExecutable();
        if ($executable === null) {
            if ($this->defaultCompareExecutable === null) {
                $this->defaultCompareExecutable = $this->em
                    ->getRepository(Executable::class)
                    ->findOneBy(['execid' => $this->config->get('default_compare')]);
            }
            $executable = $this->defaultCompareExecutable;
        }
        return $executable->getImmutableExecutable();
    }

    public function getImmutableRunExecutable(ContestProblem $problem): ImmutableExecutable
    {
        /** @var Executable $executable */
        $executable = $problem
            ->getProblem()
            ->getRunExecutable();
        if ($executable === null) {
            if ($this->defaultRunExecutable === null) {
                $this->defaultRunExecutable = $this->em
                    ->getRepository(Executable::class)
                    ->findOneBy(['execid' => $this->config->get('default_run')]);
            }
            $executable = $this->defaultRunExecutable;
        }
        return $executable->getImmutableExecutable();
    }

    private function getProblemsForExecutable(Executable $executable) {
        $ret = array_merge($executable->getProblemsCompare()->toArray(),
            $executable->getProblemsRun()->toArray());

        foreach (['run', 'compare'] as $type) {
            if ($executable->getExecid() == $this->config->get('default_' . $type)) {
                $ret = array_merge($ret, $this->em->getRepository(Problem::class)
                    ->findBy([$type . '_executable' => null]));
            }
        }

        return $ret;
    }

    /**
     * Get the URL to a route relative to the API root
     */
    public function apiRelativeUrl(string $route, array $params = []): string
    {
        $route = $this->router->generate($route, $params);
        $apiRootRoute = $this->router->generate('v4_api_root');
        $offset = substr($apiRootRoute, -1) === '/' ? 0 : 1;
        return substr($route, strlen($apiRootRoute) + $offset);
    }

    /**
     * Get the path of an asset if it exists
     *
     * @param string $name
     * @param string $type
     * @param bool $fullPath If true, get the full path. If false, get the webserver relative path
     *
     * @return string|null
     */
    public function assetPath(string $name, string $type, bool $fullPath = false): ?string
    {
        $prefix = $fullPath ? ($this->getDomjudgeWebappDir() . '/public/') : '';
        switch ($type) {
            case 'affiliation':
                $extension = 'png';
                $var = $this->affiliationLogos;
                $dir = 'images/affiliations';
                break;
            case 'team':
                $extension = 'jpg';
                $var = $this->teamImages;
                $dir = 'images/teams';
                break;
        }

        if (isset($extension)) {
            if (in_array($name . '.' . $extension, $var)) {
                return sprintf('%s%s/%s.%s', $prefix, $dir, $name, $extension);
            }
        }

        return null;
    }

    public function loadTeam(string $idField, string $teamId, Contest $contest): Team
    {
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Team::class, 't')
            ->select('t')
            ->leftJoin('t.category', 'tc')
            ->leftJoin('t.contests', 'c')
            ->leftJoin('tc.contests', 'cc')
            ->andWhere(sprintf('t.%s = :team', $idField))
            ->andWhere('t.enabled = 1')
            ->setParameter(':team', $teamId);

        if (!$contest->isOpenToAllTeams()) {
            $queryBuilder
                ->andWhere('c.cid = :cid OR cc.cid = :cid')
                ->setParameter(':cid', $contest->getCid());
        }

        /** @var Team $team */
        $team = $queryBuilder->getQuery()->getOneOrNullResult();

        if (!$team) {
            throw new BadRequestHttpException(
                sprintf("Team with ID '%s' not found in contest or not enabled.", $teamId));
        }
        return $team;
    }
}
