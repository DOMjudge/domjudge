<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use DOMJudgeBundle\Controller\BaseController;
use DOMJudgeBundle\Entity\Judging;
use DOMJudgeBundle\Entity\Problem;
use DOMJudgeBundle\Entity\Submission;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\ScoreboardService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
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
 * @package DOMJudgeBundle\Controller\Jury
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
     * @Security("has_role('ROLE_JURY') or has_role('ROLE_BALLOON')")
     */
    public function indexAction(Request $request)
    {
        $errors = [];
        return $this->render('DOMJudgeBundle:jury:index.html.twig', ['errors' => $errors]);
    }

    /**
     * @Route("/updates", methods={"GET"}, name="jury_ajax_updates")
     * @Security("has_role('ROLE_JURY') or has_role('ROLE_BALLOON')")
     */
    public function updatesAction(Request $request)
    {
        return $this->json($this->dj->getUpdates());
    }

    /**
     * @Route("/ajax/{datatype}", methods={"GET"}, name="jury_ajax_data")
     * @param string $datatype
     * @Security("has_role('ROLE_JURY')")
     */
    public function ajaxDataAction(Request $request, string $datatype)
    {
        $q  = $request->query->get('q');
        $qb = $this->em->createQueryBuilder();

        if ($datatype === 'problems') {
            $problems = $qb->from('DOMJudgeBundle:Problem', 'p')
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
            $teams = $qb->from('DOMJudgeBundle:Team', 't')
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
            $languages = $qb->from('DOMJudgeBundle:Language', 'l')
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
            $query = $qb->from('DOMJudgeBundle:Contest', 'c')
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
     * @Security("has_role('ROLE_ADMIN')")
     * @param Request           $request
     * @param ScoreboardService $scoreboardService
     * @return \Symfony\Component\HttpFoundation\Response|StreamedResponse
     */
    public function refreshCacheAction(Request $request, ScoreboardService $scoreboardService)
    {
        // Note: we use a XMLHttpRequest here as Symfony does not support streaming Twig outpit

        $contests = $this->dj->getCurrentContests();
        if ($cid = $request->request->get('cid')) {
            if (!isset($contests[$cid])) {
                throw new BadRequestHttpException(sprintf('Contest %s not found', $cid));
            }
            $contests = [$cid => $contests[$cid]];
        } elseif ($request->cookies->has('domjudge_cid') && ($contest = $this->dj->getCurrentContest())) {
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
                        ->from('DOMJudgeBundle:Team', 't')
                        ->select('t')
                        ->orderBy('t.teamid');
                    if (!$contest->getPublic()) {
                        $queryBuilder
                            ->join('t.contests', 'c')
                            ->andWhere('c.cid = :cid')
                            ->setParameter(':cid', $contest->getCid());
                    }
                    /** @var Team[] $teams */
                    $teams = $queryBuilder->getQuery()->getResult();
                    /** @var Problem[] $problems */
                    $problems = $this->em->createQueryBuilder()
                        ->from('DOMJudgeBundle:Problem', 'p')
                        ->join('p.contest_problems', 'cp')
                        ->select('p')
                        ->andWhere('cp.contest = :contest')
                        ->setParameter(':contest', $contest)
                        ->orderBy('cp.shortname')
                        ->getQuery()
                        ->getResult();

                    $message = sprintf('<p>Recalculating all values for the scoreboard cache for contest %d (%d teams, %d problems)...</p>',
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

        return $this->render('@DOMJudge/jury/refresh_cache.html.twig', [
            'contests' => $contests,
            'contest' => count($contests) === 1 ? reset($contests) : null,
            'doRefresh' => $request->request->has('refresh'),
        ]);
    }

    /**
     * @Route("/judging-verifier", name="jury_judging_verifier")
     * @Security("has_role('ROLE_ADMIN')")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response|StreamedResponse
     */
    public function judgingVerifierAction(Request $request)
    {
        /** @var Submission[] $submissions */
        $submissions = [];
        if ($contests = $this->dj->getCurrentContests()) {
            $submissions = $this->em->createQueryBuilder()
                ->from('DOMJudgeBundle:Submission', 's')
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

        return $this->render('@DOMJudge/jury/check_judgings.html.twig', [
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
     * @Route("/change-contest/{contestId}", name="jury_change_contest")
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
