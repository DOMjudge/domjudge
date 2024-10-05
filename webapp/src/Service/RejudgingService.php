<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Contest;
use App\Entity\Judging;
use App\Entity\JudgingRun;
use App\Entity\Problem;
use App\Entity\Rejudging;
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\User;
use App\Utils\Utils;
use BadMethodCallException;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Ramsey\Uuid\Uuid;

class RejudgingService
{
    final public const ACTION_APPLY = 'apply';
    final public const ACTION_CANCEL = 'cancel';

    // When we are applying a rejudging we will update the scoreboard at the end. We will use the
    // last 5% of the progress bar to do this.
    final protected const APPLY_PROGRESS_WITH_SCOREBOARD_UPDATE = 95;

    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly DOMJudgeService $dj,
        protected readonly ScoreboardService $scoreboardService,
        protected readonly EventLogService $eventLogService,
        protected readonly BalloonService $balloonService
    ) {}

    /**
     * Create a new rejudging.
     *
     * @param string          $reason           Reason for this rejudging
     * @param Judging[]       $judgings         List of judgings to rejudging
     * @param bool            $autoApply        Whether the judgings should be automatically applied.
     * @param Judging[]      &$skipped          Returns list of judgings not included.
     * @param callable|null   $progressReporter If set, report progress using this callback. Will get two values:
     *                                          - the progress as an integer
     *                                          - the log to display
     *
     */
    public function createRejudging(
        string $reason,
        int $priority,
        array $judgings,
        bool $autoApply,
        ?int $repeat,
        ?int $overshoot,
        ?Rejudging $repeatedRejudging,
        array &$skipped,
        ?callable $progressReporter = null
    ): ?Rejudging {
        // This might take a while. Make sure we do not timeout.
        set_time_limit(0);

        $rejudging = new Rejudging();
        $rejudging
            ->setStartUser($this->dj->getUser())
            ->setStarttime(Utils::now())
            ->setReason($reason)
            ->setAutoApply($autoApply);
        $this->em->persist($rejudging);
        $this->em->flush();
        if (isset($repeat) && $repeat > 1) {
            if ($repeatedRejudging === null) {
                $repeatedRejudging = $rejudging;
            }
            $rejudging
                ->setRepeat($repeat)
                ->setRepeatedRejudging($repeatedRejudging);
            $this->em->flush();
        }

        $log = '';
        $singleJudging = (count($judgings) == 1);
        $index = 0;
        $first = true;
        foreach ($judgings as $judging) {
            $submission = $judging->getSubmission();
            $contestProblem = $submission->getContestProblem();
            $language = $submission->getLanguage();

            $index++;
            if (
                // Record and skip submission/judging if it is already part of another judging or is not allowed
                // to be judged.
                $submission->getRejudging() !== null
                || !$contestProblem->getAllowJudge()
                || !$language->getAllowJudge()
            ) {
                $skipped[] = $judging;
                continue;
            }


            // $this->>em->wrapInTransaction flushes the entity manager, which is pretty slow.
            // So use the direct connection transaction API here.
            $this->em->getConnection()->beginTransaction();

            $this->em->getConnection()->executeStatement(
                'UPDATE submission SET rejudgingid = :rejudgingid WHERE submitid = :submitid AND rejudgingid IS NULL',
                [
                    'rejudgingid' => $rejudging->getRejudgingid(),
                    'submitid' => $judging->getSubmissionId(),
                ]
            );

            if ($singleJudging) {
                $teamid = $judging->getSubmission()->getTeamId();
                if ($teamid) {
                    $this->em->getConnection()->executeStatement(
                        'UPDATE team SET judging_last_started = null WHERE teamid = :teamid',
                        [ 'teamid' => $teamid ]
                    );
                }
            }

            // Give back judging, create a new one.
            // Use a direct query to speed things up.
            $this->em->getConnection()->executeStatement(
                'INSERT INTO judging (cid, valid, submitid, prevjudgingid, rejudgingid, uuid)
                    VALUES (:cid, 0, :submitid, :prevjudgingid, :rejudgingid, :uuid)',
                [
                    'cid' => $judging->getContest()->getCid(),
                    'submitid' => $judging->getSubmissionId(),
                    'prevjudgingid' => $judging->getJudgingId(),
                    'rejudgingid' => $rejudging->getRejudgingid(),
                    'uuid' => Uuid::uuid4()->toString(),
                ]
            );
            $newJudgingId = $this->em->getConnection()->lastInsertId();
            $newJudging = $this->em->getRepository(Judging::class)
                ->createQueryBuilder('j')
                ->join('j.submission', 's')
                ->join('s.contest_problem', 'cp')
                ->join('s.language', 'l')
                ->join('l.compile_executable', 'e')
                ->join('cp.problem', 'p')
                ->leftJoin('p.compare_executable', 'ce')
                ->leftJoin('ce.immutableExecutable', 'ice')
                ->leftJoin('p.run_executable', 're')
                ->leftJoin('re.immutableExecutable', 'ire')
                ->select('j', 's', 'cp', 'l', 'e', 'p', 'ce', 'ice', 're', 'ire')
                ->andWhere('j.judgingid = :judgingid')
                ->setParameter('judgingid', $newJudgingId)
                ->getQuery()
                ->getSingleResult();

            $this->dj->maybeCreateJudgeTasks($newJudging, $priority, false, $overshoot);

            $this->em->getConnection()->commit();

            if (!$first) {
                $log .= ', ';
            }
            $first = false;
            $log .= sprintf('s%d', $judging->getSubmissionId());
            if ($progressReporter !== null) {
                $progress = (int)round($index / count($judgings) * 100);
                $progressReporter($progress, $log);
            }
        }

        if (count($skipped) == count($judgings)) {
            // We skipped all judgings, this is a hard error. Let's clean up the rejudging and report it.
            // The most common case of this is that a single submissions was requested and already is part of another
            // rejudging.
            $this->em->remove($rejudging);
            $this->em->flush();
            return null;
        }
        return $rejudging;
    }

    /**
     * Finish the given rejudging
     * @param Rejudging     $rejudging        The rejudging to finish
     * @param string        $action           One of the self::ACTION_* constants
     * @param callable|null $progressReporter If given, use this callable to report progress
     * @return bool True iff the rejudging was finished successfully
     * @throws DBALException
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function finishRejudging(Rejudging $rejudging, string $action, ?callable $progressReporter = null): bool
    {
        // This might take a while.
        ini_set('max_execution_time', '300');

        if ($rejudging->getEndtime()) {
            $error = sprintf('Error: rejudging already %s.', $rejudging->getValid() ? 'applied' : 'canceled');
            if ($progressReporter) {
                $progressReporter(0, '', $error);
                return false;
            } else {
                throw new BadMethodCallException($error);
            }
        }

        $rejudgingId = $rejudging->getRejudgingid();

        $todo = $this->calculateTodo($rejudging)['todo'];
        if ($action == self::ACTION_APPLY && $todo > 0) {
            $error = sprintf('Error: %d unfinished judgings left, cannot apply rejudging.', $todo);
            if ($progressReporter) {
                $progressReporter(0, '', $error);
                return false;
            } else {
                throw new BadMethodCallException($error);
            }
        }

        // Get all submissions that we should consider.
        /** @var Submission[] $submissions */
        $submissions = $this->em->createQueryBuilder()
            ->from(Submission::class, 's')
            ->leftJoin('s.judgings', 'j', 'WITH', 'j.rejudging = :rejudging')
            ->join('s.contest', 'c')
            ->join('s.team', 't')
            ->join('s.problem', 'p')
            ->select('s.submitid, c.cid, t.teamid, p.probid, j.judgingid')
            ->andWhere('s.rejudging = :rejudging')
            ->setParameter('rejudging', $rejudging)
            ->getQuery()
            ->getResult();

        $this->dj->auditlog('rejudging', $rejudgingId, $action . 'ing rejudge', '(start)');

        // Add missing state events for all active contests. We do this here
        // and disable doing it in the loop when calling EventLogService::log,
        // because then we would do the same check a lot of times, which is
        // really inefficient and slow. Note that it might be the case that a
        // state change event will happen exactly during applying a rejudging
        // *and* that no client is listening. Given that applying a rejudging
        // will only create judgement and run events and that for these events
        // contest state change events don't really matter, we will only check
        // it once, here.
        // We will also not check dependent object events in the loop, because
        // if we apply a rejudging, the original judgings will already have
        // triggered all dependent events.
        foreach ($this->dj->getCurrentContests() as $contest) {
            $this->eventLogService->addMissingStateEvents($contest);
        }

        // This loop uses direct queries instead of Doctrine classes to speed
        // it up drastically.
        $firstItem = true;
        $index     = 0;
        $log       = '';

        $scoreboardRowsToUpdate = [];
        foreach ($submissions as $submission) {
            $index++;

            if ($action === self::ACTION_APPLY) {
                $this->em->wrapInTransaction(function () use ($submission, $rejudgingId, &$scoreboardRowsToUpdate) {
                    // First invalidate old judging, may be different from prevjudgingid!
                    $this->em->getConnection()->executeQuery(
                        'UPDATE judging SET valid=0 WHERE submitid = :submitid',
                        ['submitid' => $submission['submitid']]
                    );

                    // Then set judging to valid.
                    $this->em->getConnection()->executeQuery(
                        'UPDATE judging SET valid=1 WHERE submitid = :submitid AND rejudgingid = :rejudgingid',
                        ['submitid' => $submission['submitid'], 'rejudgingid' => $rejudgingId]
                    );

                    // Remove relation from submission to rejudge.
                    $this->em->getConnection()->executeQuery(
                        'UPDATE submission SET rejudgingid=NULL WHERE submitid = :submitid',
                        ['submitid' => $submission['submitid']]
                    );

                    // Update caches.
                    $cid = $submission['cid'];
                    $probid = $submission['probid'];
                    if (!isset($scoreboardRowsToUpdate[$cid][$probid])) {
                        $scoreboardRowsToUpdate[$cid][$probid] = true;
                    }

                    // Update event log.
                    $this->eventLogService->log('judging', $submission['judgingid'],
                                                EventLogService::ACTION_CREATE,
                                                $submission['cid'], null, null, false);

                    $runData = $this->em->createQueryBuilder()
                        ->from(JudgingRun::class, 'r')
                        ->select('r.runid')
                        ->andWhere('r.judging = :judgingid')
                        ->andWhere('r.runresult IS NOT NULL')
                        ->setParameter('judgingid', $submission['judgingid'])
                        ->getQuery()
                        ->getResult();
                    $runIds  = array_map(fn(array $data) => $data['runid'], $runData);
                    if (!empty($runIds)) {
                        $this->eventLogService->log('judging_run', $runIds,
                                                    EventLogService::ACTION_CREATE,
                                                    $submission['cid'], null, null, false);
                    }

                    // Update balloons.
                    $contest          = $this->em->getRepository(Contest::class)->find($submission['cid']);
                    $submissionEntity = $this->em->getRepository(Submission::class)->find($submission['submitid']);
                    $judging          = $this->em->getRepository(Judging::class)->find($submission['judgingid']);
                    $this->balloonService->updateBalloons($contest, $submissionEntity, $judging);
                });
            } elseif ($action === self::ACTION_CANCEL) {
                // Reset submission and invalidate judging tasks.

                $params = [
                    'rejudgingid' => $rejudgingId,
                    'submitid' => $submission['submitid'],
                ];
                $this->em->getConnection()->executeQuery(
                    'UPDATE submission
                            SET rejudgingid = NULL
                            WHERE rejudgingid = :rejudgingid
                            AND submitid = :submitid', $params);
                $this->em->getConnection()->executeQuery(
                    'UPDATE judgetask
                            SET valid = 0
                            WHERE jobid = :judgingid
                            AND judgehostid IS NULL', ['judgingid' => $submission['judgingid']]);
                $this->em->getConnection()->executeQuery(
                    'UPDATE judging
                            SET result = :aborted
                            WHERE judgingid = :judgingid
                            AND result IS NULL', ['aborted' => 'aborted', 'judgingid' => $submission['judgingid']]);
            } else {
                $error = "Unknown action '$action' specified.";
                throw new BadMethodCallException($error);
            }

            if ($progressReporter) {
                $log         .= $firstItem ? '' : ', ';
                $log         .= 's' . $submission['submitid'];
                $firstItem   = false;
                $maxProgress = ($action === self::ACTION_APPLY ? static::APPLY_PROGRESS_WITH_SCOREBOARD_UPDATE : 100);
                $progress    = (int)round($index / count($submissions) * $maxProgress);
                $progressReporter($progress, $log);
            }
        }

        if (!empty($scoreboardRowsToUpdate)) {
            if ($progressReporter) {
                $log .= ', updating scoreboard cache';
                $progressReporter(static::APPLY_PROGRESS_WITH_SCOREBOARD_UPDATE, $log);
            }

            // Now update the scoreboard
            foreach ($scoreboardRowsToUpdate as $cid => $probids) {
                $probids = array_keys($probids);
                $contest = $this->em->getRepository(Contest::class)->find($cid);
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
                        ->setParameter('cid', $contest->getCid());
                }
                /** @var Team[] $teams */
                $teams = $queryBuilder->getQuery()->getResult();
                foreach ($teams as $team) {
                    foreach ($probids as $probid) {
                        $problem = $this->em->getRepository(Problem::class)->find($probid);
                        $this->scoreboardService->calculateScoreRow($contest, $team, $problem);
                    }
                    $this->scoreboardService->updateRankCache($contest, $team);
                }
            }
        }

        if ($progressReporter !== null) {
            $progressReporter(100, $log);
        }

        // Update the rejudging itself.
        /** @var Rejudging $rejudging */
        $rejudging = $this->em->getRepository(Rejudging::class)->find($rejudgingId);
        $user      = $this->em->getRepository(User::class)->find($this->dj->getUser()->getUserid());
        $rejudging
            ->setEndtime(Utils::now())
            ->setFinishUser($user)
            ->setValid($action === self::ACTION_APPLY);
        $this->em->flush();

        $this->dj->auditlog('rejudging', $rejudgingId, $action . 'ing rejudge', '(end)');

        return true;
    }

    /**
     * @return array{todo: int, done: int}
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function calculateTodo(Rejudging $rejudging): array
    {
        // Make sure we have the most recent data. This is necessary to
        // guarantee that repeated rejudgings are scheduled correctly.
        $this->em->flush();

        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Judging::class, 'j')
            ->select('COUNT(j)')
            ->andWhere('j.rejudging = :rejudging')
            ->setParameter('rejudging', $rejudging);

        $clonedQueryBuilder = clone $queryBuilder;

        $todo = $queryBuilder
            ->andWhere('j.endtime IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $done = $clonedQueryBuilder
            ->andWhere('j.endtime IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return ['todo' => $todo, 'done' => $done];
    }
}
