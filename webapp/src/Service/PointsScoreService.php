<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\ContestProblem;
use App\Entity\Judging;
use App\Entity\JudgingRun;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class SubmissionService
 * @package App\Service
 */
class PointsScoreService
{
    protected EntityManagerInterface $em;
    protected LoggerInterface $logger;
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected EventLogService $eventLogService;
    protected ScoreboardService $scoreboardService;

    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface        $logger,
        DOMJudgeService        $dj,
        ConfigurationService   $config,
        EventLogService        $eventLogService,
        ScoreboardService      $scoreboardService
    )
    {
        $this->em = $em;
        $this->logger = $logger;
        $this->dj = $dj;
        $this->config = $config;
        $this->eventLogService = $eventLogService;
        $this->scoreboardService = $scoreboardService;
    }

    /**
     * @param JudgingRun[] $judgingRuns
     */
    public static function getScoredPoints(Judging         $judging,
                                           array           $judgingRuns,
                                           ContestProblem  $contestProblem,
                                           DOMJudgeService $dj): float
    {
        if ($judging->getResult() === 'correct') {
            return $contestProblem->getPoints();
        }

        $pointsScored = 0;

        foreach ($contestProblem->getProblem()->getTestcaseGroups() as $testcaseGroup) {
            foreach ($judgingRuns as $judgingRun) {
                $runGroup = $judgingRun->getTestcase()->getTestcaseGroup();

                if ($runGroup === null) {
                    continue;
                }

                if ($runGroup->getTestcasegroupid() === $testcaseGroup->getTestcasegroupid()
                    && $judgingRun->getRunresult() !== "correct") {
                    continue (2);
                }
            }

            $pointsScored += $testcaseGroup->getPointsPercentage() * $contestProblem->getPoints();
        }

        return $pointsScored;
    }
}
