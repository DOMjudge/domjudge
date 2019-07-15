<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Balloon;
use App\Entity\Contest;
use App\Entity\Judging;
use App\Entity\Submission;
use Doctrine\ORM\EntityManagerInterface;

class BalloonService
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
     * BalloonService constructor.
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj
    ) {
        $this->em = $em;
        $this->dj = $dj;
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
            $this->dj->dbconfig_get('verification_required', false)) {
            return;
        }

        // prevent duplicate balloons in case of multiple correct submissions
        $numCorrect = $this->em->createQueryBuilder()
            ->from(Balloon::class, 'b')
            ->select('COUNT(b.submitid) AS numBallons')
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
}
