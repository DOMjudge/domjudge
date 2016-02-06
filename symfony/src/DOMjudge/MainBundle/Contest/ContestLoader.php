<?php

namespace DOMjudge\MainBundle\Contest;

use Doctrine\ORM\EntityManager;
use DOMjudge\MainBundle\Entity\Team;
use DOMjudge\MainBundle\Entity\Contest;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Doctrine\ORM\Query\Expr;

class ContestLoader
{
	/**
	 * @var EntityManager
	 */
	private $entityManager;
	/**
	 * @var SessionInterface
	 */
	private $session;

	public function __construct(EntityManager $entityManager, SessionInterface $session)
	{
		$this->entityManager = $entityManager;
		$this->session = $session;
	}

	/**
	 * Get active contests
	 *
	 * @param string|null $indexBy
	 *   If given, index by the given field. Should start with "c.". If not given, use increasing
	 *   numeric indices
	 * @param bool $onlyPublic
	 *   Whether to only get public contests
	 * @param Team|null $onlyOfTeam
	 *   Whether to only get contests with the given team. This will also return public contests, as
	 *   all teams are part of them
	 * @param bool $alsoFuture
	 *   Whether to also get contests that will be active in the future
	 * @return Contest[]
	 *   All the active contests for the given parameters
	 */
	public function getActiveContests($indexBy = null, $onlyPublic = false, Team $onlyOfTeam = null,
	                                  $alsoFuture = false)
	{
		$qb = $this->entityManager->createQueryBuilder();
		$qb
			->select('c')
			->from('DOMjudgeMainBundle:Contest', 'c', $indexBy)
			->andWhere('c.enabled = 1')
			->andWhere(
				$qb->expr()->orx(
					$qb->expr()->isNull('c.deactivateTime'),
					$qb->expr()->gt('c.deactivateTime', ':now')
				)
			)
			->orderBy('c.activateTime')
			->setParameter('now', microtime(true));

		if ( !$alsoFuture ) {
			$qb->andWhere('c.activateTime <= :now');
		}
		if ( $onlyPublic ) {
			$qb->andWhere('c.public = 1');
		}
		if ( $onlyOfTeam !== null ) {
			$qb
				->leftJoin('c.teams', 't')
				->andWhere(
					$qb->expr()->orx(
						$qb->expr()->eq('t.teamid', ':team'),
						$qb->expr()->eq('c.public', 1)
					)
				)
				->setParameter('team', $onlyOfTeam->getTeamid());
		}

		$query = $qb->getQuery();

		return $query->getResult();
	}

	/**
	 * Get the currently selected contest
	 * @param bool $onlyPublic
	 *   Whether to only check public contests
	 * @param Team|null $onlyOfTeam
	 *   Whether to only check contests with the given team. This will also check public contests, as
	 *   all teams are part of them
	 * @param bool $alsoFuture
	 *   Whether to also check contests that will be active in the future
	 * @return Contest|null
	 *   The currently active contest or null if none is active
	 */
	public function getCurrentContest($onlyPublic = false, Team $onlyOfTeam = null, $alsoFuture = false)
	{
		$contests = $this->getActiveContests('c.cid', $onlyPublic, $onlyOfTeam, $alsoFuture);

		if ( $this->session->has('current_contest_id') ) {
			$current_contest_id = $this->session->get('current_contest_id');
			if ( isset($contests[$current_contest_id]) ) {
				return $contests[$current_contest_id];
			} elseif ($current_contest_id == -1) {
				return null;
			}
			// Else: contest is not -1 (i.e. a contest is selected) but not found, default to first
			// found one
		}

		if ( count($contests) >= 1 ) {
			$contest_ids = array_keys($contests);
			return $contests[$contest_ids[0]];
		}

		// No active contests
		return null;
	}

	/**
	 * Set the currently active contest
	 *
	 * @param Contest|null $contest
	 *   The contest to make active for the current session or null to make no contest active
	 */
	public function setCurrentContest(Contest $contest = null)
	{
		if ( $contest === null ) {
			$this->session->set('current_contest_id', -1);
		} else {
			$this->session->set('current_contest_id', $contest->getCid());
		}
	}
}
