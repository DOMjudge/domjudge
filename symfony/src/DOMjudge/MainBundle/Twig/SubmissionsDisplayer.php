<?php

namespace DOMjudge\MainBundle\Twig;

use DOMjudge\MainBundle\Entity\Judging;
use DOMjudge\MainBundle\Entity\JudgingRun;
use DOMjudge\MainBundle\Entity\Rejudging;
use DOMjudge\MainBundle\Entity\Submission;
use DOMjudge\MainBundle\Entity\TestCase;

class SubmissionsDisplayer extends \Twig_Extension
{
	public function getFunctions()
	{
		return array(
			new \Twig_SimpleFunction('submission_counts', array($this, 'submissionCounts'), array(
				'is_safe' => array('html'),
				'needs_environment' => true,
			)),
			new \Twig_SimpleFunction('submissions', array($this, 'submissions'), array(
				'is_safe' => array('html'),
				'needs_environment' => true,
			)),
			new \Twig_SimpleFunction('testcaseRun', array($this, 'testcaseRun'), array(
				'is_safe' => array('html'),
				'needs_environment' => true,
			)),
		);
	}

	/**
	 * Render a list of submissions
	 *
	 * @param \Twig_Environment $twig
	 *   The Twig environment to use
	 * @param Submission[] $submissions
	 *   The list of submissions to render
	 * @param bool $isJury
	 *   Whether to render this list for the jury
	 * @param Submission|null $highlight
	 *   If not null, highlight the row for the given submission
	 * @param bool $showContestColumn
	 *   Whether to show the contest column
	 * @param bool $showOldResult
	 *   Whether to show the old result
	 * @param Rejudging $rejudging
	 *   The rejudging to use or null to not use one
	 * @param Judging[] $oldJudgings
	 *   The old judgings for the given submissions. Keys should be submission ID's
	 * @return string
	 *   The rendered submissions
	 */
	public function submissions(\Twig_Environment $twig, $submissions, $isJury = false,
	                            Submission $highlight = null,
	                            $showContestColumn = false, $showOldResult = false,
	                            Rejudging $rejudging = null, $oldJudgings = array())
	{
		return $twig->render('@DOMjudgeMain/submissions.html.twig', array(
			'submissions' => $submissions,
			'isJury' => $isJury,
			'highlight' => $highlight,
			'showContestColumn' => $showContestColumn,
			'showOldResult' => $showOldResult,
			'rejudging' => $rejudging,
			'oldJudgings' => $oldJudgings,
		));
	}

	/**
	 * Render the submission counts for a submission list
	 *
	 * @param \Twig_Environment $twig
	 *   The Twig environment to use
	 * @param int $total
	 *   The total number of submissions
	 * @param int $correct
	 *   The total number correct of submissions
	 * @param int $unverified
	 *   The total number unverified of submissions
	 * @param int $ignored
	 *   The total number ignored of submissions
	 * @param int $queued
	 *   The total number queued of submissions
	 * @return string The rendered counts
	 *   The rendered counts
	 */
	public function submissionCounts(\Twig_Environment $twig, $total, $correct, $unverified,
	                                 $ignored, $queued)
	{
		return $twig->render('@DOMjudgeMain/submission_counts.html.twig', array(
			'total' => $total,
			'correct' => $correct,
			'unverified' => $unverified,
			'ignored' => $ignored,
			'queued' => $queued,
		));
	}

	/**
	 * Display a testcase result
	 * 
	 * @param \Twig_Environment $twig
	 *   The Twig environment to use
	 * @param TestCase $testCase
	 *   The testcase to use
	 * @param JudgingRun|null $judgingRun
	 *   The judging run to use
	 * @param bool $final
	 *   Whether the judging is final
	 * @return string
	 *   The rendered testcase
	 */
	public function testcaseRun(\Twig_Environment $twig, TestCase $testCase, JudgingRun $judgingRun = null, $final)
	{
		$class = ( $final ? "tc_unused" : "tc_pending" );
		$short = "?";
		if ($judgingRun !== null) {
			switch ( $judgingRun->getRunResult() ) {
				case 'correct':
					$class = "tc_correct";
					$short = "✓";
					break;
				case NULL:
					break;
				default:
					$short = substr($judgingRun->getRunResult(), 0, 1);
					$class = "tc_incorrect";
			}
		}
		
		return $twig->render('@DOMjudgeMain/testcase_run.html.twig', array(
			'testCase' => $testCase,
			'judgingRun' => $judgingRun,
			'class' => $class,
			'short' => $short,
		));
	}

	/**
	 * Returns the name of the extension.
	 *
	 * @return string The extension name
	 */
	public function getName()
	{
		return 'submissions_displayer';
	}
}
