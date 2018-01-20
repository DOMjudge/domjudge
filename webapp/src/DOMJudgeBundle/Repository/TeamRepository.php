<?php

namespace DOMJudgeBundle\Repository;

use Doctrine\ORM\EntityRepository;
use DOMJudgeBundle\Entity\Contest;

class TeamRepository extends EntityRepository {

	protected function queryBuilderForContest(Contest $contest, $onlyPublic = false) {
		$qb = $this->createQueryBuilder('t')
			->leftJoin('t.affiliation', 'a')
			->join('t.category', 'c')
			->select('t', 'a', 'c')
			->where('t.enabled = true');

		if (!$contest->getPublic()) {
			$qb = $qb
				->join('t.contests', 'tc')
				->where('tc.cid = :cid')
				->setParameter(':cid', $contest->getCid());
		}

		if ($onlyPublic) {
			$qb = $qb->andWhere('c.visible = 1');
		}

		return $qb;
	}

	public function findAllForContest(Contest $contest, $onlyPublic = false) {
		return $this->queryBuilderForContest($contest, $onlyPublic)->getQuery()->getResult();
	}

	public function findForContest(Contest $contest, $id, $useExternalId, $onlyPublic = false) {
		$qb = $this->queryBuilderForContest($contest, $onlyPublic);

		if ($useExternalId) {
			$qb->andWhere('t.externalid = :id');
		} else {
			$qb->andWhere('t.teamid = :id');
		}
		$qb->setParameter(':id', $id);

		return $qb->getQuery()->getSingleResult();
	}
}
