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
     *                                 awards: string, done: bool}}>
    */
    public function collectBalloonTable(Contest $contest, bool $todo = false): array
    {
        $em = $this->em;
        $showPostFreeze = (bool)$this->config->get('show_balloons_postfreeze');
        if (!$showPostFreeze) {
            $freezetime = $contest->getFreezeTime();
        }

        // Build a list of teams and the problems they solved first.
        $firstSolved = $em->getRepository(ScoreCache::class)->findBy(['is_first_to_solve' => 1]);
        $firstSolvers = [];
        foreach ($firstSolved as $scoreCache) {
            $firstSolvers[$scoreCache->getTeam()->getTeamId()][] = $scoreCache->getProblem()->getProbid();
        }

        $query = $em->createQueryBuilder()
            ->select('b', 's.submittime', 'p.probid',
                't.teamid', 's', 't', 't.location',
                'c.categoryid AS categoryid', 'c.name AS catname',
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
            ->orderBy('b.done', 'ASC')
            ->addOrderBy('s.submittime', 'DESC');

        $balloons = $query->getQuery()->getResult();
        // Loop once over the results to get totals and awards.
        $TOTAL_BALLOONS = $AWARD_BALLOONS = [];
        foreach ($balloons as $balloonsData) {
            if ($balloonsData['color'] === null) {
                continue;
            }

            $TOTAL_BALLOONS[$balloonsData['teamid']][$balloonsData['probshortname']] = $balloonsData[0]->getSubmission()->getContestProblem();

            // Keep a list of balloons that were first to solve this problem;
            // can be multiple, one for each sortorder.
            if (in_array($balloonsData['probid'], $firstSolvers[$balloonsData['teamid']] ?? [], true)) {
                $AWARD_BALLOONS['problem'][$balloonsData['probid']][] = $balloonsData[0]->getBalloonId();
            }
            // Keep overwriting this - in the end it'll
            // contain the ID of the first balloon in this contest.
            $AWARD_BALLOONS['contest'] = $balloonsData[0]->getBalloonId();
        }

        // Loop again to construct table.
        $balloons_table = [];
        foreach ($balloons as $balloonsData) {
            if ($balloonsData['color'] === null) {
                continue;
            }
            /** @var Balloon $balloon */
            $balloon = $balloonsData[0];
            $done = $balloon->getDone();

            if ($todo && $done) {
                continue;
            }

            $balloonId = $balloon->getBalloonId();

            $stime = $balloonsData['submittime'];

            if (isset($freezetime) && $stime >= $freezetime) {
                continue;
            }

            $balloondata = [];
            $balloondata['balloonid'] = $balloonId;
            $balloondata['time'] = $stime;
            $balloondata['problem'] = $balloonsData['probshortname'];
            $balloondata['contestproblem'] = $balloon->getSubmission()->getContestProblem();
            $balloondata['team'] = $balloon->getSubmission()->getTeam();
            $balloondata['teamid'] = $balloonsData['teamid'];
            $balloondata['location'] = $balloonsData['location'];
            $balloondata['affiliation'] = $balloonsData['affilshort'];
            $balloondata['affiliationid'] = $balloonsData['affilid'];
            $balloondata['category'] = $balloonsData['catname'];
            $balloondata['categoryid'] = $balloonsData['categoryid'];

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
            $balloondata['done'] = $done;

            $balloons_table[] = [
                'data' => $balloondata,
            ];
        }
        return $balloons_table;
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
