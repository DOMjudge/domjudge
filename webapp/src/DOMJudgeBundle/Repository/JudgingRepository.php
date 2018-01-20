<?php

namespace DOMJudgeBundle\Repository;

use Doctrine\ORM\EntityRepository;
use DOMJudgeBundle\Entity\Contest;

class JudgingRepository extends EntityRepository {
	protected function queryBuilderForContest(Contest $contest) {
		return $this->createQueryBuilder('j')
			->join('j.contest', 'c')
			->select('j', 'c', 'r', 'MAX(r.runtime) AS maxruntime')
			->leftJoin('j.runs', 'r')
			->where('j.cid = :cid')
			->setParameter(':cid', $contest)
			->groupBy('j.judgingid')
			->orderBy('j.judgingid');
	}

	public function findAllForContest(Contest $contest) {
		return $this->queryBuilderForContest($contest)
			->getQuery()
			->getResult();
	}

	public function findOneForContest(Contest $contest, $id) {
		return $this->queryBuilderForContest($contest)
			->andWhere('j.judgingid = :id')
			->setParameter(':id', $id)
			->getQuery()
			->getSingleResult();
	}
}
