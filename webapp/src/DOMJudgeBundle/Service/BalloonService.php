<?php declare(strict_types=1);

namespace DOMJudgeBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Balloon;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\Judging;
use DOMJudgeBundle\Entity\Submission;

class BalloonService
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
     * BalloonService constructor.
     * @param EntityManagerInterface $entityManager
     * @param DOMJudgeService        $DOMJudgeService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService
    ) {
        $this->entityManager   = $entityManager;
        $this->DOMJudgeService = $DOMJudgeService;
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
        // Make sure judging is correct
        if (!$judging || $judging->getResult() !== Judging::RESULT_CORRECT) {
            return;
        }

        // Also make sure it is verified if this is required
        if (!$judging->getVerified() &&
            $this->DOMJudgeService->dbconfig_get('verification_required', false)) {
            return;
        }

        // prevent duplicate balloons in case of multiple correct submissions
        $numCorrect = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Balloon', 'b')
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
            if ($contest->getProcessBalloons()) {
                $balloon = new Balloon();
                $balloon->setSubmission(
                    $this->entityManager->getReference(Submission::class, $submission->getSubmitid()));
                $this->entityManager->persist($balloon);
                $this->entityManager->flush();
            }
        }
    }
}
