<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Contest;
use App\Entity\Judging;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Service\DOMJudgeService;
use App\Service\ScoreboardService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class JuryMiscController
 *
 * @Route("/jury")
 *
 * @package App\Controller\Jury
 */
class JuryMiscController extends BaseController
{
    protected EntityManagerInterface $em;
    protected DOMJudgeService $dj;

    /**
     * GeneralInfoController constructor.
     */
    public function __construct(EntityManagerInterface $entityManager, DOMJudgeService $dj)
    {
        $this->em = $entityManager;
        $this->dj = $dj;
    }

    /**
     * @Route("", name="jury_index")
     * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_BALLOON') or is_granted('ROLE_CLARIFICATION_RW')")
     */
    public function indexAction(): Response
    {
        return $this->render('jury/index.html.twig');
    }

    /**
     * @Route("/updates", methods={"GET"}, name="jury_ajax_updates")
     * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_BALLOON')")
     */
    public function updatesAction(): JsonResponse
    {
        return $this->json($this->dj->getUpdates());
    }

    /**
     * @Route("/ajax/{datatype}", methods={"GET"}, name="jury_ajax_data")
     * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_BALLOON')")
     */
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
                ->select('DISTINCT a.room')
                ->where($qb->expr()->like('a.room', '?1'))
                ->orderBy('a.room', 'ASC')
                ->getQuery()->setParameter(1, '%' . $q . '%')
                ->getResult();

            $results = array_map(fn(array $location) => [
                'id' => $location['room'],
                'text' => $location['room']
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

    /**
     * @Route("/refresh-cache", name="jury_refresh_cache")
     * @IsGranted("ROLE_ADMIN")
     */
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
                echo $this->dj->jsonEncode(['progress' => $progress, 'log' => Utils::specialchars($log), 'message' => $message]);
                ob_flush();
                flush();
            };
            return $this->streamResponse(function () use ($contests, $progressReporter, $scoreboardService) {
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

    /**
     * @Route("/judging-verifier", name="jury_judging_verifier")
     * @IsGranted("ROLE_JURY")
     */
    public function judgingVerifierAction(Request $request): Response
    {
        /** @var Submission[] $submissions */
        $submissions = [];
        if ($contests = $this->dj->getCurrentContests()) {
            $submissions = $this->em->createQueryBuilder()
                ->from(Submission::class, 's')
                ->join('s.judgings', 'j', Join::WITH, 'j.valid = 1')
                ->select('s', 'j')
                ->andWhere('s.contest IN (:contests)')
                ->andWhere('j.result IS NOT NULL')
                ->setParameter('contests', $contests)
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
            $expectedResults = $submission->getExpectedResults();
            $submissionId    = $submission->getSubmitid();

            if (!empty($expectedResults) && !$judging->getVerified()) {
                $numChecked++;
                $result = mb_strtoupper($judging->getResult());
                if (!in_array($result, $expectedResults)) {
                    $submissionFiles = $submission->getFiles();
                    $unexpected[$submissionId] = ['files' => $submissionFiles, 'actual' => $result, 'expected' => $expectedResults];
                } elseif (count($expectedResults) > 1) {
                    if ($verifyMultiple) {
                        // Judging result is as expected, set judging to verified.
                        $judging
                            ->setVerified(true)
                            ->setJuryMember($verifier);
                        $multiple[$submissionId] = ['actual' => $result, 'expected' => $expectedResults, 'verified' => true];
                    } else {
                        $multiple[$submissionId] = ['actual' => $result, 'expected' => $expectedResults, 'verified' => false];
                    }
                } else {
                    // Judging result is as expected, set judging to verified.
                    $judging
                        ->setVerified(true)
                        ->setJuryMember($verifier);
                    $verified[$submissionId] = ['actual' => $result, 'expected' => $expectedResults, 'verified' => true];
                }
            } else {
                $numUnchecked++;

                if (empty($expectedResults)) {
                    $nomatch[$submissionId] = [];
                } else {
                    $earlier[$submissionId] = [];
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

    /**
     * @Route("/change-contest/{contestId<-?\d+>}", name="jury_change_contest")
     */
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
}
