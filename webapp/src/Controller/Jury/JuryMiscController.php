<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\API\GeneralInfoController as GI;
use App\Controller\BaseController;
use App\Entity\Contest;
use App\Entity\Judging;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ScoreboardService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/jury')]
class JuryMiscController extends BaseController
{
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        protected readonly EventLogService $eventLogService,
        protected readonly RequestStack $requestStack,
        KernelInterface $kernel,
    ) {
        parent::__construct($em, $eventLogService, $dj, $kernel);
    }

    #[IsGranted(new Expression("is_granted('ROLE_JURY') or is_granted('ROLE_BALLOON') or is_granted('ROLE_CLARIFICATION_RW')"))]
    #[Route(path: '', name: 'jury_index')]
    public function indexAction(ConfigurationService $config): Response
    {
        return $this->render('jury/index.html.twig', [
            'adminer_enabled' => $config->get('adminer_enabled'),
            'CCS_SPEC_API_URL' => GI::CCS_SPEC_API_URL,
        ]);
    }

    #[IsGranted(new Expression("is_granted('ROLE_JURY') or is_granted('ROLE_BALLOON')"))]
    #[Route(path: '/updates', methods: ['GET'], name: 'jury_ajax_updates')]
    public function updatesAction(): JsonResponse
    {
        return $this->json($this->dj->getUpdates());
    }

    #[IsGranted(new Expression("is_granted('ROLE_JURY') or is_granted('ROLE_BALLOON')"))]
    #[Route(path: '/ajax/{datatype}', methods: ['GET'], name: 'jury_ajax_data')]
    public function ajaxDataAction(Request $request, string $datatype): JsonResponse
    {
        $q  = $request->query->get('q');
        $qb = $this->em->createQueryBuilder();

        if ($datatype === 'affiliations') {
            $affiliations = $qb->from(TeamAffiliation::class, 'a')
                ->select('a.affilid', 'a.name', 'a.shortname')
                ->where($qb->expr()->like('a.name', '?1'))
                ->orWhere($qb->expr()->like('a.shortname', '?1'))
                ->orWhere($qb->expr()->eq('a.affilid', '?2'))
                ->orderBy('a.name', 'ASC')
                ->getQuery()->setParameter(1, '%' . $q . '%')
                ->setParameter(2, $q)
                ->getResult();

            $results = array_map(function (array $affiliation) {
                $displayname = $affiliation['name'] . " (" . $affiliation['affilid'] . ")";
                return [
                    'id' => $affiliation['affilid'],
                    'text' => $displayname,
                ];
            }, $affiliations);
        } elseif ($datatype === 'locations') {
            $locations = $qb->from(Team::class, 'a')
                ->select('DISTINCT a.location')
                ->where($qb->expr()->like('a.location', '?1'))
                ->orderBy('a.location', 'ASC')
                ->getQuery()->setParameter(1, '%' . $q . '%')
                ->getResult();

            $results = array_map(fn(array $location) => [
                'id' => $location['location'],
                'text' => $location['location']
            ], $locations);
        } elseif (!$this->isGranted('ROLE_JURY')) {
            throw new AccessDeniedHttpException('Permission denied');
        } elseif ($datatype === 'problems') {
            $problems = $qb->from(Problem::class, 'p')
                ->select('p.probid', 'p.name')
                ->where($qb->expr()->like('p.name', '?1'))
                ->orWhere($qb->expr()->eq('p.probid', '?2'))
                ->orderBy('p.name', 'ASC')
                ->getQuery()->setParameter(1, '%' . $q . '%')
                ->setParameter(2, $q)
                ->getResult();

            $results = array_map(function (array $problem) {
                $displayname = $problem['name'] . " (p" . $problem['probid'] . ")";
                return [
                    'id' => $problem['probid'],
                    'text' => $displayname,
                ];
            }, $problems);
        } elseif ($datatype === 'teams') {
            $teams = $qb->from(Team::class, 't')
                ->select('t.teamid', 't.display_name', 't.name', 'COALESCE(t.display_name, t.name) AS order')
                ->where($qb->expr()->like('t.name', '?1'))
                ->orWhere($qb->expr()->like('t.display_name', '?1'))
                ->orWhere($qb->expr()->eq('t.teamid', '?2'))
                ->orderBy('order', 'ASC')
                ->getQuery()->setParameter(1, '%' . $q . '%')
                ->setParameter(2, $q)
                ->getResult();

            $results = array_map(function (array $team) {
                $displayname = ($team['display_name'] ?? $team['name']) . " (t" . $team['teamid'] . ")";
                return [
                    'id' => $team['teamid'],
                    'text' => $displayname,
                ];
            }, $teams);
        } elseif ($datatype === 'languages') {
            $languages = $qb->from(Language::class, 'l')
                ->select('l.langid', 'l.name')
                ->where($qb->expr()->like('l.name', '?1'))
                ->orWhere($qb->expr()->eq('l.langid', '?2'))
                ->orderBy('l.name', 'ASC')
                ->getQuery()->setParameter(1, '%' . $q . '%')
                ->setParameter(2, $q)
                ->getResult();

            $results = array_map(function (array $language) {
                $displayname = $language['name'] . " (" . $language['langid'] . ")";
                return [
                    'id' => $language['langid'],
                    'text' => $displayname,
                ];
            }, $languages);
        } elseif ($datatype === 'contests') {
            $query = $qb->from(Contest::class, 'c')
                ->select('c.cid', 'c.name', 'c.shortname')
                ->where($qb->expr()->like('c.name', '?1'))
                ->orWhere($qb->expr()->like('c.shortname', '?1'))
                ->orWhere($qb->expr()->eq('c.cid', '?2'))
                ->orderBy('c.name', 'ASC');

            if ($request->query->get('public') !== null) {
                $query = $query->andWhere($qb->expr()->eq('c.public', '?3'));
            }
            $query = $query->getQuery()
                ->setParameter(1, '%' . $q . '%')
                ->setParameter(2, $q);
            if ($request->query->get('public') !== null) {
                $query = $query->setParameter(3, $request->query->get('public'));
            }
            $contests = $query->getResult();

            $results = array_map(function (array $contest) {
                $displayname = $contest['name'] . " (" . $contest['shortname'] . " - c" . $contest['cid'] . ")";
                return [
                    'id' => $contest['cid'],
                    'text' => $displayname,
                ];
            }, $contests);
        } else {
            throw new NotFoundHttpException("Unknown AJAX data type: " . $datatype);
        }

        return $this->json(['results' => $results]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/refresh-cache', name: 'jury_refresh_cache')]
    public function refreshCacheAction(Request $request, ScoreboardService $scoreboardService): Response
    {
        // Note: we use a XMLHttpRequest here as Symfony does not support
        // streaming Twig output.

        $contests = $this->dj->getCurrentContests();
        if ($cid = $request->request->get('cid')) {
            if (!isset($contests[$cid])) {
                throw new BadRequestHttpException(sprintf('Contest %s not found', $cid));
            }
            $contests = [$cid => $contests[$cid]];
        } elseif ($request->cookies->has('domjudge_cid') &&
                  ($contest = $this->dj->getCurrentContest())) {
            $contests = [$contest->getCid() => $contest];
        }

        if ($request->isXmlHttpRequest() && $request->isMethod('POST')) {
            $progressReporter = function (int $progress, string $log, ?string $message = null) {
                echo $this->dj->jsonEncode(['progress' => $progress, 'log' => htmlspecialchars($log), 'message' => $message]);
                ob_flush();
                flush();
            };
            return $this->streamResponse($this->requestStack, function () use ($contests, $progressReporter, $scoreboardService) {
                $timeStart = microtime(true);

                foreach ($contests as $contest) {
                    $scoreboardService->refreshCache($contest, $progressReporter);
                }

                $timeEnd = microtime(true);

                $progressReporter(100, '', sprintf(
                    'Scoreboard cache refresh completed in %.2lf seconds.',
                    $timeEnd - $timeStart
                ));
            });
        }

        return $this->render('jury/refresh_cache.html.twig', [
            'contests' => $contests,
            'contest' => count($contests) === 1 ? reset($contests) : null,
            'doRefresh' => $request->request->has('refresh'),
        ]);
    }

    #[IsGranted('ROLE_JURY')]
    #[Route(path: '/judging-verifier', name: 'jury_judging_verifier')]
    public function judgingVerifierAction(Request $request): Response
    {
        /** @var Submission[] $submissions */
        $submissions = [];
        if ($contest = $this->dj->getCurrentContest()) {
            $submissions = $this->em->createQueryBuilder()
                ->from(Submission::class, 's')
                ->join('s.judgings', 'j', Join::WITH, 'j.valid = 1')
                ->select('s', 'j')
                ->andWhere('s.contest = :contest')
                ->andWhere('j.result IS NOT NULL')
                ->setParameter('contest', $contest)
                ->getQuery()
                ->getResult();
        }

        $numChecked   = 0;
        $numUnchecked = 0;

        $unexpected = [];
        $multiple   = [];
        $verified   = [];
        $nomatch    = [];
        $earlier    = [];

        $verifier = 'auto-verifier';

        $verifyMultiple = (bool)$request->get('verify_multiple', false);

        foreach ($submissions as $submission) {
            // As we only load the needed judging, this will automatically be the first one
            /** @var Judging $judging */
            $judging         = $submission->getJudgings()->first();
            /** @var string[] $expectedResults */
            $expectedResults = $submission->getExpectedResults();
            $submissionId    = $submission->getSubmitid();
            $submissionFiles = $submission->getFiles();

            $result = mb_strtoupper($judging->getResult());
            $entry = ['files' => $submissionFiles, 'actual' => $result, 'expected' => $expectedResults, 'contestProblem' => $submission->getContestProblem()];
            if (!empty($expectedResults) && !$judging->getVerified()) {
                $numChecked++;
                if (!in_array($result, $expectedResults)) {
                    $unexpected[$submissionId] = $entry;
                } elseif (count($expectedResults) > 1) {
                    if ($verifyMultiple) {
                        // Judging result is as expected, set judging to verified.
                        $judging
                            ->setVerified(true)
                            ->setJuryMember($verifier);
                        $entry['verified'] = true;
                    } else {
                        $entry['verified'] = false;
                    }
                    $multiple[$submissionId] = $entry;
                } else {
                    // Judging result is as expected, set judging to verified.
                    $judging
                        ->setVerified(true)
                        ->setJuryMember($verifier);
                    $entry['verified'] = true;
                    $verified[$submissionId] = $entry;
                }
            } else {
                $numUnchecked++;

                if (empty($expectedResults)) {
                    $entry['verified'] = false;
                    $nomatch[$submissionId] = $entry;
                } else {
                    $entry['verified'] = true;
                    $earlier[$submissionId] = $entry;
                }
            }
        }

        $this->em->flush();

        return $this->render('jury/check_judgings.html.twig', [
            'numChecked' => $numChecked,
            'numUnchecked' => $numUnchecked,
            'unexpected' => $unexpected,
            'multiple' => $multiple,
            'verified' => $verified,
            'nomatch' => $nomatch,
            'earlier' => $earlier,
            'verifyMultiple' => $verifyMultiple,
        ]);
    }

    #[Route(path: '/change-contest/{contestId<-?\d+>}', name: 'jury_change_contest')]
    public function changeContestAction(Request $request, RouterInterface $router, int $contestId): Response
    {
        if ($this->isLocalReferer($router, $request)) {
            $response = new RedirectResponse($request->headers->get('referer'));
        } else {
            $response = $this->redirectToRoute('jury_index');
        }
        return $this->dj->setCookie('domjudge_cid', (string)$contestId, 0, null, '', false, false,
                                                 $response);
    }

    #[Route(path: "/adminer", name: "jury_adminer")]
    #[IsGranted("ROLE_ADMIN")]
    public function adminer(
        #[Autowire('%domjudge.etcdir%')] string $etcDir,
        #[Autowire('%kernel.project_dir%')] string $projectDir,
        ConfigurationService $config
    ): Response {
        if (!$config->get('adminer_enabled')) {
            throw new NotFoundHttpException();
        }

        // The adminer_object method needs this variable to know where to find the credentials
        $GLOBALS['etcDir'] = $etcDir;

        // Use output buffering since the streamed response doesn't work because Adminer needs the session
        ob_start();
        include_once $projectDir . '/resources/adminer.php';
        $resp = ob_get_clean();

        return new Response($resp);
    }
}
