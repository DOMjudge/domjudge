<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Balloon;
use App\Entity\Contest;
use App\Entity\Judging;
use App\Entity\ScoreCache;
use App\Entity\Submission;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;

class BalloonService
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var ConfigurationService
     */
    protected $config;

    /**
     * BalloonService constructor.
     *
     * @param EntityManagerInterface $em
     * @param ConfigurationService   $config
     */
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
     * @param Contest      $contest
     * @param Submission   $submission
     * @param Judging|null $judging
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     */
    public function updateBalloons(
        Contest $contest,
        Submission $submission,
        Judging $judging = null
    ) {
        // Balloon processing disabled for contest
        if (!$contest->getProcessBalloons()) {
            return;
        }

        // Make sure judging is correct
        if (!$judging || $judging->getResult() !== Judging::RESULT_CORRECT) {
            return;
        }

        // Also make sure it is verified if this is required
        if (!$judging->getVerified() &&
            $this->config->get('verification_required')) {
            return;
        }

        // prevent duplicate balloons in case of multiple correct submissions
        $numCorrect = $this->em->createQueryBuilder()
            ->from(Balloon::class, 'b')
            ->select('COUNT(b.submitid) AS numBalloons')
            ->join('b.submission', 's')
            ->andWhere('s.valid = 1')
            ->andWhere('s.probid = :probid')
            ->andWhere('s.teamid = :teamid')
            ->andWhere('s.cid = :cid')
            ->setParameter(':probid', $submission->getProbid())
            ->setParameter(':teamid', $submission->getTeamid())
            ->setParameter(':cid', $submission->getCid())
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

    public function collectBalloonTable(Contest $contest): array
    {
        $em = $this->em;
        $showPostFreeze = (bool)$this->config->get('show_balloons_postfreeze');
        if (!$showPostFreeze) {
            $freezetime = $contest->getFreezeTime();
        }

        // Build a list of teams and the problems they solved first
        $firstSolved = $em->getRepository(ScoreCache::class)->findBy(['is_first_to_solve' => 1]);
        $firstSolvers = [];
        foreach ($firstSolved as $scoreCache) {
            $firstSolvers[$scoreCache->getTeam()->getTeamId()][] = $scoreCache->getProblem()->getProbid();
        }

        $query = $em->createQueryBuilder()
            ->select('b', 's.submittime', 'p.probid',
                't.teamid', 't.name AS teamname', 't.room',
                'c.name AS catname',
                's.cid', 'co.shortname',
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
            ->setParameter(':cid', $contest->getCid())
            ->orderBy('b.done', 'ASC')
            ->addOrderBy('s.submittime', 'DESC');

        $balloons = $query->getQuery()->getResult();
        // Loop once over the results to get totals and awards
        $TOTAL_BALLOONS = $AWARD_BALLOONS = [];
        foreach ($balloons as $balloonsData) {
            if ($balloonsData['color'] === null) {
                continue;
            }

            $TOTAL_BALLOONS[$balloonsData['teamid']][$balloonsData['probshortname']] = $balloonsData['color'];

            // Keep a list of balloons that were first to solve this problem;
            // can be multiple, one for each sortorder.
            if (in_array($balloonsData['probid'], $firstSolvers[$balloonsData['teamid']] ?? [], true)) {
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

            if ($color === null) {
                continue;
            }
            $balloon = $balloonsData[0];

            $balloonId = $balloon->getBalloonId();

            $stime = $balloonsData['submittime'];

            if (isset($freezetime) && $stime >= $freezetime) {
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
            $balloondata['done'] = $balloon->getDone();

            $balloons_table[] = [
                'data' => $balloondata,
            ];
        }
        return $balloons_table;
    }
}
