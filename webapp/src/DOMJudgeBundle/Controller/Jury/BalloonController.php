<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Balloon;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use DOMJudgeBundle\Utils\Utils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/jury/balloons")
 * @Security("has_role('ROLE_JURY') or has_role('ROLE_BALLOON')")
 */
class BalloonController extends Controller
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
        $query = $em->createQueryBuilder()
            ->select('b', 's.submittime', 'p.probid',
                't.teamid', 't.name AS teamname', 't.room', 'c.name AS catname',
                's.cid', 'co.shortname', 'cp.shortname AS probshortname', 'cp.color')
            ->from('DOMJudgeBundle:Balloon', 'b')
            ->leftJoin('b.submission', 's')
            ->leftJoin('s.problem', 'p')
            ->leftJoin('s.contest', 'co')
            ->leftJoin('p.contest_problems', 'cp', 'co.cid = cp.cid AND p.probid = cp.probid')
            ->leftJoin('s.team', 't')
            ->leftJoin('t.category', 'c')
            ->orderBy('b.balloonid', 'DESC');

        $contests = $this->dj->getCurrentContests();
        $frozen_contests = [];
        $freezetimes = [];
        foreach($contests as $cid => $contest) {
            if($contest->getState()['frozen']) {
                $frozen_contests[$cid] = $contest->getShortName();
            }
            if ( !$showPostFreeze ) {
                $freezetimes[$cid] = $contest->getFreezeTime();
            }
        }

        $balloons = $query->getQuery()->getResult();
        // Loop once over the results to get totals and awards
        $TOTAL_BALLOONS = $AWARD_BALLOONS = [];
        foreach ($balloons as $balloonsData) {
            if ( $balloonsData['color'] === null ) {
                continue;
            }

            $TOTAL_BALLOONS[$balloonsData['teamid']][$balloonsData['cid']."-".$balloonsData['probshortname']] =
                Utils::balloonSym($balloonsData['color']);

            // keep overwriting these variables - in the end they'll
            // contain the ids of the first balloon in each type
            $AWARD_BALLOONS['contest'][$balloonsData['cid']] = $AWARD_BALLOONS['problem'][$balloonsData['probid']] = $AWARD_BALLOONS['team'][$balloonsData['teamid']] = $balloonsData[0]->getBalloonId();
        }

        $table_fields = [
            'status' => ['title' => '', 'sort' => true],
            'balloonid' => ['title' => 'ID', 'sort' => true],
            'time' => ['title' => 'time', 'sort' => true],
            'solved' => ['title' => 'solved', 'sort' => true],
            'team' => ['title' => 'team', 'sort' => true],
            'location' => ['title' => 'loc.', 'sort' => true],
            'category' => ['title' => 'category', 'sort' => true],
            'total' => ['title' => 'total', 'sort' => false],
            'awards' => ['title' => 'comments', 'sort' => true],
        ];

        // Loop again to construct table
        $balloons_table = [];
        foreach ($balloons as $balloonsData) {
            $balloon = $balloonsData[0];
            $balloondata = [];

            $balloonId = $balloon->getBalloonId();

            $stime = $balloonsData['submittime'];
            $contest = $balloonsData['cid'];
            $color = $balloonsData['color'];

            if ( $color === null ) {
                continue;
            }

            if ( isset($freezetimes[$contest]) && $stime >= $freezetimes[$contest]) {
                continue;
            }

            $balloondata['balloonid']['value'] = $balloonId;
            $balloondata['time']['value'] = Utils::printtime($stime, $timeFormat);
            $balloondata['solved']['value'] = Utils::balloonSym($color) . " " . $balloonsData['probshortname'];
            $balloondata['team']['value'] = "t" . $balloonsData['teamid'] . ": " . $balloonsData['teamname'];
            $balloondata['location']['value'] = $balloonsData['room'];
            $balloondata['category']['value'] = $balloonsData['catname'];

            ksort($TOTAL_BALLOONS[$balloonsData['teamid']]);
            $balloondata['total']['value'] = implode(' ', $TOTAL_BALLOONS[$balloonsData['teamid']]);

            $comments = [];
            if ($AWARD_BALLOONS['contest'][$contest] == $balloonId) {
                $comments[] = 'first in contest';
            } else {
                if ($AWARD_BALLOONS['team'][$balloonsData['teamid']] == $balloonId) {
                    $comments[] = 'first for team';
                }
                if ($AWARD_BALLOONS['problem'][$balloonsData['probid']] == $balloonId) {
                    $comments[] = 'first for problem';
                }
            }

            $balloondata['awards']['value'] = implode('; ', $comments);

            if ( $balloon->getDone() ) {
                $cssclass = 'disabled';
                $balloonactions = [[]];
                $balloondata['status']['value'] = '<i class="far fa-check-circle"></i>';
                $balloondata['status']['sortvalue'] = '1';
            } else {
                $cssclass = null;
                $balloondata['status']['value'] = '<i class="far fa-hourglass"></i>';
                $balloondata['status']['sortvalue'] = '0';
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

        return $this->render('@DOMJudge/jury/balloons.html.twig', [
            'refresh' => ['after' => 60, 'url' => $this->generateUrl('jury_balloons')],
            'frozen_contests' => $frozen_contests,
            'balloons' => $balloons_table,
            'table_fields' => $table_fields,
            'num_actions' => 1,
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
