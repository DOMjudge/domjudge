<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use DOMJudgeBundle\Utils\Utils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury")
 * @Security("has_role('ROLE_JURY')")
 */
class ContestController extends Controller
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var DOMJudgeService
     */
    protected $DOMJudgeService;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * TeamCategoryController constructor.
     * @param EntityManagerInterface $entityManager
     * @param DOMJudgeService        $DOMJudgeService
     * @param EventLogService        $eventLogService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        EventLogService $eventLogService
    ) {
        $this->entityManager   = $entityManager;
        $this->DOMJudgeService = $DOMJudgeService;
        $this->eventLogService = $eventLogService;
    }

    /**
     * @Route("/contests/", name="jury_contests")
     */
    public function indexAction(Request $request, Packages $assetPackage, KernelInterface $kernel)
    {
        $em = $this->entityManager;

        if ($doNow = (array)$request->request->get('donow')) {
            $times         = ['activate', 'start', 'freeze', 'end', 'unfreeze', 'finalize', 'deactivate'];
            $start_actions = ['delay_start', 'resume_start'];
            $actions       = array_merge($times, $start_actions);

            if (!$this->isGranted('ROLE_ADMIN')) {
                throw new AccessDeniedHttpException();
            }
            /** @var Contest $contest */
            $contest = $em->getRepository(Contest::class)->find($request->request->get('contest'));
            if (!$contest) {
                throw new NotFoundHttpException('contest not found');
            }

            $time = key($doNow);
            if (!in_array($time, $actions, true)) {
                throw new BadRequestHttpException(sprintf("Unknown value '%s' for timetype", $time));
            }

            if ($time === 'finalize') {
                return $this->redirectToRoute('legacy.jury_finalize', ['id' => $contest->getCid()]);
            }

            $now       = (int)floor(Utils::now());
            $nowstring = strftime('%Y-%m-%d %H:%M:%S ', $now) . date_default_timezone_get();
            $this->DOMJudgeService->auditlog('contest', $contest->getCid(), $time . ' now', $nowstring);

            // Special case delay/resume start (only sets/unsets starttime_undefined).
            $maxSeconds = Contest::STARTTIME_UPDATE_MIN_SECONDS_BEFORE;
            if (in_array($time, $start_actions, true)) {
                $enabled = $time === 'delay_start' ? 0 : 1;
                if (Utils::difftime((float)$contest->getStarttime(false), $now) <= $maxSeconds) {
                    throw new BadRequestHttpException(sprintf("Cannot %s less than %d seconds before contest start.",
                                                              $time, $maxSeconds));
                }
                $contest->setStarttimeEnabled($enabled);
                $em->flush();
                $this->eventLogService->log('contest', $contest->getCid(), EventLogService::ACTION_UPDATE,
                                            $contest->getCid());
                return $this->redirectToRoute('jury_contests', ['edited' => 1]);
            }

            // starttime is special because other, relative times depend on it.
            if ($time == 'start') {
                if ($contest->getStarttimeEnabled() && Utils::difftime((float)$contest->getStarttime(false),
                                                                       $now) <= $maxSeconds) {
                    throw new BadRequestHttpException(sprintf("Cannot update starttime less than %d seconds before contest start.",
                                                              $maxSeconds));
                }
                $contest
                    ->setStarttime($now)
                    ->setStarttimeString($nowstring)
                    ->setStarttimeEnabled(true);
                $em->flush();

                $this->eventLogService->log('contest', $contest->getCid(), EventLogService::ACTION_UPDATE,
                                            $contest->getCid());
                return $this->redirectToRoute('jury_contests', ['edited' => 1]);
            } else {
                $method = sprintf('set%stimeString', $time);
                $contest->{$method}($nowstring);
                $em->flush();
                $this->eventLogService->log('contest', $contest->getCid(), EventLogService::ACTION_UPDATE,
                                            $contest->getCid());
                return $this->redirectToRoute('jury_contests');
            }
        }

        $contests = $em->createQueryBuilder()
            ->select('c', 'COUNT(t.teamid) AS num_teams')
            ->from('DOMJudgeBundle:Contest', 'c')
            ->leftJoin('c.teams', 't')
            ->orderBy('c.starttime', 'DESC')
            ->groupBy('c.cid')
            ->getQuery()->getResult();

        $table_fields = [
            'cid' => ['title' => 'CID', 'sort' => true],
            'shortname' => ['title' => 'shortname', 'sort' => true],
            'activatetime' => ['title' => 'activate', 'sort' => true],
            'starttime' => ['title' => 'start', 'sort' => true, 'default_sort' => true, 'default_sort_order' => 'desc'],
            'freezetime' => ['title' => 'freeze', 'sort' => true],
            'endtime' => ['title' => 'end', 'sort' => true],
            'unfreezetime' => ['title' => 'unfreeze', 'sort' => true],
            'finalizetime' => ['title' => 'finalize', 'sort' => true],
            'deactivatetime' => ['title' => 'deactivate', 'sort' => true],
        ];

        $currentContests = $this->DOMJudgeService->getCurrentContests();

        $timeFormat = (string)$this->DOMJudgeService->dbconfig_get('time_format', '%H:%M');

        // TODO: use directories from domserver-static here when we have access to it
        $etcDir = realpath(sprintf('%s/../../etc', $kernel->getRootDir()));
        require_once $etcDir . '/domserver-config.php';

        if (ALLOW_REMOVED_INTERVALS) {
            $table_fields['num_removed_intervals'] = ['title' => '# removed<br/>intervals', 'sort' => true];
            $removedIntervals                      = $em->createQueryBuilder()
                ->from('DOMJudgeBundle:RemovedInterval', 'i', 'i.cid')
                ->select('COUNT(i.intervalid) AS num_removed_intervals', 'i.cid')
                ->groupBy('i.cid')
                ->getQuery()
                ->getResult();
        } else {
            $removedIntervals = [];
        }

        $problems = $em->createQueryBuilder()
            ->from('DOMJudgeBundle:ContestProblem', 'cp', 'cp.cid')
            ->select('COUNT(cp.probid) AS num_problems', 'cp.cid')
            ->groupBy('cp.cid')
            ->getQuery()
            ->getResult();

        $table_fields = array_merge($table_fields, [
            'process_balloons' => ['title' => 'process<br/>balloons?', 'sort' => true],
            'public' => ['title' => 'public?', 'sort' => true],
            'num_teams' => ['title' => '# teams', 'sort' => true],
            'num_problems' => ['title' => '# problems', 'sort' => true],
            'name' => ['title' => 'name', 'sort' => true],
        ]);

        // Insert external ID field when configured to use it
        if ($externalIdField = $this->eventLogService->externalIdFieldForEntity(Contest::class)) {
            $table_fields = array_slice($table_fields, 0, 1, true) +
                [$externalIdField => ['title' => 'external<br/>ID', 'sort' => true]] +
                array_slice($table_fields, 1, null, true);
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $contests_table   = [];
        foreach ($contests as $contestData) {
            /** @var Contest $contest */
            $contest        = $contestData[0];
            $contestdata    = [];
            $contestactions = [];
            // Get whatever fields we can from the contest object itself
            foreach ($table_fields as $k => $v) {
                if ($propertyAccessor->isReadable($contest, $k)) {
                    $contestdata[$k] = ['value' => $propertyAccessor->getValue($contest, $k)];
                }
            }

            if ($this->isGranted('ROLE_ADMIN')) {
                $contestactions[] = [
                    'icon' => 'edit',
                    'title' => 'edit this contest',
                    'link' => $this->generateUrl('legacy.jury_contest', [
                        'cmd' => 'edit',
                        'id' => $contest->getCid(),
                        'referrer' => 'contests'
                    ])
                ];
                $contestactions[] = [
                    'icon' => 'trash-alt',
                    'title' => 'delete this contest',
                    'link' => $this->generateUrl('legacy.jury_delete', [
                        'table' => 'contest',
                        'cid' => $contest->getCid(),
                        'referrer' => 'contests',
                        'desc' => $contest->getName(),
                    ])
                ];
            }

            $contestdata['process_balloons'] = ['value' => $contest->getProcessBalloons() ? 'yes' : 'no'];
            $contestdata['public']           = ['value' => $contest->getPublic() ? 'yes' : 'no'];
            $contestdata['num_teams']        = ['value' => $contestData['num_teams']];
            if ($contest->getPublic()) {
                $contestdata['num_teams'] = ['value' => '<i>all</i>'];
            }

            if (ALLOW_REMOVED_INTERVALS) {
                $contestdata['num_removed_intervals'] = ['value' => $removedIntervals[$contest->getCid()]['num_removed_intervals'] ?? 0];
            }
            $contestdata['num_problems'] = ['value' => $problems[$contest->getCid()]['num_problems'] ?? 0];

            $timeFields = [
                'activate',
                'start',
                'freeze',
                'end',
                'unfreeze',
                'finalize',
                'deactivate',
            ];
            foreach ($timeFields as $timeField) {
                $time = $contestdata[$timeField . 'time']['value'];
                if (!$contest->getStarttimeEnabled() && $timeField != 'activate') {
                    $time      = null;
                    $timeTitle = null;
                }
                if ($time === null) {
                    $timeValue = '-';
                    $timeTitle = '-';
                } else {
                    $timeValue = Utils::printtime($time, $timeFormat);
                    $timeTitle = Utils::printtime($time, '%Y-%m-%d %H:%M:%S (%Z)');
                }
                $contestdata[$timeField . 'time']['value'] = $timeValue;
                $contestdata[$timeField . 'time']['title'] = $timeTitle;
            }

            $styles = [];
            if (!$contest->getEnabled()) {
                $styles[] = 'disabled';
            }
            if (in_array($contest->getCid(), array_keys($currentContests))) {
                $styles[] = 'highlight';
            }
            $contests_table[] = [
                'data' => $contestdata,
                'actions' => $contestactions,
                'link' => $this->generateUrl('legacy.jury_contest', ['id' => $contest->getCid()]),
                'cssclass' => implode(' ', $styles),
            ];
        }

        /** @var Contest $upcomingContest */
        $upcomingContest = $em->createQueryBuilder()
            ->from('DOMJudgeBundle:Contest', 'c')
            ->select('c')
            ->andWhere('c.activatetime > :now')
            ->andWhere('c.enabled = 1')
            ->setParameter(':now', Utils::now())
            ->orderBy('c.activatetime')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $this->render('@DOMJudge/jury/contests.html.twig', [
            'current_contests' => $this->DOMJudgeService->getCurrentContests(),
            'upcoming_contest' => $upcomingContest,
            'contests_table' => $contests_table,
            'table_fields' => $table_fields,
            'num_actions' => $this->isGranted('ROLE_ADMIN') ? 2 : 0,
            'edited' => $request->query->getBoolean('edited'),
        ]);
    }
}
