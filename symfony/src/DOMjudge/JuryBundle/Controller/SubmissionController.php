<?php

namespace DOMjudge\JuryBundle\Controller;

use DOMjudge\JuryBundle\Resources\Type\SubmissionsFilterType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SubmissionController extends Controller
{
	/**
	 * @Route("/submissions", name="jury_submissions")
	 * @Template()
	 */
	public function listAction(Request $request)
	{
		$current_filter = $this->get('session')->get('domjudge.submissions_filter', 'newest');
		if ($request->query->has('submissions_filter')) {
			$current_filter = array_keys($request->query->get('submissions_filter'))[0];
			$this->get('session')->set('domjudge.submissions_filter', $current_filter);
		}
		
		$form = $this->createForm(SubmissionsFilterType::class, array(), array(
			'currently_disabled' => $current_filter,
		));
		
		$current_contest = $this->get('domjudge.contest')->getCurrentContest(false, null, true);
		if ($current_contest !== null)
		{
			$contests = array($current_contest);
		} else {
			$contests = $this->get('domjudge.contest')->getActiveContests(null, false, null, true);
		}
		
		$limit = 50;

		$restrictions = array();
		if ($current_filter === 'unverified') {
			$restrictions['verified'] = false;
		} elseif ($current_filter == 'unjudged') {
			$restrictions['judged'] = false;
		} elseif ($current_filter == 'all') {
			$limit = null;
		}
		
		$submissions = $this->get('domjudge.submission')->getSubmissions($contests, $restrictions, $limit);
		$submissionCount = $this->get('domjudge.submission')->getSubmissionCount($contests, $restrictions);
		$correctSubmissionCount = $this->get('domjudge.submission')->getCorrectSubmissionCount($contests, $restrictions);
		$unverifiedSubmissionCount = $this->get('domjudge.submission')->getUnverifiedSubmissionCount($contests, $restrictions);
		$ignoredSubmissionCount = $this->get('domjudge.submission')->getIgnoredSubmissionCount($contests, $restrictions);
		$queuedSubmissionCount = $this->get('domjudge.submission')->getQueuedSubmissionCount($contests, $restrictions);
		
		return array(
			'submissions' => $submissions,
			'submissionCount' => $submissionCount,
			'correctSubmissionCount' => $correctSubmissionCount,
			'unverifiedSubmissionCount' => $unverifiedSubmissionCount,
			'ignoredSubmissionCount' => $ignoredSubmissionCount,
			'queuedSubmissionCount' => $queuedSubmissionCount,
			'filterForm' => $form->createView(), 
		);
	}
}
