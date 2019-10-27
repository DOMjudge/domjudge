<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Entity\Balloon;
use App\Entity\ScoreCache;
use App\Entity\TeamAffiliation;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury/balloons")
 * @IsGranted({"ROLE_JURY", "ROLE_BALLOON"})
 */
class BalloonController extends AbstractController
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
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * BalloonController constructor.
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param EventLogService        $eventLogService
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        EventLogService $eventLogService
    ) {
        $this->em              = $em;
        $this->dj              = $dj;
        $this->eventLogService = $eventLogService;
    }

    /**
     * @Route("", name="jury_balloons")
     */
    public function indexAction(Request $request, Packages $assetPackage, KernelInterface $kernel)
    {
        $timeFormat = (string)$this->dj->dbconfig_get('time_format', '%H:%M');
        $showPostFreeze = (bool)$this->dj->dbconfig_get('show_balloons_postfreeze', false);

        $em = $this->em;

        $contest = $this->dj->getCurrentContest();
        if(is_null($contest)) {
            return $this->render('jury/balloons.html.twig');
        }

        $contestIsFrozen = isset($contest->getState()['frozen']);
        if(!$showPostFreeze) {
            $freezetime = $contest->getFreezeTime();
        }

        // Build a list of teams and the problems they solved first
        $firstSolved = $em->getRepository(ScoreCache::class)->findBy(['is_first_to_solve'=>1]);
        $firstSolvers = [];
        foreach($firstSolved as $scoreCache) {
            $firstSolvers[$scoreCache->getTeam()->getTeamId()][] = $scoreCache->getProblem()->getProbid();
        }

        $query = $em->createQueryBuilder()
            ->select('b', 's.submittime', 'p.probid',
                't.teamid', 't.name AS teamname', 't.room',
                'c.name AS catname',
                's.cid', 'co.shortname',
                'cp.shortname AS probshortname', 'cp.color',
                'a.affilid AS affilid', 'a.shortname AS affilshort' )
            ->from(Balloon::class, 'b')
            ->leftJoin('b.submission', 's')
            ->leftJoin('s.problem', 'p')
            ->leftJoin('s.contest', 'co')
            ->leftJoin('p.contest_problems', 'cp', Join::WITH, 'co.cid = cp.contest AND p.probid = cp.problem')
            ->leftJoin('s.team', 't')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.affiliation', 'a')
            ->andWhere('co.cid = :cid')
            ->setParameter(':cid', $contest->getCid())
            ->orderBy('b.done', 'ASC')
            ->addOrderBy('s.submittime', 'DESC');

        $balloons = $query->getQuery()->getResult();
        // Loop once over the results to get totals and awards
        $TOTAL_BALLOONS = $AWARD_BALLOONS = [];
        foreach ($balloons as $balloonsData) {
            if ( $balloonsData['color'] === null ) {
                continue;
            }

            $TOTAL_BALLOONS[$balloonsData['teamid']][$balloonsData['probshortname']] = $balloonsData['color'];

            // Keep a list of balloons that were first to solve this problem;
            // can be multiple, one for each sortorder.
            if (in_array($balloonsData['probid'], $firstSolvers[$balloonsData['teamid']]??[], true) ) {
                $AWARD_BALLOONS['problem'][$balloonsData['probid']][] = $balloonsData[0]->getBalloonId();
            }
            // Keep overwriting this - in the end it'll
            // contain the id of the first balloon in this contest.
            $AWARD_BALLOONS['contest'] = $balloonsData[0]->getBalloonId();
        }

        // Loop again to construct table
        $balloons_table = [];
        foreach ($balloons as $balloonsData) {
            $color = $balloonsData['color'];

            if ( $color === null ) {
                continue;
            }
            $balloon = $balloonsData[0];

            $balloonId = $balloon->getBalloonId();

            $stime = $balloonsData['submittime'];

            if ( isset($freezetime) && $stime >= $freezetime) {
                continue;
            }

            $balloondata = [];
            $balloondata['balloonid'] = $balloonId;
            $balloondata['time'] = $stime;
            $balloondata['solved'] = Utils::balloonSym($color) . " " . $balloonsData['probshortname'];
            $balloondata['color'] = $color;
            $balloondata['problem'] = $balloonsData['probshortname'];
            $balloondata['team'] = "t" . $balloonsData['teamid'] . ": " . $balloonsData['teamname'];
            $balloondata['teamid'] = $balloonsData['teamid'];
            $balloondata['location'] = $balloonsData['room'];
            $balloondata['affiliation'] = $balloonsData['affilshort'];
            $balloondata['category'] = $balloonsData['catname'];

            ksort($TOTAL_BALLOONS[$balloonsData['teamid']]);
            $balloondata['total'] = $TOTAL_BALLOONS[$balloonsData['teamid']];

            $comments = [];
            if ($AWARD_BALLOONS['contest'] == $balloonId) {
                $comments[] = 'first in contest';
            } elseif (isset($AWARD_BALLOONS['problem'][$balloonsData['probid']])
                   && in_array($balloonId, $AWARD_BALLOONS['problem'][$balloonsData['probid']], true)) {
                $comments[] = 'first for problem';
            }

            $balloondata['awards'] = implode('; ', $comments);

            if ( $balloon->getDone() ) {
                $cssclass = 'disabled';
                $balloonactions = [[]];
                $balloondata['done'] = true;
            } else {
                $cssclass = null;
                $balloondata['done'] = false;
                $balloonactions = [[
                    'icon' => 'running',
                    'title' => 'mark balloon as done',
                    'link' => $this->generateUrl('jury_balloons_setdone', [
                        'balloonId' => $balloon->getBalloonId(),
                    ])]];
            }


            $balloons_table[] = [
                'data' => $balloondata,
                'actions' => $balloonactions,
                'cssclass' => $balloon->getDone() ? 'disabled' : null,
            ];
        }

        // Load preselected filters
        $filters              = $this->dj->jsonDecode((string)$this->dj->getCookie('domjudge_balloonsfilter') ?: '[]');
        $filteredAffiliations = [];
        if (isset($filters['affiliation-id'])) {
            /** @var TeamAffiliation[] $filteredAffiliations */
            $filteredAffiliations = $this->em->createQueryBuilder()
                ->from(TeamAffiliation::class, 'a')
                ->select('a')
                ->where('a.affilid IN (:affilIds)')
                ->setParameter(':affilIds', $filters['affiliation-id'])
                ->getQuery()
                ->getResult();
        }

        return $this->render('jury/balloons.html.twig', [
            'refresh' => [
                'after' => 60,
                'url' => $this->generateUrl('jury_balloons'),
                'ajax' => true
            ],
            'isfrozen' => $contestIsFrozen,
            'hasFilters' => !empty($filters),
            'filteredAffiliations' => $filteredAffiliations,
            'balloons' => $balloons_table
        ]);
    }

    /**
     * @Route("/{balloonId}/done", name="jury_balloons_setdone")
     */
    public function setDoneAction(Request $request, int $balloonId)
    {
        $em = $this->em;
        $balloon = $em->getRepository(Balloon::class)->find($balloonId);
        if (!$balloon) {
            throw new NotFoundHttpException('balloon not found');
        }
        $balloon->setDone(true);
        $em->flush();

        return $this->redirectToRoute("jury_balloons");
    }
}
