<?php declare(strict_types=1);

namespace DOMJudgeBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\Judging;
use DOMJudgeBundle\Entity\Problem;
use DOMJudgeBundle\Entity\Rejudging;
use DOMJudgeBundle\Entity\Submission;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Entity\User;
use DOMJudgeBundle\Utils\Utils;

class RejudgingService
{
    const ACTION_APPLY = 'apply';
    const ACTION_CANCEL = 'cancel';

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var DOMJudgeService
     */
    protected $DOMJudgeService;

    /**
     * @var ScoreboardService
     */
    protected $scoreboardService;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * @var BalloonService
     */
    protected $balloonService;

    /**
     * RejudgingService constructor.
     * @param EntityManagerInterface $entityManager
     * @param DOMJudgeService        $DOMJudgeService
     * @param ScoreboardService      $scoreboardService
     * @param EventLogService        $eventLogService
     * @param BalloonService         $balloonService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        ScoreboardService $scoreboardService,
        EventLogService $eventLogService,
        BalloonService $balloonService
    ) {
        $this->entityManager     = $entityManager;
        $this->DOMJudgeService   = $DOMJudgeService;
        $this->scoreboardService = $scoreboardService;
        $this->eventLogService   = $eventLogService;
        $this->balloonService    = $balloonService;
    }

    /**
     * Finish the given rejudging
     * @param Rejudging     $rejudging        The rejudging to finish
     * @param string        $action           One of the self::ACTION_* constants
     * @param callable|null $progressReporter If given, use this callable to report progress
     * @return bool True iff the rejudging was finished successfully
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function finishRejudging(Rejudging $rejudging, string $action, callable $progressReporter = null)
    {
        // This might take a while
        ini_set('max_execution_time', '300');
        
        if ($rejudging->getEndtime()) {
            $error = sprintf('Rejudging already %s.', $rejudging->getValid() ? 'applied' : 'canceled');
            if ($progressReporter) {
                $progressReporter($error, true);
                return false;
            } else {
                throw new \BadMethodCallException($error);
            }
        }

        $rejudgingId = $rejudging->getRejudgingid();

        $todo = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Submission', 's')
            ->select('COUNT(s)')
            ->andWhere('s.rejudging = :rejudging')
            ->setParameter(':rejudging', $rejudging)
            ->getQuery()
            ->getSingleScalarResult();

        $done = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Judging', 'j')
            ->select('COUNT(j)')
            ->andWhere('j.rejudging = :rejudging')
            ->andWhere('j.endtime IS NOT NULL')
            ->setParameter(':rejudging', $rejudging)
            ->getQuery()
            ->getSingleScalarResult();

        $todo -= $done;

        if ($action == self::ACTION_APPLY && $todo > 0) {
            $error = sprintf('%d unfinished judgings left, cannot apply rejudging.', $todo);
            if ($progressReporter) {
                $progressReporter($error, true);
                return false;
            } else {
                throw new \BadMethodCallException($error);
            }
        }

        // Get all submissions that we should consider
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Submission', 's')
            ->join('s.judgings', 'j')
            ->select('s.submitid, s.cid, s.teamid, s.probid, j.judgingid, j.result')
            ->andWhere('s.rejudging = :rejudging');
        if ($action === self::ACTION_APPLY) {
            $queryBuilder->andWhere('j.rejudging = :rejudging');
        }
        /** @var array $submissions */
        $submissions = $queryBuilder
            ->setParameter(':rejudging', $rejudging)
            ->groupBy('s.submitid')
            ->getQuery()
            ->getResult();

        $this->DOMJudgeService->auditlog('rejudging', $rejudgingId, $action . 'ing rejudge', '(start)');

        // This loop uses direct queries instead of Doctrine classes to speed it up drastically

        foreach ($submissions as $submission) {
            if ($progressReporter) {
                $progressReporter(sprintf('s%s, ', $submission['submitid']));
            }

            if ($action === self::ACTION_APPLY) {
                $this->entityManager->transactional(function () use ($submission, $rejudgingId) {
                    // First invalidate old judging, maybe different from prevjudgingid!
                    $this->entityManager->getConnection()->executeQuery(
                        'UPDATE judging SET valid=0 WHERE submitid = :submitid',
                        [':submitid' => $submission['submitid']]
                    );

                    // Then set judging to valid
                    $this->entityManager->getConnection()->executeQuery(
                        'UPDATE judging SET valid=1 WHERE submitid = :submitid AND rejudgingid = :rejudgingid',
                        [':submitid' => $submission['submitid'], ':rejudgingid' => $rejudgingId]
                    );

                    // Remove relation from submission to rejudge
                    $this->entityManager->getConnection()->executeQuery(
                        'UPDATE submission SET rejudgingid=NULL WHERE submitid = :submitid',
                        [':submitid' => $submission['submitid']]
                    );

                    // Last update cache
                    $contest = $this->entityManager->getRepository(Contest::class)->find($submission['cid']);
                    $team    = $this->entityManager->getRepository(Team::class)->find($submission['teamid']);
                    $problem = $this->entityManager->getRepository(Problem::class)->find($submission['probid']);
                    $this->scoreboardService->calculateScoreRow($contest, $team, $problem);

                    // Update event log
                    $this->eventLogService->log('judging', $submission['judgingid'], EventLogService::ACTION_CREATE,
                                                $submission['cid']);

                    $runData = $this->entityManager->createQueryBuilder()
                        ->from('DOMJudgeBundle:JudgingRun', 'r')
                        ->select('r.runid')
                        ->andWhere('r.judgingid = :judgingid')
                        ->setParameter(':judgingid', $submission['judgingid'])
                        ->getQuery()
                        ->getResult();
                    $runIds  = array_map(function (array $data) {
                        return $data['runid'];
                    }, $runData);
                    if (!empty($runIds)) {
                        $this->eventLogService->log('judging_run', $runIds, 'create', $submission['cid']);
                    }

                    // Update ballons
                    $contest    = $this->entityManager->getRepository(Contest::class)->find($submission['cid']);
                    $submission = $this->entityManager->getRepository(Submission::class)->find($submission['submitid']);
                    $this->balloonService->updateBalloons($contest, $submission);
                });
            } else {
                // Restore old judgehost association
                /** @var Judging $validJudging */
                $validJudging = $this->entityManager->createQueryBuilder()
                    ->from('DOMJudgeBundle:Judging', 'j')
                    ->join('j.judgehost', 'jh')
                    ->select('j', 'jh')
                    ->andWhere('j.submitid = :submitid')
                    ->andWhere('j.valid = 1')
                    ->setParameter(':submitid', $submission['submitid'])
                    ->getQuery()
                    ->getOneOrNullResult();

                $params = [
                    ':judgehost' => $validJudging->getJudgehost()->getHostname(),
                    ':rejudgingid' => $rejudgingId,
                    ':submitid' => $submission['submitid'],
                ];
                $this->entityManager->getConnection()->executeQuery(
                    'UPDATE submission
                            SET rejudgingid = NULL,
                                judgehost = :judgehost
                            WHERE rejudgingid = :rejudgingid
                            AND submitid = :submitid', $params);
            }
        }

        // Update the rejudging itself
        /** @var Rejudging $rejudging */
        $rejudging = $this->entityManager->getRepository(Rejudging::class)->find($rejudgingId);
        $user      = $this->entityManager->getRepository(User::class)->find($this->DOMJudgeService->getUser()->getUserid());
        $rejudging
            ->setEndtime(Utils::now())
            ->setFinishUser($user)
            ->setValid($action === self::ACTION_APPLY);
        $this->entityManager->flush();

        $this->DOMJudgeService->auditlog('rejudging', $rejudgingId, $action . 'ing rejudge', '(end)');

        return true;
    }
}
