<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Balloon;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Judging;
use App\Entity\Problem;
use App\Entity\ScoreCache;
use App\Entity\Submission;
use App\Entity\Team;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BalloonService
{
    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly ConfigurationService $config
    ) {}

    /**
     * Update the balloons table after a correct submission.
     *
     * This function double checks that the judging is correct and confirmed.
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws ORMException
     */
    public function updateBalloons(
        Contest $contest,
        Submission $submission,
        Judging $judging
    ): void {
        // Balloon processing disabled for contest.
        if (!$contest->getProcessBalloons()) {
            return;
        }

        // Make sure judging is correct.
        if ($judging->getResult() !== Judging::RESULT_CORRECT) {
            return;
        }

        // Also make sure it is verified if this is required.
        if (!$judging->getVerified() &&
            $this->config->get('verification_required')) {
            return;
        }

        // Prevent duplicate balloons in case of multiple correct submissions.
        $numCorrect = $this->em->createQueryBuilder()
            ->from(Balloon::class, 'b')
            ->select('COUNT(b.submission) AS numBalloons')
            ->andWhere('b.problem = :probid')
            ->andWhere('b.team = :teamid')
            ->andWhere('b.contest = :cid')
            ->setParameter('probid', $submission->getProblem())
            ->setParameter('teamid', $submission->getTeam())
            ->setParameter('cid', $submission->getContest())
            ->getQuery()
            ->getSingleScalarResult();

        if ($numCorrect == 0) {
            $balloon = new Balloon();
            $balloon->setSubmission($this->em->getReference(Submission::class, $submission->getSubmitid()));
            $balloon->setTeam($this->em->getReference(Team::class, $submission->getTeamId()));
            $balloon->setContest(
                $this->em->getReference(Contest::class, $submission->getContest()->getCid()));
            $balloon->setProblem($this->em->getReference(Problem::class, $submission->getProblemId()));
            $this->em->persist($balloon);
            try {
                $this->em->flush();
            } catch (UniqueConstraintViolationException $e) {
            }
        }
    }

    /**
     * @return array<array{data: array{balloonid: int, time: string, problem: string, contestproblem: ContestProblem,
     *                                 team: Team, teamid: int, location: string|null, affiliation: string|null,
     *                                 affiliationid: int, category: string, categoryid: int, total: array<string, ContestProblem>,
     *                                 done: bool}}>
    */
    public function collectBalloonTable(Contest $contest, bool $todo = false): array
    {
        $em = $this->em;

        // Retrieve all relevant balloons in 'submit order'. This allows accurate
        // counts when deciding whether to hand out post-freeze balloons.
        $query = $em->createQueryBuilder()
            ->select('b', 's.submittime', 'p.probid',
                't.teamid', 's', 't', 't.location',
                'c.categoryid AS categoryid', 'c.sortorder', 'c.name AS catname',
                'co.cid', 'co.shortname',
                'cp.shortname AS probshortname', 'cp.color',
                'a.affilid AS affilid', 'a.shortname AS affilshort')
            ->from(Balloon::class, 'b')
            ->leftJoin('b.submission', 's')
            ->leftJoin('b.problem', 'p')
            ->leftJoin('b.contest', 'co')
            ->leftJoin('p.contest_problems', 'cp', Join::WITH, 'co.cid = cp.contest AND p.probid = cp.problem')
            ->leftJoin('b.team', 't')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.affiliation', 'a')
            ->andWhere('co.cid = :cid')
            ->setParameter('cid', $contest->getCid())
            ->orderBy('b.done', 'DESC')
            ->addOrderBy('s.submittime', 'ASC');

        $balloons = $query->getQuery()->getResult();

        $minimumNumberOfBalloons = (int)$this->config->get('minimum_number_of_balloons');
        $ignorePreFreezeSolves = (bool)$this->config->get('any_balloon_postfreeze');
        $freezetime = $contest->getFreezeTime();

        $balloonsTable = [];

        // Total balloons keeps track of the total balloons for a team, will be used to fill the rhs for every row in $balloonsTable.
        // The same summary is used for every row for a team. References to elements in this array ensure easy updates.
        /** @var mixed[] $balloonSummaryPerTeam */
        $balloonSummaryPerTeam = [];

        foreach ($balloons as $balloonsData) {
            if ($balloonsData['color'] === null) {
                continue;
            }
            /** @var Balloon $balloon */
            $balloon = $balloonsData[0];
            $done = $balloon->getDone();

            // Ensure a summary-row exists for this sortorder and take a reference to these summaries. References are needed to ensure array reuse.
            // Summaries are used to determine whether a balloon has been handed out so they need to be separated between sortorders.
            $balloonSummaryPerTeam[$balloonsData['sortorder']] ??= [];
            $relevantBalloonSummaries = &$balloonSummaryPerTeam[$balloonsData['sortorder']];

            // Commonly no balloons are handed out post freeze.
            // Underperforming teams' moral can be boosted by handing out balloons post-freeze.
            // Handing out balloons for problems that have not been solved pre-freeze poses a potential information leak, so these are always excluded.
            // So to decide whether to skip showing a balloon:
            //   1. Check whether the scoreboard has been frozen.
            //   2. Check whether the team has exceeded minimum number of balloons.
            //   3. Check whether the problem been solved pre-freeze.
            $stime = $balloonsData['submittime'];
            if ($ignorePreFreezeSolves === false && isset($freezetime) && $stime >= $freezetime) {
                if (count($relevantBalloonSummaries[$balloonsData['teamid']] ?? []) >= $minimumNumberOfBalloons) {
                    continue;
                }

                // Check if problem has been solved before the freeze by someone in the same sortorder to prevent information leak.
                // Checking for solved problems in the same sortorder prevent information leaks from teams like DOMjudge that have commonly solved
                // all problems (jury solutions) but are not in the same sortorder. If a balloon for this problem should've been handed out it is
                // safe to hand out again since balloons are handled in 'submit order'.
                if (!array_reduce($relevantBalloonSummaries, fn($c, $i) => $c ||
                    array_key_exists($balloonsData['probshortname'], $i), false)) {
                    continue;
                }
            }

            // Register the balloon that is handed out in the team summary.
            $relevantBalloonSummaries[$balloonsData['teamid']][$balloonsData['probshortname']] = $balloon->getSubmission()->getContestProblem();

            // This balloon might not need to be listed, entire order is needed for counts though.
            if ($todo && $done) {
                continue;
            }

            $balloonsTable[] = [
                'data' => [
                    'balloonid' => $balloon->getBalloonId(),
                    'time' => $stime,
                    'problem' => $balloonsData['probshortname'],
                    'contestproblem' => $balloon->getSubmission()->getContestProblem(),
                    'team' => $balloon->getSubmission()->getTeam(),
                    'teamid' => $balloonsData['teamid'],
                    'location' => $balloonsData['location'],
                    'affiliation' => $balloonsData['affilshort'],
                    'affiliationid' => $balloonsData['affilid'],
                    'category' => $balloonsData['catname'],
                    'categoryid' => $balloonsData['categoryid'],
                    'done' => $done,

                    // Reuse the same total summary table by taking a reference, makes updates easier.
                    'total' => &$relevantBalloonSummaries[$balloonsData['teamid']],
                ]
            ];
        }

        // Sort the balloons, since these are handled by reference each summary item only need to be sorted once.
        foreach ($balloonSummaryPerTeam as $relevantBalloonSummaries) {
            foreach ($relevantBalloonSummaries as &$balloons) {
                ksort($balloons);
            }
        }

        // Reverse the order so the newest appear first
        return array_reverse($balloonsTable);
    }

    public function setDone(int $balloonId): void
    {
        $em = $this->em;
        $balloon = $em->getRepository(Balloon::class)->find($balloonId);
        if (!$balloon) {
            throw new NotFoundHttpException('balloon not found');
        }
        $balloon->setDone(true);
        $em->flush();
    }
}
