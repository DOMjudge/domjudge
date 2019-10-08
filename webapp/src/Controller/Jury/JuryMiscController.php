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
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * GeneralInfoController constructor.
     * @param EntityManagerInterface $entityManager
     * @param DOMJudgeService        $dj
     */
    public function __construct(EntityManagerInterface $entityManager, DOMJudgeService $dj)
    {
        $this->em = $entityManager;
        $this->dj = $dj;
    }

    /**
     * @Route("", name="jury_index")
     * @IsGranted({"ROLE_JURY", "ROLE_BALLOON"})
     */
    public function indexAction(Request $request)
    {
        $errors = [];
        return $this->render('jury/index.html.twig', ['errors' => $errors]);
    }

    /**
     * @Route("/updates", methods={"GET"}, name="jury_ajax_updates")
     * @IsGranted({"ROLE_JURY", "ROLE_BALLOON"})
     */
    public function updatesAction(Request $request)
    {
        return $this->json($this->dj->getUpdates());
    }

    /**
     * @Route("/ajax/{datatype}", methods={"GET"}, name="jury_ajax_data")
     * @param string $datatype
     * @IsGranted("ROLE_JURY")
     */
    public function ajaxDataAction(Request $request, string $datatype)
    {
        $q  = $request->query->get('q');
        $qb = $this->em->createQueryBuilder();

        if ($datatype === 'problems') {
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
                ->select('t.teamid', 't.name')
                ->where($qb->expr()->like('t.name', '?1'))
                ->orWhere($qb->expr()->eq('t.teamid', '?2'))
                ->orderBy('t.name', 'ASC')
                ->getQuery()->setParameter(1, '%' . $q . '%')
                ->setParameter(2, $q)
                ->getResult();

            $results = array_map(function (array $team) {
                $displayname = $team['name'] . " (t" . $team['teamid'] . ")";
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
        } elseif ($datatype === 'affiliations') {
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
        } else {
            throw new NotFoundHttpException("Unknown AJAX data type: " . $datatype);
        }

        return $this->json(['results' => $results]);
    }

    /**
     * @Route("/refresh-cache", name="jury_refresh_cache")
     * @IsGranted("ROLE_ADMIN")
     * @param Request           $request
     * @param ScoreboardService $scoreboardService
     * @return \Symfony\Component\HttpFoundation\Response|StreamedResponse
     */
    public function refreshCacheAction(Request $request, ScoreboardService $scoreboardService)
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
            $progressReporter = function (string $data) {
                echo $data;
                ob_flush();
                flush();
            };
            $response         = new StreamedResponse();
            $response->headers->set('X-Accel-Buffering', 'no');
            $response->setCallback(function () use ($contests, $progressReporter, $scoreboardService) {
                $timeStart = microtime(true);

                $this->dj->auditlog('scoreboard', null, 'refresh cache');

                foreach ($contests as $contest) {
                    $queryBuilder = $this->em->createQueryBuilder()
                        ->from(Team::class, 't')
                        ->select('t')
                        ->orderBy('t.teamid');
                    if (!$contest->isOpenToAllTeams()) {
                        $queryBuilder
                            ->leftJoin('t.contests', 'c')
                            ->join('t.category', 'cat')
                            ->leftJoin('cat.contests', 'cc')
                            ->andWhere('c.cid = :cid OR cc.cid = :cid')
                            ->setParameter(':cid', $contest->getCid());
                    }
                    /** @var Team[] $teams */
                    $teams = $queryBuilder->getQuery()->getResult();
                    /** @var Problem[] $problems */
                    $problems = $this->em->createQueryBuilder()
                        ->from(Problem::class, 'p')
                        ->join('p.contest_problems', 'cp')
                        ->select('p')
                        ->andWhere('cp.contest = :contest')
                        ->setParameter(':contest', $contest)
                        ->orderBy('cp.shortname')
                        ->getQuery()
                        ->getResult();

                    $message = sprintf('<p>Recalculating all values for the scoreboard ' .
                                       'cache for contest %d (%d teams, %d problems)...</p>',
                                       $contest->getCid(), count($teams), count($problems));
                    $progressReporter($message);
                    $progressReporter('<pre>');

                    if (count($teams) == 0) {
                        $progressReporter('No teams defined, doing nothing.</pre>');
                        return;
                    }
                    if (count($problems) == 0) {
                        $progressReporter('No problems defined, doing nothing.</pre>');
                        return;
                    }

                    // for each team, fetch the status of each problem
                    foreach ($teams as $team) {
                        $progressReporter(sprintf('Team %d:', $team->getTeamid()));

                        // for each problem fetch the result
                        foreach ($problems as $problem) {
                            $progressReporter(sprintf(' p%d', $problem->getProbid()));
                            $scoreboardService->calculateScoreRow($contest, $team, $problem, false);
                        }

                        $progressReporter(" rankcache\n");
                        $scoreboardService->updateRankCache($contest, $team);
                    }

                    $progressReporter('</pre>');

                    $progressReporter('<p>Deleting irrelevant data...</p>');

                    // Drop all teams and problems that do not exist in the contest
                    if (!empty($problems)) {
                        $problemIds = array_map(function (Problem $problem) {
                            return $problem->getProbid();
                        }, $problems);
                    } else {
                        // problemId -1 will never happen, but otherwise the array is empty and that is not supported
                        $problemIds = [-1];
                    }

                    if (!empty($teams)) {
                        $teamIds = array_map(function (Team $team) {
                            return $team->getTeamid();
                        }, $teams);
                    } else {
                        // teamId -1 will never happen, but otherwise the array is empty and that is not supported
                        $teamIds = [-1];
                    }

                    $params = [
                        ':cid' => $contest->getCid(),
                        ':problemIds' => $problemIds,
                    ];
                    $types  = [
                        ':problemIds' => Connection::PARAM_INT_ARRAY,
                        ':teamIds' => Connection::PARAM_INT_ARRAY,
                    ];
                    $this->em->getConnection()->executeQuery(
                        'DELETE FROM scorecache WHERE cid = :cid AND probid NOT IN (:problemIds)',
                        $params, $types);

                    $params = [
                        ':cid' => $contest->getCid(),
                        ':teamIds' => $teamIds,
                    ];
                    $this->em->getConnection()->executeQuery(
                        'DELETE FROM scorecache WHERE cid = :cid AND teamid NOT IN (:teamIds)',
                        $params, $types);
                    $this->em->getConnection()->executeQuery(
                        'DELETE FROM rankcache WHERE cid = :cid AND teamid NOT IN (:teamIds)',
                        $params, $types);
                }

                $timeEnd = microtime(true);

                $progressReporter(sprintf('<p>Scoreboard cache refresh completed in %.2lf seconds.</p>',
                                          $timeEnd - $timeStart));
            });
            return $response;
        }

        return $this->render('jury/refresh_cache.html.twig', [
            'contests' => $contests,
            'contest' => count($contests) === 1 ? reset($contests) : null,
            'doRefresh' => $request->request->has('refresh'),
        ]);
    }

    /**
     * @Route("/judging-verifier", name="jury_judging_verifier")
     * @IsGranted("ROLE_ADMIN")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response|StreamedResponse
     */
    public function judgingVerifierAction(Request $request)
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
                ->setParameter(':contests', $contests)
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
            $submissionLink  = $this->generateUrl('jury_submission', ['submitId' => $submission->getSubmitid()]);
            $submissionId    = sprintf('s%d', $submission->getSubmitid());

            if (!empty($expectedResults) && !$judging->getVerified()) {
                $numChecked++;
                $result = mb_strtoupper($judging->getResult());
                if (!in_array($result, $expectedResults)) {
                    $unexpected[] = sprintf(
                        "<a href='%s'>%s</a> has unexpected result '%s', should be one of: %s",
                        $submissionLink, $submissionId, $result, implode(', ', $expectedResults)
                    );
                } elseif (count($expectedResults) > 1) {
                    if ($verifyMultiple) {
                        // Judging result is as expected, set judging to verified
                        $judging
                            ->setVerified(true)
                            ->setJuryMember($verifier);
                        $multiple[] = sprintf(
                            "<a href='%s'>%s</a> verified as %s out of multiple possible outcomes (%s)",
                            $submissionLink, $submissionId, $result, implode(', ', $expectedResults)
                        );
                    } else {
                        $multiple[] = sprintf(
                            "<a href='%s'>%s</a> is judged as %s but has multiple possible outcomes (%s)",
                            $submissionLink, $submissionId, $result, implode(', ', $expectedResults)
                        );
                    }
                } else {
                    // Judging result is as expected, set judging to verified
                    $judging
                        ->setVerified(true)
                        ->setJuryMember($verifier);
                    $verified[] = sprintf(
                        "<a href='%s'>%s</a> verified as '%s'",
                        $submissionLink, $submissionId, $result
                    );
                }
            } else {
                $numUnchecked++;

                if (empty($expectedResults)) {
                    $nomatch[] = sprintf(
                        "expected results unknown in <a href='%s'>%s</a>, leaving submission unchecked",
                        $submissionLink, $submissionId
                    );
                } else {
                    $earlier[] = sprintf(
                        "<a href='%s'>%s</a> already verified earlier",
                        $submissionLink, $submissionId
                    );
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
     * @param Request         $request
     * @param RouterInterface $router
     * @param int             $contestId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function changeContestAction(Request $request, RouterInterface $router, int $contestId)
    {
        if ($this->isLocalReferrer($router, $request)) {
            $response = new RedirectResponse($request->headers->get('referer'));
        } else {
            $response = $this->redirectToRoute('jury_index');
        }
        return $this->dj->setCookie('domjudge_cid', (string)$contestId, 0, null, '', false, false,
                                                 $response);
    }
}
