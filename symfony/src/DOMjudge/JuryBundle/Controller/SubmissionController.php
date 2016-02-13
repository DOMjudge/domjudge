<?php

namespace DOMjudge\JuryBundle\Controller;

use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use DOMjudge\JuryBundle\Form\Type\IgnoreSubmissionType;
use DOMjudge\JuryBundle\Form\Type\SubmissionsFilterType;
use DOMjudge\MainBundle\Entity\Judging;
use DOMjudge\MainBundle\Entity\JudgingRun;
use DOMjudge\MainBundle\Entity\Rejudging;
use DOMjudge\MainBundle\Entity\Submission;
use DOMjudge\MainBundle\Entity\TestCase;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
		if ( $request->query->has('submissions_filter') ) {
			$current_filter = array_keys($request->query->get('submissions_filter'))[0];
			$this->get('session')->set('domjudge.submissions_filter', $current_filter);
		}

		$form = $this->createForm(SubmissionsFilterType::class, array(), array(
			'currently_disabled' => $current_filter,
		));

		$current_contest = $this->get('domjudge.contest')->getCurrentContest(false, null, true);
		if ( $current_contest !== null ) {
			$contests = array($current_contest);
		} else {
			$contests = $this->get('domjudge.contest')->getActiveContests(null, false, null, true);
		}

		$limit = 50;

		$restrictions = array();
		if ( $current_filter === 'unverified' ) {
			$restrictions['verified'] = false;
		} elseif ( $current_filter == 'unjudged' ) {
			$restrictions['judged'] = false;
		} elseif ( $current_filter == 'all' ) {
			$limit = null;
		}

		$submissions = $this->get('domjudge.submission')->getSubmissions($contests, $restrictions,
		                                                                 $limit);
		$submissionCount = $this->get('domjudge.submission')->getSubmissionCount($contests,
		                                                                         $restrictions);
		$correctSubmissionCount = $this->get('domjudge.submission')->getCorrectSubmissionCount($contests,
		                                                                                       $restrictions);
		$unverifiedSubmissionCount = $this->get('domjudge.submission')->getUnverifiedSubmissionCount($contests,
		                                                                                             $restrictions);
		$ignoredSubmissionCount = $this->get('domjudge.submission')->getIgnoredSubmissionCount($contests,
		                                                                                       $restrictions);
		$queuedSubmissionCount = $this->get('domjudge.submission')->getQueuedSubmissionCount($contests,
		                                                                                     $restrictions);

		return array(
			'submissions' => $submissions,
			'total' => $submissionCount,
			'correct' => $correctSubmissionCount,
			'unverified' => $unverifiedSubmissionCount,
			'ignored' => $ignoredSubmissionCount,
			'queued' => $queuedSubmissionCount,
			'showContestColumn' => (count($contests) !== 1),
			'filterForm' => $form->createView(),
		);
	}

	/**
	 * @Route("/submission/{submissionId}", name="jury_submission")
	 * @Template()
	 */
	public function viewAction(Request $request, $submissionId)
	{
		// TODO: claiming code

		$em = $this->getDoctrine()->getManager();

		if ( $request->query->has('judging') ) {
			$judgingId = $request->query->get('judging');
		} else {
			$judgingId = null;
		}

		if ( $request->query->has('rejudging') ) {
			$rejudging = $this->getDoctrine()->getRepository('DOMjudgeMainBundle:Rejudging')->find($request->query->get('rejudging'));
			if ( $rejudging === null ) {
				throw new NotFoundHttpException('DOMjudgeMainBundle:Rejudging object not found.');
			}
		} else {
			$rejudging = null;
		}

		if ( $judgingId !== null && $rejudging !== null ) {
			throw new \InvalidArgumentException("You cannot specify judging and rejudging at the same time.");
		}

		if ( $judgingId === null && $rejudging !== null ) {
			// Try to select judging from rejudging
			$query = $this->get('doctrine.orm.entity_manager')->createQueryBuilder()
				->select('partial j.{judgingid}')
				->from('DOMjudgeMainBundle:Judging', 'j')
				->where('j.submission = :submission')
				->andWhere('j.rejudging = :rejudging')
				->setParameter('submission', $submissionId)
				->setParameter('rejudging', $rejudging)
				->getQuery();

			$judgingId = $query->getOneOrNullResult(Query::HYDRATE_SINGLE_SCALAR);
		}

		// Load the submission
		$query = $this->get('doctrine.orm.entity_manager')->createQueryBuilder()
			->select('s, t, p, l, c, cp')
			->from('DOMjudgeMainBundle:Submission', 's')
			->leftJoin('s.team', 't')
			->leftJoin('s.problem', 'p')
			->leftJoin('s.language', 'l')
			->leftJoin('s.contest', 'c')
			->leftJoin('p.contestProblems', 'cp', Expr\Join::WITH, 'cp.contest = c')
			->where('s.submitid = :submission')
			->setParameter('submission', $submissionId)
			->getQuery();

		/** @var Submission $submission */
		$submission = $query->getOneOrNullResult();

		if ( $submission === null ) {
			throw new NotFoundHttpException(sprintf("Submission s%d not found", $submissionId));
		}

		$ignoreForm = null;
		$ignoreFormView = null;

		if ( $this->get('security.authorization_checker')->isGranted('ROLE_ADMIN') ) {
			$ignoreForm = $this->createForm(IgnoreSubmissionType::class,
			                                array(
				                                'submission' => $submission->getSubmitid(),
				                                'ignore' => $submission->getValid(),
			                                ), array(
				                                'ignore' => $submission->getValid(),
			                                )
			);

			$ignoreForm->handleRequest($request);

			// If form was submitted, process ignore status
			if ( $ignoreForm->isSubmitted() && $ignoreForm->isValid() ) {
				$ignore = $ignoreForm['ignore']->getData();
				$submission->setValid(!$ignore);
				$em->flush();

				// Rebuild the form to have it have the new values
				$ignoreForm = $this->createForm(IgnoreSubmissionType::class,
				                                array(
					                                'submission' => $submission->getSubmitid(),
					                                'ignore' => $submission->getValid(),
				                                ), array(
					                                'ignore' => $submission->getValid(),
				                                )
				);
			}

			$ignoreFormView = $ignoreForm->createView();
		}

		// Now load the judgings for this submission
		$query = $this->get('doctrine.orm.entity_manager')->createQueryBuilder()
			->select('partial j.{judgingid,result,valid,startTime,endTime,judgehost,verified,juryMember,verifyComment,outputCompile}, MAX(jr.runTime) AS maxRunTime, partial r.{reason,rejudgingid}')
			->from('DOMjudgeMainBundle:Judging', 'j')
			->leftJoin('j.judgingRuns', 'jr')
			->leftJoin('j.rejudging', 'r')
			->where('j.contest = :contest')
			->andWhere('j.submission = :submission')
			->groupBy('j.judgingid')
			->orderBy('j.startTime', 'ASC')
			->addOrderBy('j.judgingid', 'ASC')
			->setParameter('contest', $submission->getContest())
			->setParameter('submission', $submission)
			->getQuery();

		// Because of the MAX(), this will be an array where each element is an array with two keys:
		// * 0: the Judging object
		// * maxRunTime: the result from the MAX()
		$judgings = $query->getResult();

		// If there is no judging selected through the request, we select the
		// valid one.
		if ( $judgingId === null ) {
			/** @var Judging[] $judging */
			foreach ( $judgings as $judging ) {
				if ( $judging[0]->getValid() ) {
					$judgingId = $judging[0]->getJudgingid();
					break;
				}
			}
		}

		// And load the judging
		if ( $judgingId === null ) {
			$currentJudging = null;
		} else {
			$query = $this->get('doctrine.orm.entity_manager')->createQueryBuilder()
				->select('partial j.{judgingid,result,valid,startTime,endTime,judgehost,verified,juryMember,verifyComment,outputCompile}')
				->from('DOMjudgeMainBundle:Judging', 'j')
				->where('j.judgingid = :judging')
				->andWhere('j.submission = :submission')
				->setParameter('judging', $judgingId)
				->setParameter('submission', $submission)
				->getQuery();

			/** @var Judging $currentJudging */
			$currentJudging = $query->getOneOrNullResult();

			if ( $currentJudging === null ) {
				throw new NotFoundHttpException(sprintf("Judging j%d not found for submission s%d",
				                                        $judgingId, $submission->getSubmitid()));
			}
		}

		if ( $currentJudging !== null ) {
			$query = $this->get('doctrine.orm.entity_manager')->createQueryBuilder()
				->select('partial t.{testcaseid,rank,description,imageType,imageThumb}')
				->addSelect('SUBSTRING(t.output, 1, 50001) AS outputReference')
				->from('DOMjudgeMainBundle:TestCase', 't')
				->where('t.problem = :problem')
				->orderBy('t.rank')
				->setParameter('problem', $submission->getProblem())
				->getQuery();

			/** @var array $testCases */
			$testCases = $query->getResult();

			$query = $this->get('doctrine.orm.entity_manager')->createQueryBuilder()
				->select('partial r.{runid,judging,testcaseid,testcase,runResult,runTime}')
				->addSelect('SUBSTRING(r.outputRun, 1, 50001) AS outputRun')
				->addSelect('SUBSTRING(r.outputDiff, 1, 50001) AS outputDiff')
				->addSelect('SUBSTRING(r.outputError, 1, 50001) AS outputError')
				->addSelect('SUBSTRING(r.outputSystem, 1, 50001) AS outputSystem')
				->from('DOMjudgeMainBundle:JudgingRun', 'r', 'r.testcaseid')
				->where('r.judging = :judging')
				->setParameter('judging', $currentJudging)
				->getQuery();

			/** @var array $runs */
			$runs = $query->getResult();

			$lastSubmission = $submission->getOriginalSubmission();

			if ( $lastSubmission === null ) {
				$query = $this->get('doctrine.orm.default_entity_manager')->createQueryBuilder()
					->select('s')
					->from('DOMjudgeMainBundle:Submission', 's')
					->where('s.team = :team')
					->andWhere('s.problem = :problem')
					->andWhere('s.submitTime < :submittime')
					->orderBy('s.submitTime', 'DESC')
					->setMaxResults(1)
					->setParameter('team', $submission->getTeam())
					->setParameter('problem', $submission->getProblem())
					->setParameter('submittime', $submission->getSubmitTime())
					->getQuery();

				$lastSubmission = $query->getOneOrNullResult();
			}
		} else {
			$testCases = null;
			$runs = null;
			$lastSubmission = null;
		}

		if ( $lastSubmission !== null ) {
			$query = $this->get('doctrine.orm.default_entity_manager')->createQueryBuilder()
				->select('partial j.{judgingid,result,verifyComment,endTime}')
				->addSelect('partial r.{rejudgingid,valid}')
				->from('DOMjudgeMainBundle:Judging', 'j')
				->leftJoin('j.rejudging', 'r')
				->where('j.submission = :submission')
				->andWhere('j.valid = 1')
				->orderBy('j.judgingid', 'DESC')
				->setMaxResults(1)
				->setParameter('submission', $lastSubmission)
				->getQuery();

			/** @var Judging $lastJudging */
			$lastJudging = $query->getOneOrNullResult();
		} else {
			$lastJudging = null;
		}

		if ( $lastJudging !== null ) {
			$query = $this->get('doctrine.orm.default_entity_manager')->createQueryBuilder()
				->select('partial r.{runid,runTime,runResult}')
				->from('DOMjudgeMainBundle:JudgingRun', 'r', 'r.testcaseid')
				->where('r.judging = :judging')
				->setParameter('judging', $lastJudging)
				->getQuery();

			/** @var JudgingRun[] $lastRuns */
			$lastRuns = $query->getResult();

			$sumLastRunTime = 0;
			$maxLastRunTime = 0;

			foreach ( $lastRuns as $judgingRun ) {
				$sumLastRunTime += $judgingRun->getRunTime();
				$maxLastRunTime = max($maxLastRunTime, $judgingRun->getRunTime());
			}
		} else {
			$lastRuns = null;
			$sumLastRunTime = 0;
			$maxLastRunTime = 0;
		}

		$sumRunTime = 0;
		$maxRunTime = 0;
		foreach ( $runs as $run ) {
			/** @var JudgingRun $judgingRun */
			$judgingRun = $run[0];
			$sumRunTime += $judgingRun->getRunTime();
			$maxRunTime = max($maxRunTime, $judgingRun->getRunTime());
		}

		return array(
			'submission' => $submission,
			'judgings' => $judgings,
			'currentJudging' => $currentJudging,
			'testCases' => $testCases,
			'runs' => $runs,
			'sumRunTime' => $sumRunTime,
			'maxRunTime' => $maxRunTime,
			'lastSubmission' => $lastSubmission,
			'lastJudging' => $lastJudging,
			'lastRuns' => $lastRuns,
			'sumLastRunTime' => $sumLastRunTime,
			'maxLastRunTime' => $maxLastRunTime,
			'ignoreForm' => $ignoreFormView,
		);
	}
}
