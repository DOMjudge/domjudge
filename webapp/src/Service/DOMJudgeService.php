<?php declare(strict_types=1);

namespace App\Service;

use App\DataTransferObject\ContestStatus;
use App\Doctrine\DBAL\Types\JudgeTaskType;
use App\Entity\AssetEntityInterface;
use App\Entity\AuditLog;
use App\Entity\Balloon;
use App\Entity\Clarification;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Executable;
use App\Entity\ExecutableFile;
use App\Entity\ExternalContestSource;
use App\Entity\ExternalSourceWarning;
use App\Entity\ImmutableExecutable;
use App\Entity\InternalError;
use App\Entity\Judgehost;
use App\Entity\JudgeTask;
use App\Entity\Judging;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\ProblemAttachment;
use App\Entity\QueueTask;
use App\Entity\Rejudging;
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Entity\Testcase;
use App\Entity\User;
use App\Utils\FreezeData;
use App\Utils\Utils;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
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
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Environment;
use ZipArchive;

class DOMJudgeService
{
    protected ?Executable $defaultCompareExecutable = null;
    protected ?Executable $defaultRunExecutable = null;

    final public const EVAL_DEFAULT = 0;
    final public const EVAL_LAZY = 1;
    final public const EVAL_FULL = 2;
    final public const EVAL_DEMAND = 3;

    // Regex external identifiers must adhere to. Note that we are not checking whether it
    // does not start with a dot or dash or ends with a dot. We could but it would make the
    // regex way more complicated and would also complicate the logic in ImportExportService::importContestYaml.
    final public const EXTERNAL_IDENTIFIER_REGEX = '/^[a-zA-Z0-9_.-]+$/';

    final public const MIMETYPE_TO_EXTENSION = [
        'image/png'     => 'png',
        'image/jpeg'    => 'jpg',
        'image/svg+xml' => 'svg',
    ];

    final public const EXTENSION_TO_MIMETYPE = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
    ];

    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly LoggerInterface $logger,
        protected readonly RequestStack $requestStack,
        protected readonly ParameterBagInterface $params,
        protected readonly AuthorizationCheckerInterface $authorizationChecker,
        protected readonly TokenStorageInterface $tokenStorage,
        protected readonly HttpKernelInterface $httpKernel,
        protected readonly ConfigurationService $config,
        protected readonly RouterInterface $router,
        protected readonly Environment $twig,
        #[Autowire('%kernel.project_dir%')]
        protected string $projectDir,
        #[Autowire('%domjudge.vendordir%')]
        protected string $vendorDir,
    ) {}

    /**
     * Return all the contests that are currently active indexed by contest ID.
     *
     * @param int|null $onlyOfTeam  Get only contests for the given team.
     * @param bool     $alsofuture  If true, also get future contests.
     * @param bool     $onlyPublic  If true, only get public contests. Only allowed when $onlyOfTeam is not specified.
     * @param bool     $honorCookie If true, return the currently selected contest if selected by the contest cookie
     *
     * @return Contest[]
     */
    public function getCurrentContests(
        ?int $onlyOfTeam = null,
        bool $alsofuture = false,
        bool $onlyPublic = false,
        bool $honorCookie = false
    ): array {
        if ($honorCookie) {
            $contest = $this->getCurrentContest(onlyOfTeam: $onlyOfTeam, onlyPublic: $onlyPublic);
            if ($contest) {
                return [$contest->getCid() => $contest];
            }
        }

        if ($onlyPublic && isset($onlyOfTeam)) {
            throw new InvalidArgumentException('Not allowed to specify a team and requesting public only.');
        }
        $now = Utils::now();
        $qb  = $this->em->createQueryBuilder();
        $qb->select('c')->from(Contest::class, 'c', 'c.cid');
        if (isset($onlyOfTeam)) {
            $qb->leftJoin('c.teams', 'ct')
                ->leftJoin('c.team_categories', 'tc')
                ->leftJoin('tc.teams', 'tct')
                ->andWhere('ct.teamid = :teamid OR tct.teamid = :teamid OR c.openToAllTeams = 1')
                ->setParameter('teamid', $onlyOfTeam);
        } elseif ($onlyPublic) {
            $qb->andWhere('c.public = 1');
        }
        $qb->andWhere('c.enabled = 1')
            ->andWhere('c.deactivatetime IS NULL OR c.deactivatetime > :now')
            ->setParameter('now', $now)
            ->orderBy('c.activatetime');

        if (!$alsofuture) {
            $qb->andWhere('c.activatetime <= :now');
        }

        return $qb->getQuery()->getResult();
    }

    public function getCurrentContestCookie(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || $request->cookies === null) {
            return null;
        }
        return $request->cookies->get('domjudge_cid');
    }

    /**
     * Get the currently selected contest.
     * @param int|null $onlyOfTeam Get only contests for the given team.
     * @param bool     $onlyPublic If true, only get public contests. Only allowed when $onlyOfTeam is not specified.
     */
    public function getCurrentContest(?int $onlyOfTeam = null, bool $onlyPublic = false): ?Contest
    {
        $contests = $this->getCurrentContests($onlyOfTeam, onlyPublic: $onlyPublic);
        if ($this->requestStack->getCurrentRequest()) {
            $selected_cid = $this->getCurrentContestCookie();
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

    public function getContest(int $cid): ?Contest
    {
        return $this->em->getRepository(Contest::class)->find($cid);
    }

    public function getTeam(?int $teamid): ?Team
    {
        return $this->em->getRepository(Team::class)->find($teamid);
    }

    public function getProblem(?int $probid): ?Problem
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

    public function getClientIp(): string
    {
        return $this->requestStack->getMainRequest()->getClientIp();
    }

    public function getUser(): ?User
    {
        $token = $this->tokenStorage->getToken();
        if ($token == null) {
            return null;
        }

        $user = $token->getUser();

        // Ignore user objects if they aren't an App user.
        // Covers cases where users are not logged in.
        if (!is_a($user, 'App\Entity\User')) {
            return null;
        }

        return $user;
    }

    public function getCookie(string $cookieName): bool|float|int|string|InputBag|null
    {
        if (!$this->requestStack->getCurrentRequest()) {
            return null;
        }
        return $this->requestStack->getCurrentRequest()->cookies->get($cookieName);
    }

    public function setCookie(
        string $cookieName,
        string $value = '',
        int $expire = 0,
        ?string $path = null,
        string $domain = '',
        bool $secure = false,
        bool $httponly = false,
        ?Response $response = null
    ): Response {
        if ($response === null) {
            $response = new Response();
        }
        if ($path === null) {
            $path = $this->requestStack->getCurrentRequest()->getBasePath();
        }

        $response->headers->setCookie(Cookie::create($cookieName, $value, $expire, $path, $domain, $secure, $httponly, false, null));

        return $response;
    }

    public function clearCookie(
        string $cookieName,
        ?string $path = null,
        string $domain = '',
        bool $secure = false,
        bool $httponly = false,
        ?Response $response = null
    ): Response {
        if ($response === null) {
            $response = new Response();
        }
        if ($path === null) {
            $path = $this->requestStack->getCurrentRequest()->getBasePath();
        }

        $response->headers->clearCookie($cookieName, $path, $domain, $secure, $httponly);
        return $response;
    }

    /**
     * @return array<array{'clarid': int, 'body': string}>
     */
    public function getUnreadClarifications(): array
    {
        $user           = $this->getUser();
        $team           = $user->getTeam();
        $clarifications = $team->getUnreadClarifications();
        $contest        = $this->getCurrentContest($team->getTeamId());
        $unreadClarifications = [];
        if ($contest === null) {
            return $unreadClarifications;
        }
        foreach ($clarifications as $clar) {
            if ($clar->getContest()->getCid() === $contest->getCid()) {
                $unreadClarifications[] = [
                    'clarid' => $clar->getClarid(),
                    'body' => $clar->getBody(),
                ];
            }
        }
        return $unreadClarifications;
    }

    /**
     * @return array{clarifications: array<array{clarid: int, body: string}>,
     *               judgehosts: array<array{hostname: string, polltime: float}>,
     *               rejudgings: array<array{rejudgingid: int, starttime: string, endtime: string|float}>,
     *               internal_errors: array<array{errorid: int, description: string}>,
     *               balloons: array<array{balloonid: int, name: string, location: string|null, pname: string}>,
     *               shadow_difference_count: int,
     *               external_contest_source_is_down: bool,
     *               external_source_warning_count: int}
     */
    public function getUpdates(): array
    {
        $contest = $this->getCurrentContest();

        $clarifications                = [];
        $judgehosts                    = [];
        $rejudgings                    = [];
        $internal_errors               = [];
        $balloons                      = [];
        $shadow_difference_count       = 0;
        $down_external_contest_source  = null;
        $external_source_warning_count = [];

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
                ->andWhere('j.enabled = 1')
                ->andWhere('j.hidden = 0')
                ->andWhere('j.polltime < :i')
                ->setParameter('i', time() - $this->config->get('judgehost_critical'))
                ->getQuery()->getResult();

            $rejudgings = $this->em->createQueryBuilder()
                ->select('r.rejudgingid, r.starttime, r.endtime')
                ->from(Rejudging::class, 'r')
                ->andWhere('r.endtime is null');
            if ($contest !== null) {
                $rejudgings = $rejudgings->join('r.submissions', 's')
                    ->andWhere('s.contest = :contest')
                    ->setParameter('contest', $contest->getCid())
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

            if ($this->shadowMode()) {
                if ($contest) {
                    $shadow_difference_count = $this->em->createQueryBuilder()
                        ->from(Submission::class, 's')
                        ->innerJoin('s.external_judgements', 'ej', Join::WITH, 'ej.valid = 1')
                        ->leftJoin('s.judgings', 'j', Join::WITH, 'j.valid = 1')
                        ->select('COUNT(s.submitid)')
                        ->andWhere('s.contest = :contest')
                        ->andWhere('s.externalid IS NOT NULL')
                        ->andWhere('ej.result IS NOT NULL')
                        ->andWhere('(j.result IS NOT NULL AND ej.result != j.result) OR s.importError IS NOT NULL')
                        ->andWhere('ej.verified = false')
                        ->setParameter('contest', $contest)
                        ->getQuery()
                        ->getSingleScalarResult();
                }

                $down_external_contest_source = $this->em->createQueryBuilder()
                    ->select('ecs.extsourceid', 'ecs.lastPollTime')
                    ->from(ExternalContestSource::class, 'ecs')
                    ->andWhere('ecs.contest = :contest')
                    ->andWhere('ecs.lastPollTime < :i OR ecs.lastPollTime is NULL OR ecs.lastHTTPCode != 200')
                    ->setParameter('contest', $contest)
                    ->setParameter('i', time() - $this->config->get('external_contest_source_critical'))
                    ->getQuery()->getOneOrNullResult();

                $external_source_warning_count = $this->em->createQueryBuilder()
                                                     ->select('COUNT(w.extwarningid)')
                                                     ->from(ExternalSourceWarning::class, 'w')
                                                     ->innerJoin('w.externalContestSource', 'ecs')
                                                     ->andWhere('ecs.contest = :contest')
                                                     ->setParameter('contest', $contest)
                                                     ->getQuery()
                                                     ->getSingleScalarResult();
            }
        }

        if ($this->checkrole('balloon') && $contest) {
            $balloonsQuery = $this->em->createQueryBuilder()
                ->select('b.balloonid', 't.name', 't.location', 'p.name AS pname')
                ->from(Balloon::class, 'b')
                ->leftJoin('b.submission', 's')
                ->leftJoin('s.problem', 'p')
                ->leftJoin('s.contest', 'co')
                ->leftJoin('p.contest_problems', 'cp', Join::WITH, 'co.cid = cp.contest AND p.probid = cp.problem')
                ->leftJoin('s.team', 't')
                ->andWhere('co.cid = :cid')
                ->andWhere('b.done = 0')
                ->setParameter('cid', $contest->getCid());

            $freezetime = $contest->getFreezeTime();
            if ($freezetime !== null && !(bool)$this->config->get('show_balloons_postfreeze')) {
                $balloonsQuery
                    ->andWhere('s.submittime < :freeze')
                    ->setParameter('freeze', $freezetime);
            }

            $balloons = $balloonsQuery->getQuery()->getResult();
        }

        return [
            'clarifications' => $clarifications,
            'judgehosts' => $judgehosts,
            'rejudgings' => $rejudgings,
            'internal_errors' => $internal_errors,
            'balloons' => $balloons,
            'shadow_difference_count' => $shadow_difference_count,
            'external_contest_source_is_down' => $down_external_contest_source !== null,
            'external_source_warning_count' => $external_source_warning_count,
        ];
    }

    /**
     * Run the given callable with all roles enabled.
     *
     * This will result in all calls to checkrole() to return true.
     */
    public function withAllRoles(callable $callable, ?UserInterface $user = null): void
    {
        $currentToken = $this->tokenStorage->getToken();
        // We need a 'user' to create a token. However, even if you
        // are not logged in, a (anonymous) user is returned. This
        // check is just here to make sure the code does not crash
        // in strange circumstances.
        if (!$user && $currentToken && $currentToken->getUser()) {
            $user = $currentToken->getUser();
        }
        if ($user) {
            $this->tokenStorage->setToken(
                new UsernamePasswordToken(
                    $user,
                    'main',
                    ['ROLE_ADMIN']
                )
            );
        }
        $callable();
        $this->tokenStorage->setToken($currentToken);
    }

    /**
     * Log an action to the auditlog table.
     */
    public function auditlog(
        string $datatype,
        mixed $dataid,
        string $action,
        mixed $extraInfo = null,
        ?string $forceUsername = null,
        string|int|null $cid = null
    ): void {
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
     */
    public function alert(string $messageType, string $description = ''): void
    {
        $alert = $this->params->get('domjudge.libdir') . '/alert';
        system(sprintf('%s %s %s &', $alert, escapeshellarg($messageType), escapeshellarg($description)));
    }

    /**
     * Dis- or re-enable what caused an internal error.
     *
     * @param array{kind: string, probid?: string, hostname?: string, langid?: string,
     *              execid?: string, testcaseid?: string} $disabled
     */
    public function setInternalError(array $disabled, ?Contest $contest, ?bool $enabled): void
    {
        switch ($disabled['kind']) {
            case 'problem':
                $this->em->createQueryBuilder()
                    ->update(ContestProblem::class, 'p')
                    ->set('p.allowJudge', ':enabled')
                    ->andWhere('p.contest = :cid')
                    ->andWhere('p.problem = :probid')
                    ->setParameter('enabled', $enabled)
                    ->setParameter('cid', $contest)
                    ->setParameter('probid', $disabled['probid'])
                    ->getQuery()
                    ->execute();
                break;
            case 'judgehost':
                $this->em->createQueryBuilder()
                    ->update(Judgehost::class, 'j')
                    ->set('j.enabled', ':enabled')
                    ->andWhere('j.hostname = :hostname')
                    ->setParameter('enabled', $enabled)
                    ->setParameter('hostname', $disabled['hostname'])
                    ->getQuery()
                    ->execute();
                break;
            case 'language':
                $this->em->createQueryBuilder()
                    ->update(Language::class, 'lang')
                    ->set('lang.allowJudge', ':enabled')
                    ->andWhere('lang.langid = :langid')
                    ->setParameter('enabled', $enabled)
                    ->setParameter('langid', $disabled['langid'])
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
                /** @var Problem $problem */
                foreach ($this->getProblemsForExecutable($executable) as $problem) {
                    /** @var ContestProblem $contestProblem */
                    foreach ($problem->getContestProblems() as $contestProblem) {
                        $contestProblem->setAllowJudge($enabled);
                    }
                }
                // FIXME: Also disable debug scripts
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
     * Perform an internal API request to the given URL with the given data.
     *
     * @param array<string, string[]> $queryOrPostData
     * @param UploadedFile[] $files
     *
     * @return mixed|null
     */
    public function internalApiRequest(string $url, string $method = Request::METHOD_GET, array $queryOrPostData = [], array $files = [], bool $throw = false)
    {
        $request  = Request::create('/api' . $url, $method, $queryOrPostData, [], $files);
        if ($this->requestStack->getCurrentRequest() && $this->requestStack->getCurrentRequest()->hasSession()) {
            $request->setSession($this->requestStack->getSession());
        } else {
            $request->setSession(new Session());
        }
        $response = $this->httpKernel->handle($request, HttpKernelInterface::SUB_REQUEST);

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            if ($throw) {
                throw new HttpException($status, $response->getContent());
            }
            $this->logger->warning(
                "executing internal %s request to url %s: http status code: %d, response: %s",
                [ $method, $url, $status, $response ]
            );
            return null;
        }

        $content = $response->getContent();

        if ($content === '') {
            return null;
        }

        return Utils::jsonDecode($content);
    }

    public function getDomjudgeEtcDir(): string
    {
        return $this->params->get('domjudge.etcdir');
    }

    public function getDomjudgeTmpDir(): string
    {
        return $this->params->get('domjudge.tmpdir');
    }

    public function getDomjudgeWebappDir(): string
    {
        return $this->params->get('domjudge.webappdir');
    }

    /**
     * @return array{'name': string, 'link'?: string, 'icon'?: string}
     */
    public function getDocLinks(): array
    {
        return $this->params->get('domjudge.doc_links');
    }

    public function getCacheDir(): string
    {
        return $this->params->get('kernel.cache_dir');
    }

    public function openZipFile(string $filename): ZipArchive
    {
        $zip = new ZipArchive();
        $res = $zip->open($filename, ZipArchive::CHECKCONS);
        if ($res === ZipArchive::ER_NOZIP) {
            throw new BadRequestHttpException('No valid ZIP archive given.');
        } elseif ($res === ZipArchive::ER_INCONS) {
            throw new BadRequestHttpException(
                'ZIP archive is inconsistent; this can happen when using built-in graphical ZIP tools on Mac OS or Ubuntu,'
            . ' use the command line zip tool instead, e.g.: zip -r ../problemarchive.zip *');
        } elseif ($res === ZIPARCHIVE::ER_MEMORY) {
            throw new ServiceUnavailableHttpException(null, 'Not enough memory to extract ZIP archive.');
        } elseif ($res !== true) {
            throw new ServiceUnavailableHttpException(null,
                'Unknown error while extracting ZIP archive: ' . print_r($res, true));
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
     * @param bool        $asTeam   Print the file as the team associated with the user
     * @return array{0: bool, 1: string}
     */
    public function printUserFile(
        string $filename,
        string $origname,
        ?string $language,
        ?bool $asTeam = false,
    ): array {
        $user = $this->getUser();
        $team = $user->getTeam();
        if ($asTeam && $team !== null) {
            $teamid = $team->getLabel() ?? $team->getExternalid();
            return $this->printFile($filename, $origname, $language, $user->getUserIdentifier(), $team->getEffectiveName(), $teamid, $team->getLocation());
        }
        return $this->printFile($filename, $origname, $language, $user->getUserIdentifier());
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
     * @param string|null $teamid   Teamid of the team this user belongs to, if any
     * @param string|null $location Location of the team, if any.
     * @return array{0: bool, 1: string}
     */
    public function printFile(
        string $filename,
        string $origname,
        ?string $language,
        string $username,
        ?string $teamname = null,
        ?string $teamid = null,
        ?string $location = null
    ): array {
        $printCommand = $this->config->get('print_command');
        if (empty($printCommand)) {
            return [false, 'Printing not enabled'];
        }

        $replaces = [
            '[file]' => escapeshellarg($filename),
            '[original]' => escapeshellarg($origname),
            '[language]' => escapeshellarg($language ?? ''),
            '[username]' => escapeshellarg($username),
            '[teamname]' => escapeshellarg($teamname ?? ''),
            '[teamid]' => escapeshellarg($teamid ?? ''),
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
     * Get a ZIP with sample data.
     *
     * @return string Content of samples zip file.
     */
    public function getSamplesZipContent(ContestProblem $contestProblem): string
    {
        if ($contestProblem->getProblem()->isInteractiveProblem()) {
            throw new NotFoundHttpException(sprintf('Problem p%d has no downloadable samples', $contestProblem->getProbid()));
        }

        $zip = new ZipArchive();
        if (!($tempFilename = tempnam($this->getDomjudgeTmpDir(), "export-"))) {
            throw new ServiceUnavailableHttpException(null, 'Could not create temporary file.');
        }

        $res = $zip->open($tempFilename, ZipArchive::OVERWRITE);
        if ($res !== true) {
            throw new ServiceUnavailableHttpException(null, 'Could not create temporary zip file.');
        }

        $this->addSamplesToZip($zip, $contestProblem);

        $zip->close();
        $zipFileContents = file_get_contents($tempFilename);
        unlink($tempFilename);
        return $zipFileContents;
    }

    protected function addSamplesToZip(ZipArchive $zip, ContestProblem $problem, ?string $directory = null): void
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
            ->setParameter('problem', $problem->getProblem())
            ->setParameter('contest', $problem->getContest())
            ->orderBy('tc.testcaseid')
            ->getQuery()
            ->getResult();

        foreach ($testcases as $index => $testcase) {
            foreach (['input', 'output'] as $type) {
                $extension = Testcase::EXTENSION_MAPPING[$type];

                $filename = sprintf("%s.%s", $index + 1, $extension);
                if ($directory) {
                    $filename = sprintf('%s/%s', $directory, $filename);
                }
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
    }

    public function getSamplesZipStreamedResponse(ContestProblem $contestProblem): StreamedResponse
    {
        $zipFileContent = $this->getSamplesZipContent($contestProblem);
        $outputFilename = sprintf('samples-%s.zip', $contestProblem->getShortname());
        return Utils::streamAsBinaryFile($zipFileContent, $outputFilename, 'zip');
    }

    public function getSamplesZipForContest(Contest $contest): StreamedResponse
    {
        // Note, we reload the contest with the problems and attachments, to reduce the number of queries
        // We do not load the testcases here since addSamplesToZip loads them
        /** @var Contest $contest */
        $contest = $this->em->createQueryBuilder()
            ->from(Contest::class, 'c')
            ->innerJoin('c.problems', 'cp')
            ->innerJoin('cp.problem', 'p')
            ->leftJoin('p.attachments', 'a')
            ->leftJoin('p.problemStatementContent', 'content')
            ->select('c', 'cp', 'p', 'a', 'content')
            ->andWhere('c.cid = :cid')
            ->setParameter('cid', $contest->getCid())
            ->getQuery()
            ->getSingleResult();

        $zip = new ZipArchive();
        if (!($tempFilename = tempnam($this->getDomjudgeTmpDir(), "export-"))) {
            throw new ServiceUnavailableHttpException(null, 'Could not create temporary file.');
        }

        $res = $zip->open($tempFilename, ZipArchive::OVERWRITE);
        if ($res !== true) {
            throw new ServiceUnavailableHttpException(null, 'Could not create temporary zip file.');
        }

        /** @var ContestProblem $problem */
        foreach ($contest->getProblems() as $problem) {
            // We don't include the samples for interactive problems.
            if (!$problem->getProblem()->isInteractiveProblem()) {
                $this->addSamplesToZip($zip, $problem, $problem->getShortname());
            }

            if ($problem->getProblem()->getProblemstatementType()) {
                $filename    = sprintf('%s/statement.%s', $problem->getShortname(), $problem->getProblem()->getProblemstatementType());
                $zip->addFromString($filename, $problem->getProblem()->getProblemstatement());
            }

            /** @var ProblemAttachment $attachment */
            foreach ($problem->getProblem()->getAttachments() as $attachment) {
                $filename = sprintf('%s/attachments/%s', $problem->getShortname(), $attachment->getName());
                $zip->addFromString($filename, $attachment->getContent()->getContent());
                if ($attachment->getContent()->isExecutable()) {
                    // 100755 = regular file, executable
                    $zip->setExternalAttributesName(
                        $filename,
                        ZipArchive::OPSYS_UNIX,
                        octdec('100755') << 16
                    );
                }
            }
        }

        if ($contest->getContestProblemsetType()) {
            $filename = sprintf('contest.%s', $contest->getContestProblemsetType());
            $zip->addFromString($filename, $contest->getContestProblemset());
        }

        $zip->close();
        $zipFileContents = file_get_contents($tempFilename);
        unlink($tempFilename);

        return Utils::streamAsBinaryFile($zipFileContents, 'samples.zip', 'zip');
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getAttachmentStreamedResponse(ContestProblem $contestProblem, int $attachmentId): StreamedResponse
    {
        /** @var ProblemAttachment|null $attachment */
        $attachment = $this->em->createQueryBuilder()
            ->from(ProblemAttachment::class, 'a')
            ->join('a.problem', 'p')
            ->join('p.contest_problems', 'cp', Join::WITH, 'cp.contest = :contest')
            ->join('a.content', 'ac')
            ->select('a', 'ac')
            ->andWhere('a.problem = :problem')
            ->andWhere('a.attachmentid = :attachmentid')
            ->setParameter('problem', $contestProblem->getProbid())
            ->setParameter('contest', $contestProblem->getContest())
            ->setParameter('attachmentid', $attachmentId)
            ->getQuery()
            ->getOneOrNullResult();
        if (!$attachment) {
            throw new NotFoundHttpException(sprintf('Problem p%d not found or not available', $contestProblem->getProbid()));
        }

        return $attachment->getStreamedResponse();
    }

    /**
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getContestStats(Contest $contest): ContestStatus
    {
        $numSubmissions = (int)$this->em
            ->createQuery(
                'SELECT COUNT(s)
                FROM App\Entity\Submission s
                WHERE s.contest = :cid')
            ->setParameter('cid', $contest->getCid())
            ->getSingleScalarResult();
        $numQueued = (int)$this->em
            ->createQuery(
                'SELECT COUNT(s)
                FROM App\Entity\Submission s
                LEFT JOIN App\Entity\Judging j WITH (j.submission = s.submitid AND j.valid != 0)
                WHERE s.contest = :cid
                AND j.result IS NULL
                AND s.valid = 1')
            ->setParameter('cid', $contest->getCid())
            ->getSingleScalarResult();
        $numJudging = (int)$this->em
            ->createQuery(
                'SELECT COUNT(s)
                FROM App\Entity\Submission s
                LEFT JOIN App\Entity\Judging j WITH (j.submission = s.submitid)
                WHERE s.contest = :cid
                AND j.result IS NULL
                AND j.valid = 1
                AND s.valid = 1')
            ->setParameter('cid', $contest->getCid())
            ->getSingleScalarResult();
        return new ContestStatus($numSubmissions, $numQueued, $numJudging);
    }

    /**
     * @return array{'problems': ContestProblem[], 'samples': string[], 'showLimits': bool,
     *               'defaultMemoryLimit': int, 'timeFactorDiffers': bool,
     *               'stats': array{'numBuckets': int, 'maxBucketSizeCorrect': int,
     *                              'maxBucketSizeCorrect': int, 'maxBucketSizeIncorrect': int,
     *                              'problems': array<array{'correct': array<array{'start': DateTime, 'end': DateTime, 'count': int}>,
     *                                                      'incorrect': array<array{'start': DateTime, 'end': DateTime, 'count': int}>}>}}
     * @throws NonUniqueResultException
     */
    public function getTwigDataForProblemsAction(
        StatisticsService $statistics,
        ?int $teamId = null,
        bool $forJury = false
    ): array {
        $contest            = isset($teamId) ? $this->getCurrentContest($teamId) : $this->getCurrentContest(onlyPublic: !$forJury);
        $showLimits         = (bool)$this->config->get('show_limits_on_team_page');
        $defaultMemoryLimit = (int)$this->config->get('memory_limit');
        $timeFactorDiffers  = false;
        if ($showLimits) {
            $languages = $this->getAllowedLanguagesForContest($contest);
            $timeFactorDiffers = $this->em->createQueryBuilder()
                    ->from(Language::class, 'l')
                    ->select('COUNT(l)')
                    ->andWhere('l.timeFactor <> 1')
                    ->andWhere('l IN (:languages)')
                    ->setParameter('languages', $languages)
                    ->getQuery()
                    ->getSingleScalarResult() > 0;
        }

        $problems = [];
        $samples = [];
        if ($contest && ($forJury || $contest->getFreezeData()->started())) {
            $problems = $this->em->createQueryBuilder()
                ->from(ContestProblem::class, 'cp')
                ->join('cp.problem', 'p')
                ->leftJoin('p.testcases', 'tc')
                ->leftJoin('p.attachments', 'a')
                ->select('p', 'cp', 'a')
                ->andWhere('cp.contest = :contest')
                ->andWhere('cp.allowSubmit = 1')
                ->setParameter('contest', $contest)
                ->addOrderBy('cp.shortname')
                ->getQuery()
                ->getResult();

            $samplesData = $this->em->createQueryBuilder()
                ->from(ContestProblem::class, 'cp')
                ->join('cp.problem', 'p')
                ->leftJoin('p.testcases', 'tc')
                ->select('p.probid', 'SUM(tc.sample) AS numsamples')
                ->andWhere('cp.contest = :contest')
                ->andWhere('cp.allowSubmit = 1')
                ->setParameter('contest', $contest)
                ->groupBy('cp.problem')
                ->getQuery()
                ->getResult();

            foreach ($samplesData as $sample) {
                $samples[$sample['probid']] = $sample['numsamples'];
            }
        }

        $data = [
            'problems' => $problems,
            'samples' => $samples,
            'showLimits' => $showLimits,
            'defaultMemoryLimit' => $defaultMemoryLimit,
            'timeFactorDiffers' => $timeFactorDiffers,
        ];

        if ($contest && $this->config->get('show_public_stats')) {
            $freezeData = new FreezeData($contest);
            $showVerdictsInFreeze = $freezeData->showFinal(false) || $contest->getFreezetime() === null;
            $data['stats'] = $statistics->getGroupedProblemsStats(
                $contest,
                array_map(fn(ContestProblem $problem) => $problem->getProblem(), $problems),
                $showVerdictsInFreeze,
                (bool)$this->config->get('verification_required')
            );
        }

        return $data;
    }

    public function createImmutableExecutable(ZipArchive $zip): ImmutableExecutable
    {
        $propertyFile = 'domjudge-executable.ini';
        $rank = 0;
        $files = [];
        for ($idx = 0; $idx < $zip->numFiles; $idx++) {
            $filename = basename($zip->getNameIndex($idx));
            if ($filename === $propertyFile) {
                // This file is only for setting metadata of the executable,
                // see webapp/src/Controller/Jury/ExecutableController.php.
                continue;
            }

            // In doubt make files executable, but try to read it from the zip file.
            $executableBit = true;
            if ($zip->getExternalAttributesIndex($idx, $opsys, $attr)
                && $opsys==ZipArchive::OPSYS_UNIX
                && (($attr >> 16) & 0100) === 0) {
                $executableBit = false;
            }
            // As a special case force these files to be executable.
            if ($filename==='build' || $filename==='run') {
                $executableBit = true;
            }
            $executableFile = new ExecutableFile();
            $executableFile
                ->setRank($rank)
                ->setFilename($filename)
                ->setFileContent($zip->getFromIndex($idx))
                ->setIsExecutable($executableBit);
            $this->em->persist($executableFile);
            $files[] = $executableFile;
            $rank++;
        }
        $immutableExecutable = new ImmutableExecutable($files);
        $this->em->persist($immutableExecutable);
        $this->em->flush();
        return $immutableExecutable;
    }

    public function helperUnblockJudgeTasks(): QueryBuilder
    {
        return $this->em->createQueryBuilder()
            ->select('j')
            ->from(Judging::class, 'j')
            ->leftJoin(JudgeTask::class, 'jt', Join::WITH, 'j.judgingid = jt.jobid')
            ->where('jt.jobid IS NULL');
    }

    public function unblockJudgeTasksForLanguage(string $langId): void
    {
        // These are all the judgings that don't have associated judgetasks yet. Check whether we unblocked them.
        $judgings = $this->helperUnblockJudgeTasks()
            ->join(Submission::class, 's', Join::WITH, 'j.submission = s.submitid')
            ->join(Language::class, 'l', Join::WITH, 's.language = l.langid')
            ->andWhere('l.langid = :langid')
            ->setParameter('langid', $langId)
            ->getQuery()
            ->getResult();
        foreach ($judgings as $judging) {
            $this->maybeCreateJudgeTasks($judging);
        }
    }

    public function unblockJudgeTasksForProblem(int $probId): void
    {
        // These are all the judgings that don't have associated judgetasks yet. Check whether we unblocked them.
        $judgings = $this->helperUnblockJudgeTasks()
            ->join(Submission::class, 's', Join::WITH, 'j.submission = s.submitid')
            ->join(Problem::class, 'p', Join::WITH, 's.problem = p.probid')
            ->andWhere('p.probid = :probid')
            ->setParameter('probid', $probId)
            ->getQuery()
            ->getResult();
        foreach ($judgings as $judging) {
            $this->maybeCreateJudgeTasks($judging);
        }
    }

    public function unblockJudgeTasksForSubmission(string $submissionId): void
    {
        // These are all the judgings that don't have associated judgetasks yet. Check whether we unblocked them.
        $judgings = $this->helperUnblockJudgeTasks()
            ->join(Submission::class, 's', Join::WITH, 'j.submission = s.submitid')
            ->andWhere('j.submission = :submissionid')
            ->setParameter('submissionid', $submissionId)
            ->getQuery()
            ->getResult();
        foreach ($judgings as $judging) {
            $this->maybeCreateJudgeTasks($judging, JudgeTask::PRIORITY_DEFAULT, true);
        }
    }

    public function unblockJudgeTasks(): void
    {
        // These are all the judgings that don't have associated judgetasks yet. Check whether we unblocked them.
        $judgings = $this->helperUnblockJudgeTasks()
            ->getQuery()
            ->getResult();
        foreach ($judgings as $judging) {
            $this->maybeCreateJudgeTasks($judging);
        }
    }

    public function maybeCreateJudgeTasks(Judging $judging, int $priority = JudgeTask::PRIORITY_DEFAULT, bool $manualRequest = false, int $overshoot = 0): void
    {
        $submission = $judging->getSubmission();
        $problem    = $submission->getContestProblem();
        $language   = $submission->getLanguage();

        if ($submission->isImportError()) {
            return;
        }
        if (!$this->allowJudge($problem, $submission, $language, $manualRequest)) {
            return;
        }

        $this->actuallyCreateJudgetasks($priority, $judging, $overshoot);

        $team = $submission->getTeam();
        $result = $this->em->createQueryBuilder()
            ->from(QueueTask::class, 'qt')
            ->select('MAX(qt.teamPriority) AS max, COUNT(qt.judging) AS count')
            ->andWhere('qt.team = :team')
            ->andWhere('qt.priority = :priority')
            ->andWhere('qt.teamPriority >= :cutoffTeamPriority')
            ->setParameter('team', $team)
            ->setParameter('priority', $priority)
            // Only consider judgings which have been placed at most 60 virtual seconds ago.
            ->setParameter('cutoffTeamPriority', (int)$submission->getSubmittime() - 60)
            ->getQuery()
            ->getOneOrNullResult();

        // Teams that submit frequently slow down the judge queue but should not be able to starve other teams of their
        // deserved and timely judgement.
        // For every "recent" pending job in the queue by that team, add a penalty (60s). Our definiition of "recent"
        // includes all submissions that have been placed at a virtual time (including penalty) more recent than 60s
        // ago. This is done in order to avoid punishing teams who submit while their submissions are stuck in the queue
        // for other reasons, for example an internal error for a problem or language.
        // To ensure that submissions will still be ordered by submission time, use at least the current maximal team
        // priority.
        // Jobs with a lower team priority are judged earlier.
        // Assume the following situation:
        // - a team submits three times at time X
        // - the team priority for the submissions are X, X+60, X+120 respectively
        // - assume that the first two submissions are judged after 5 seconds, the team submits again
        // - the new submission would get X+5+60 (since there's only one of their submissions still to be worked on),
        //   but we want to judge submissions of this team in order, so we take the current max (X+120) and add 1.
        $teamPriority = (int)(max($result['max']+1, $submission->getSubmittime() + 60*$result['count']));
        // Use a direct query to speed things up
        $this->em->getConnection()->executeQuery(
            'INSERT INTO queuetask (judgingid, priority, teamid, teampriority, starttime)
             VALUES (:judgingid, :priority, :teamid, :teampriority, null)',
            [
                'judgingid' => $judging->getJudgingid(),
                'priority' => $priority,
                'teamid' => $team->getTeamid(),
                'teampriority' => $teamPriority,
            ]
        );
    }

    public function getImmutableCompareExecutable(ContestProblem $problem): ImmutableExecutable
    {
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

    /**
     * @return Problem[]
     */
    private function getProblemsForExecutable(Executable $executable): array
    {
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
     *
     * @param array<string, string> $params
     */
    public function apiRelativeUrl(string $route, array $params = []): string
    {
        $route = $this->router->generate($route, $params);
        $apiRootRoute = $this->router->generate('v4_api_root');
        $offset = str_ends_with($apiRootRoute, '/') ? 0 : 1;
        return substr($route, strlen($apiRootRoute) + $offset);
    }

    /**
     * Get asset files in the given directory with the given extension
     *
     * @return string[]
     */
    public function getAssetFiles(string $path): array
    {
        $customDir = sprintf('%s/public/%s', $this->params->get('kernel.project_dir'), $path);
        if (!is_dir($customDir)) {
            return [];
        }

        $results = [];
        foreach (scandir($customDir) as $file) {
            foreach (array_merge(['css','js'], static::MIMETYPE_TO_EXTENSION) as $extension) {
                if (str_contains($file, '.' . $extension)) {
                    $results[] = $file;
                }
            }
        }

        return $results;
    }

    /**
     * Get the path of an asset if it exists
     *
     * @param bool $fullPath If true, get the full path. If false, get the webserver relative path
     * @param string|null $forceExtension If set, also return the asset path if it does not exist currently and use the given extension
     */
    public function assetPath(?string $name, string $type, bool $fullPath = false, ?string $forceExtension = null): ?string
    {
        $prefix = $fullPath ? ($this->getDomjudgeWebappDir() . '/public/') : '';
        switch ($type) {
            case 'affiliation':
                $dir = 'images/affiliations';
                break;
            case 'team':
                $dir = 'images/teams';
                break;
            case 'contest':
                $dir = 'images/banners';
                break;
        }

        if (isset($dir)) {
            $assets = $this->getAssetFiles($dir);
            foreach (static::MIMETYPE_TO_EXTENSION as $extension) {
                if ($forceExtension === $extension || (!$forceExtension && in_array($name . '.' . $extension, $assets))) {
                    return sprintf('%s%s/%s.%s', $prefix, $dir, $name, $extension);
                }
            }
        }

        return null;
    }

    public function globalBannerAssetPath(): ?string
    {
        // This is put in a separate method (and not as a special case in assetPath) since
        // fullAssetPath uses assetPath as well and we do not want to show the 'delete banner'
        // checkbox when a global banner has been set.
        $bannerFiles = ['images/banner.png', 'images/banner.jpg', 'images/banner.svg'];
        foreach ($bannerFiles as $bannerFile) {
            if (file_exists($this->getDomjudgeWebappDir() . '/public/' . $bannerFile)) {
                return $bannerFile;
            }
        }

        return null;
    }

    /**
     * Get the full asset path for the given entity and property.
     */
    public function fullAssetPath(AssetEntityInterface $entity, string $property, ?string $forceExtension = null): ?string
    {
        if ($entity instanceof Team && $property == 'photo') {
            return $this->assetPath($entity->getExternalid(), 'team', true, $forceExtension);
        } elseif ($entity instanceof TeamAffiliation && $property == 'logo') {
            return $this->assetPath($entity->getExternalid(), 'affiliation', true, $forceExtension);
        } elseif ($entity instanceof Contest && $property == 'banner') {
            return $this->assetPath($entity->getExternalid(), 'contest', true, $forceExtension);
        }

        return null;
    }

    public function loadTeam(string $teamId, Contest $contest): Team
    {
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Team::class, 't')
            ->select('t')
            ->leftJoin('t.category', 'tc')
            ->leftJoin('t.contests', 'c')
            ->leftJoin('tc.contests', 'cc')
            ->andWhere('t.externalid = :team')
            ->andWhere('t.enabled = 1')
            ->setParameter('team', $teamId);

        if (!$contest->isOpenToAllTeams()) {
            $queryBuilder
                ->andWhere('c.cid = :cid OR cc.cid = :cid')
                ->setParameter('cid', $contest->getCid());
        }

        /** @var Team|null $team */
        $team = $queryBuilder->getQuery()->getOneOrNullResult();

        if (!$team) {
            throw new BadRequestHttpException(
                sprintf("Team with ID '%s' not found in contest or not enabled.", $teamId));
        }
        return $team;
    }

    public function getRunConfig(ContestProblem $problem, Submission $submission, int $overshoot = 0): string
    {
        $memoryLimit = $problem->getProblem()->getMemlimit();
        $outputLimit = $problem->getProblem()->getOutputlimit();
        if (empty($memoryLimit)) {
            $memoryLimit = $this->config->get('memory_limit');
        }
        if (empty($outputLimit)) {
            $outputLimit = $this->config->get('output_limit');
        }
        $runExecutable = $this->getImmutableRunExecutable($problem);

        return Utils::jsonEncode(
            [
                'time_limit' => $problem->getProblem()->getTimelimit() * $submission->getLanguage()->getTimeFactor(),
                'memory_limit' => $memoryLimit,
                'output_limit' => $outputLimit,
                'process_limit' => $this->config->get('process_limit'),
                'entry_point' => $submission->getEntryPoint(),
                'pass_limit' => $problem->getProblem()->getMultipassLimit(),
                'hash' => $runExecutable->getHash(),
                'overshoot' => $overshoot,
            ]
        );
    }

    public function getCompareConfig(ContestProblem $problem): string
    {
        $compareExecutable = $this->getImmutableCompareExecutable($problem);
        return Utils::jsonEncode(
            [
                'script_timelimit' => $this->config->get('script_timelimit'),
                'script_memory_limit' => $this->config->get('script_memory_limit'),
                'script_filesize_limit' => $this->config->get('script_filesize_limit'),
                'compare_args' => $problem->getProblem()->getSpecialCompareArgs(),
                'combined_run_compare' => $problem->getProblem()->isInteractiveProblem(),
                'hash' => $compareExecutable->getHash(),
            ]
        );
    }

    public function getCompileConfig(Submission $submission): string
    {
        $compileExecutable = $submission->getLanguage()->getCompileExecutable()->getImmutableExecutable();
        return Utils::jsonEncode(
            [
                'script_timelimit' => $this->config->get('script_timelimit'),
                'script_memory_limit' => $this->config->get('script_memory_limit'),
                'script_filesize_limit' => $this->config->get('script_filesize_limit'),
                'language_extensions' => $submission->getLanguage()->getExtensions(),
                'filter_compiler_files' => $submission->getLanguage()->getFilterCompilerFiles(),
                'hash' => $compileExecutable->getHash(),
            ]
        );
    }

    public function getScoreboardZip(
        Request $request,
        RequestStack $requestStack,
        ?Contest $contest,
        ScoreboardService $scoreboardService,
        bool $forceUnfrozen = false
    ): StreamedResponse {
        $data = $scoreboardService->getScoreboardTwigData(
                request: $request,
                response: null,
                refreshUrl: '',
                jury: false,
                public: true,
                static: true,
                contest: $contest,
                forceUnfrozen: $forceUnfrozen
            ) + ['hide_menu' => true, 'current_contest' => $contest];

        $request = $requestStack->pop();
        // Use reflection to change the basepath property of the request, so we can detect
        // all requested and assets
        $requestReflection = new ReflectionClass($request);
        $basePathProperty  = $requestReflection->getProperty('basePath');
        $basePathProperty->setAccessible(true);
        $basePathProperty->setValue($request, '/CHANGE_ME');
        $requestStack->push($request);

        $contestPage = $this->twig->render('public/scoreboard.html.twig', $data);

        // Now get all assets that are used
        $assetRegex = '|/CHANGE_ME/([/a-z0-9_\-\.]*)(\??[/a-z0-9_\-\.=]*)|i';
        preg_match_all($assetRegex, $contestPage, $assetMatches);
        $contestPage = preg_replace($assetRegex, '$1$2', $contestPage);
        $contestPage = str_replace('/public/submissions-data.json', 'submissions-data.json', $contestPage);

        $zip = new ZipArchive();
        if (!($tempFilename = tempnam($this->getDomjudgeTmpDir(), "contest-"))) {
            throw new ServiceUnavailableHttpException(null, 'Could not create temporary file.');
        }

        $res = $zip->open($tempFilename, ZipArchive::OVERWRITE);
        if ($res !== true) {
            throw new ServiceUnavailableHttpException(null, 'Could not create temporary zip file.');
        }
        $zip->addFromString('index.html', $contestPage);

        $submissionsDataRequest  = Request::create('/public/submissions-data.json', Request::METHOD_GET);
        $submissionsDataRequest->setSession($this->requestStack->getSession());
        /** @var JsonResponse $response */
        $response = $this->httpKernel->handle($submissionsDataRequest, HttpKernelInterface::SUB_REQUEST);
        $submissionsData = $response->getContent();
        $zip->addFromString('submissions-data.json', $submissionsData);

        $publicPath = realpath(sprintf('%s/public/', $this->projectDir));
        foreach ($assetMatches[1] as $file) {
            $filepath = realpath($publicPath . '/' . $file);
            if (!str_starts_with($filepath, $publicPath) &&
                !str_starts_with($filepath, $this->vendorDir)
            ) {
                // Path outside of known good dirs: path traversal
                continue;
            }

            $zip->addFile($filepath, $file);
        }

        // Also copy in the webfonts
        $webfontsPath = $publicPath . '/webfonts/';
        foreach (glob($webfontsPath . '*') as $fontFile) {
            $fontName = basename($fontFile);
            $zip->addFile($fontFile, 'webfonts/' . $fontName);
        }
        $zip->close();

        return Utils::streamZipFile($tempFilename, 'scoreboard.zip');
    }

    /**
     * @return array{backgroundColors: array<string>}
     */
    public function getScoreboardCategoryColorCss(): array {
        $backgroundColors = array_map(fn($x) => ( $x->getColor() ?? '#FFFFFF' ), $this->em->getRepository(TeamCategory::class)->findAll());
        $backgroundColors = array_merge($backgroundColors, ['#FFFF99']);
        return ['backgroundColors' => $backgroundColors];
    }

    private function allowJudge(ContestProblem $problem, Submission $submission, Language $language, bool $manualRequest): bool
    {
        if (!$problem->getAllowJudge() || !$language->getAllowJudge()) {
            return false;
        }
        $evalOnDemand = false;
        // We have 2 cases, the problem picks the global value or the value is set.
        if ($problem->determineOnDemand($this->config->get('lazy_eval_results'))) {
            $evalOnDemand = true;

            // Special case, we're shadow and someone submits on our side in that case
            // we're not super lazy.
            if ($this->shadowMode() && $submission->getExternalid() === ('dj-' . $submission->getSubmitid())) {
                $evalOnDemand = false;
            }
            if ($manualRequest) {
                // When explicitly requested, judge the submission.
                $evalOnDemand = false;
            }
        }
        return !$evalOnDemand;
    }

    private function actuallyCreateJudgetasks(int $priority, Judging $judging, int $overshoot = 0): void
    {
        $submission = $judging->getSubmission();
        $problem    = $submission->getContestProblem();
        // We use a mass insert query, since that is way faster than doing a separate insert for each testcase.
        // We first insert judgetasks, then select their ID's and finally insert the judging runs.

        // Step 1: Create the template for the judgetasks.
        $compileExecutable = $submission->getLanguage()->getCompileExecutable()->getImmutableExecutable();
        $judgetaskInsertParams = [
            ':type' => JudgeTaskType::JUDGING_RUN,
            ':submitid' => $submission->getSubmitid(),
            ':priority' => $priority,
            ':jobid' => $judging->getJudgingid(),
            ':uuid' => $judging->getUuid(),
            ':compile_script_id' => $compileExecutable->getImmutableExecId(),
            ':compare_script_id' => $this->getImmutableCompareExecutable($problem)->getImmutableExecId(),
            ':run_script_id' => $this->getImmutableRunExecutable($problem)->getImmutableExecId(),
            ':compile_config' => $this->getCompileConfig($submission),
            ':run_config' => $this->getRunConfig($problem, $submission, $overshoot),
            ':compare_config' => $this->getCompareConfig($problem),
        ];

        $judgetaskDefaultParamNames = array_keys($judgetaskInsertParams);

        // Step 2: Create and insert the judgetasks.

        $testcases = $problem->getProblem()->getTestcases();
        if (count($testcases) < 1) {
            throw new BadRequestHttpException("No testcases set for problem {$problem->getProbid()}");
        }
        $judgetaskInsertParts = [];
        /** @var Testcase $testcase */
        foreach ($testcases as $testcase) {
            $judgetaskInsertParts[] = sprintf(
                '(%s, :testcase_id%d, :testcase_hash%d)',
                implode(', ', $judgetaskDefaultParamNames),
                $testcase->getTestcaseid(),
                $testcase->getTestcaseid()
            );
            $judgetaskInsertParams[':testcase_id' . $testcase->getTestcaseid()] = $testcase->getTestcaseid();
            $judgetaskInsertParams[':testcase_hash' . $testcase->getTestcaseid()] = $testcase->getTestcaseHash();
        }
        $judgetaskColumns = array_map(fn(string $column) => substr($column, 1), $judgetaskDefaultParamNames);
        $judgetaskInsertQuery = sprintf(
            'INSERT INTO judgetask (%s, testcase_id, testcase_hash) VALUES %s',
            implode(', ', $judgetaskColumns),
            implode(', ', $judgetaskInsertParts)
        );

        $judgetaskInsertParamsWithoutColon = [];
        foreach ($judgetaskInsertParams as $key => $param) {
            $key = str_replace(':', '', $key);
            $judgetaskInsertParamsWithoutColon[$key] = $param;
        }

        $this->em->getConnection()->executeQuery($judgetaskInsertQuery, $judgetaskInsertParamsWithoutColon);

        // Step 3: Insert the corresponding judging runs.
        $this->em->getConnection()->executeQuery(
            'INSERT INTO judging_run (judgingid, judgetaskid, testcaseid)
                    SELECT :judgingid, judgetaskid, testcase_id FROM judgetask
                    WHERE jobid = :judgingid ORDER BY judgetaskid',
            ['judgingid' => $judging->getJudgingid()]
        );
    }

    public function shadowMode(): bool
    {
        return (bool)$this->config->get('shadow_mode');
    }

    /** @return Language[] */
    public function getAllowedLanguagesForContest(?Contest $contest) : array {
        if ($contest) {
            $languages = $contest->getLanguages();
            if (!$languages->isEmpty()) {
                return $languages->toArray();
            }
        }
        return $this->em->createQueryBuilder()
            ->select('l')
            ->from(Language::class, 'l')
            ->where('l.allowSubmit = 1')
            ->getQuery()
            ->getResult();
    }
}
