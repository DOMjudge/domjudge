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
    public function getScoredPoints(Judging $judging,
                                    array   $judgingRuns): float
    {
        $contestProblem = $judging->getSubmission()->getContestProblem();

        if ($judging->getResult() === 'correct') {
            return $contestProblem->getPoints();
        }

        $lazyEval = $this->config->get('lazy_eval_results');
        $problemLazy = $judging->getSubmission()->getContestProblem()->getLazyEvalResults();
        if (isset($problemLazy)) {
            $lazyEval = $problemLazy;
        }

        $partialScoring = $this->config->get('partial_points_scoring');
        $problemPartialScoring = $judging->getSubmission()->getContestProblem()->getPartialPointsScoring();
        if (isset($problemPartialScoring)) {
            $partialScoring = $problemPartialScoring;
        }

        if (!$partialScoring || $lazyEval !== DOMJudgeService::EVAL_FULL) {
            return 0;
        }

        $groupRuns = [];

        foreach ($judgingRuns as $judgingRun) {
            $group = $judgingRun->getTestcase()->getTestcaseGroup();
            if (!isset($groupRuns[$group->getTestcasegroupid()])) {
                $groupRuns[$group->getTestcasegroupid()] = [$judgingRun];
            } else {
                $groupRuns[$group->getTestcasegroupid()][] = $judgingRun;
            }
        }

        $pointsScored = 0;

        foreach ($groupRuns as $runs) {
            /** @var JudgingRun[] $runs */
            $group = $runs[0]->getTestcase()->getTestcaseGroup();

            $correctRuns = array_reduce($runs,
                fn($carry, $run) => $run->getRunresult() == 'correct' ? $carry + 1 : $carry, 0);
            if ($correctRuns < count($group->getTestcases())) {
                continue;
            }

            $pointsScored += $group->getPointsPercentage() * $contestProblem->getPoints();
        }

        return $pointsScored;
    }
}
