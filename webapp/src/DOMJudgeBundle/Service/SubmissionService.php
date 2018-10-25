<?php declare(strict_types=1);

namespace DOMJudgeBundle\Service;

use DOMJudgeBundle\Entity\JudgingRun;

/**
 * Class SubmissionService
 * @package DOMJudgeBundle\Service
 */
class SubmissionService
{
    /**
     * Determines final result for a judging given an ordered array of
     * judging runs. Runs can be NULL if not run yet. A return value of
     * NULL means that a final result cannot be determined yet; this may
     * only occur when not all testcases have been run yet.
     * @param JudgingRun[] $runs
     * @param array        $resultsPrio
     * @return string|null
     */
    public function getFinalResult(array $runs, array $resultsPrio)
    {
        // Whether we have NULL results
        $haveNullResult = false;

        // This stores the current result and priority to be returned:
        $bestRun      = null;
        $bestPriority = -1;

        foreach ($runs as $testCase => $run) {
            if ($run === null) {
                $haveNullResult = true;
            } else {
                $priority = $resultsPrio[$run->getRunresult()];
                if (empty($priority)) {
                    throw new \InvalidArgumentException(
                        sprintf("Unknown results '%s' found", $run->getRunresult()));
                }
                if ($priority > $bestPriority) {
                    $bestRun      = $run;
                    $bestPriority = $priority;
                }
            }
        }

        // If we have NULL results, check whether the highest priority
        // result has maximal priority. Use a local copy of the
        // 'resultsPrio' array, keeping the original untouched.
        $tmp = $resultsPrio;
        rsort($tmp);
        $maxPriority = reset($tmp);

        // No highest priority result found: no final answer yet.
        if ($haveNullResult && $bestPriority < $maxPriority) {
            return null;
        }

        return $bestRun ? $bestRun->getRunresult() : null;
    }
}
