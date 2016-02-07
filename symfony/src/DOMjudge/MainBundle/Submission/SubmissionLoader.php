<?php

namespace DOMjudge\MainBundle\Submission;

use Doctrine\ORM\EntityManager;
use DOMjudge\MainBundle\Entity\Contest;
use DOMjudge\MainBundle\Entity\Judging;
use DOMjudge\MainBundle\Entity\Submission;
use DOMjudge\MainBundle\Entity\Rejudging;
use Doctrine\ORM\Query\Expr;

class SubmissionLoader
{
	/**
	 * @var EntityManager
	 */
	private $entityManager;

	public function __construct(EntityManager $entityManager)
	{
		$this->entityManager = $entityManager;
	}

	/**
	 * Get all submissions for a given set of restrictions
	 *
	 * @param Contest[] $contests
	 *   Get all submissions for the given contests
	 * @param array $restrictions
	 *   If empty, do not restrict submissions. Otherwise must contain key/value pairs:
	 *   - 'verified'  if set, only list submissions that are/are not verified, depending on the value
	 *   - 'judged'    if set, only list submissions with completed/not completed judgings, depending on the value
	 *   - 'team', 'problem', 'language', 'category', 'judgehost', 'rejudging' can be
	 *     set to an entity or ID to filter on that respective team, language, etc.
	 *   - 'rejudgingdiff' if true, only show submissions with a different result. If false, only
	 *     show submissions with the same result. 'rejudging' must be set for this
	 *   - 'old_result' if set show only submissions with this as the old result. 'rejudging' must
	 *     be set for this
	 *   - 'result' if set show only submissions with this as the result
	 * @param int|null $limit
	 *   If not null, only retrieve the given number of restrictions
	 * @return Submission[]
	 *   The filtered submissions
	 */
	public function getSubmissions($contests, $restrictions = array(), $limit = null)
	{
		$qb = $this->getSubmissionsBaseBuilder($contests, $restrictions);

		$qb->select('s, partial j.{judgingid,result,verified,juryMember}, p, cp, l');

		if ( $limit !== null ) {
			$qb->setMaxResults($limit);
		}

		$query = $qb->getQuery();

		return $query->getResult();
	}

	/**
	 * Get the old judgings for a rejudging and an optional set of submissions.
	 *
	 * @param Rejudging $rejudging
	 *   The rejudging to get the old results for
	 * @param Submission[]|null $submissions
	 *   The submissions to get the old judgings for or null to get all old results for the given
	 *   rejudging
	 *
	 * @return Judging[]
	 *   The old judgings for the passed submissions. Indexed on submission ID
	 */
	public function getOldJudgings(Rejudging $rejudging, $submissions = null)
	{
		$q = "
			SELECT jold
			FROM DOMjudgeMainBundle:Submission s
			LEFT JOIN DOMjudgeMainBundle:Judging j
				WITH j.rejudging = :rejudging
			LEFT JOIN DOMjudgeMainBundle:Judging jold
				WITH j.previousJudging IS NULL
					AND s = jold.submission
					AND jold.valid = 1
					OR j.previousJudging = jold.judgingid
		WHERE (s.rejudging = :rejudging OR j.rejudging = :rejudging)";
		if ( $submissions !== null ) {
			$q .= " AND s.submitid IN (:submissions)";
		}
		$query = $this->entityManager->createQuery($q);

		$query->setParameter('rejudging', $rejudging);

		if ( $submissions !== null ) {
			$query->setParameter('submissions', $submissions);
		}

		/** @var Judging[] $judgings */
		$judgings = $query->getResult();

		$result = array();
		foreach ( $judgings as $judging ) {
			$result[$judging->getSubmission()->getSubmitid()] = $judging;
		}

		return $result;
	}

	public function getSubmissionCount($contests, $restrictions = array())
	{
		$qb = $this->getSubmissionsBaseBuilder($contests, $restrictions);

		$qb->select('COUNT(s)');

		$query = $qb->getQuery();

		return (int)$query->getSingleScalarResult();
	}

	public function getCorrectSubmissionCount($contests, $restrictions = array())
	{
		$qb = $this->getSubmissionsBaseBuilder($contests, $restrictions);

		$qb
			->select('COUNT(s)')
			->andWhere('j.result LIKE \'correct\'');

		$query = $qb->getQuery();

		return (int)$query->getSingleScalarResult();
	}

	public function getUnverifiedSubmissionCount($contests, $restrictions = array())
	{
		$qb = $this->getSubmissionsBaseBuilder($contests, $restrictions);

		$qb
			->select('COUNT(s)')
			->andWhere('j.verified = 0')
			->andWhere('j.result IS NOT NULL');

		$query = $qb->getQuery();

		return (int)$query->getSingleScalarResult();
	}

	public function getIgnoredSubmissionCount($contests, $restrictions = array())
	{
		$qb = $this->getSubmissionsBaseBuilder($contests, $restrictions);

		$qb
			->select('COUNT(s)')
			->andWhere('s.valid = 0');

		$query = $qb->getQuery();

		return (int)$query->getSingleScalarResult();
	}

	public function getQueuedSubmissionCount($contests, $restrictions = array())
	{
		$qb = $this->getSubmissionsBaseBuilder($contests, $restrictions);

		$qb
			->select('COUNT(s)')
			->andWhere('j.result IS NULL');

		$query = $qb->getQuery();

		return (int)$query->getSingleScalarResult();
	}

	/**
	 * Get the base submission query builder for the given restrictions
	 *
	 * @param Contest[] $contests
	 *   Get all submissions for the given contests
	 * @param array $restrictions
	 *   If empty, do not restrict submissions. Otherwise must contain key/value pairs:
	 *   - 'verified'  if set, only list submissions that are/are not verified, depending on the value
	 *   - 'judged'    if set, only list submissions with completed/not completed judgings, depending on the value
	 *   - 'team', 'problem', 'language', 'category', 'judgehost', 'rejudging' can be
	 *     set to an entity or ID to filter on that respective team, language, etc.
	 *   - 'rejudgingdiff' if true, only show submissions with a different result. If false, only
	 *     show submissions with the same result. 'rejudging' must be set for this
	 *   - 'old_result' if set show only submissions with this as the old result. 'rejudging' must
	 *     be set for this
	 *   - 'result' if set show only submissions with this as the result
	 * @return \Doctrine\ORM\QueryBuilder
	 *   A query builder to use as a basis for other functions in this service
	 */
	private function getSubmissionsBaseBuilder($contests, $restrictions = array())
	{
		if ( isset($restrictions['rejudgingdiff']) && !isset($restrictions['rejudging']) ) {
			throw new \InvalidArgumentException("Rejudgingdiff set but no rejudging given");
		}
		if ( isset($restrictions['old_result']) && !isset($restrictions['rejudging']) ) {
			throw new \InvalidArgumentException("old_result set but no rejudging given");
		}

		$qb = $this->entityManager->createQueryBuilder();
		$qb
			->from('DOMjudgeMainBundle:Submission', 's')
			->innerJoin('s.team', 't')
			->innerJoin('s.problem', 'p')
			->innerJoin('p.contestProblems', 'cp', Expr\Join::WITH, 'cp.contest = s.contest')
			->innerJoin('s.language', 'l')
			->where('s.contest IN (:contests)')
			->setParameter('contests', $contests);

		// If rejudging is set, we need to also load the old judgings
		if ( isset($restrictions['rejudging']) ) {
			$qb
				->leftJoin('s.judgings', 'j', Expr\Join::WITH, 'j.rejudging = :rejudging')
				// We need to join on the type, as we need a custom ON
				->leftJoin('DOMjudgeMainBundle:Judging', 'jold', Expr\Join::WITH,
				           'j.previousJudging IS NULL AND s = jold.submission AND jold.valid = 1 OR j.previousJudging = jold.judgingid')
				->andWhere($qb->expr()->orX(
					$qb->expr()->eq('s.rejudging', ':rejudging'),
					$qb->expr()->eq('j.rejudging', ':rejudging')
				))
				->setParameter('rejudging', $restrictions['rejudging']);
		} else {
			$qb->leftJoin('s.judgings', 'j', Expr\Join::WITH, 'j.valid = 1');
		}

		// Filter on verified stauts
		if ( isset($restrictions['verified']) ) {
			if ( $restrictions['verified'] ) {
				$qb->andWhere('j.verified = 1');
			} else {
				$qb->andWhere(
					$qb->expr()->orx(
						$qb->expr()->eq('j.verified', 0),
						$qb->expr()->andX(
							$qb->expr()->isNull('j.verified'),
							$qb->expr()->isNull('s.judgehost')
						)
					)
				);
			}
		}

		// Filter on judges status
		if ( isset($restrictions['judged']) ) {
			if ( $restrictions['judged'] ) {
				$qb->andWhere('j.result IS NOT NULL');
			} else {
				$qb->andWhere('j.result IS NULL');
			}
		}

		// Filter on rejudging diff
		if ( isset($restrictions['rejudgingdiff']) ) {
			if ( $restrictions['rejudgingdiff'] ) {
				$qb->andWhere('j.result != jold.result');
			} else {
				$qb->andWhere('j.result = jold.result');
			}
		}

		// Filter on team
		if ( isset($restrictions['team']) ) {
			$qb
				->andWhere('s.team = :team')
				->setParameter('team', $restrictions['team']);
		}

		// Filter on team
		if ( isset($restrictions['category']) ) {
			$qb
				->andWhere('t.category = :category')
				->setParameter('category', $restrictions['category']);
		}

		// Filter on problem
		if ( isset($restrictions['problem']) ) {
			$qb
				->andWhere('s.problem = :problem')
				->setParameter('problem', $restrictions['problem']);
		}

		// Filter on language
		if ( isset($restrictions['language']) ) {
			$qb
				->andWhere('s.language = :language')
				->setParameter('language', $restrictions['language']);
		}

		// Filter on judgehost
		if ( isset($restrictions['judgehost']) ) {
			$qb
				->andWhere('s.judgehost = :judgehost')
				->setParameter('judgehost', $restrictions['judgehost']);
		}

		// Filter on judgehost
		if ( isset($restrictions['judgehost']) ) {
			$qb
				->andWhere('s.judgehost = :judgehost')
				->setParameter('judgehost', $restrictions['judgehost']);
		}

		// Filter on old result
		if ( isset($restrictions['old_result']) ) {
			$qb
				->andWhere('jold.result = :oldresult')
				->setParameter('oldresult', $restrictions['old_result']);
		}

		// Filter on result
		if ( isset($restrictions['result']) ) {
			$qb
				->andWhere('j.result = :result')
				->setParameter('result', $restrictions['result']);
		}

		return $qb;
	}
}
