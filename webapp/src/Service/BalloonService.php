<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Balloon;
use App\Entity\Contest;
use App\Entity\Judging;
use App\Entity\ScoreCache;
use App\Entity\Submission;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BalloonService
{
    protected EntityManagerInterface $em;
    protected ConfigurationService $config;

    public function __construct(
        EntityManagerInterface $em,
        ConfigurationService $config
    ) {
        $this->em     = $em;
        $this->config = $config;
    }

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
        ?Judging $judging = null
    ): void {
        // Balloon processing disabled for contest.
        if (!$contest->getProcessBalloons()) {
            return;
        }

        // Make sure judging is correct.
        if (!$judging || $judging->getResult() !== Judging::RESULT_CORRECT) {
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
            ->join('b.submission', 's')
            ->select('COUNT(b.submission) AS numBalloons')
            ->andWhere('s.valid = 1')
            ->andWhere('s.problem = :probid')
            ->andWhere('s.team = :teamid')
            ->andWhere('s.contest = :cid')
            ->setParameter('probid', $submission->getProblem())
            ->setParameter('teamid', $submission->getTeam())
            ->setParameter('cid', $submission->getContest())
            ->getQuery()
            ->getSingleScalarResult();

        if ($numCorrect == 0) {
            $balloon = new Balloon();
            $balloon->setSubmission(
                $this->em->getReference(Submission::class, $submission->getSubmitid()));
            $this->em->persist($balloon);
            $this->em->flush();
        }
    }

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
                't.teamid', 't.name AS teamname', 't.room',
                'c.name AS catname',
                'co.cid', 'co.shortname',
                'cp.shortname AS probshortname', 'cp.color',
                'a.affilid AS affilid', 'a.shortname AS affilshort')
            ->from(Balloon::class, 'b')
            ->leftJoin('b.submission', 's')
            ->leftJoin('s.problem', 'p')
            ->leftJoin('s.contest', 'co')
            ->leftJoin('p.contest_problems', 'cp', Join::WITH, 'co.cid = cp.contest AND p.probid = cp.problem')
            ->leftJoin('s.team', 't')
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
            $balloondata['team'] = "t" . $balloonsData['teamid'] . ": " . $balloonsData['teamname'];
            $balloondata['teamid'] = $balloonsData['teamid'];
            $balloondata['location'] = $balloonsData['room'];
            $balloondata['affiliation'] = $balloonsData['affilshort'];
            $balloondata['affiliationid'] = $balloonsData['affilid'];
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
