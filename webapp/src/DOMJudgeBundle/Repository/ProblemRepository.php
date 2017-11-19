<?php

namespace DOMJudgeBundle\Repository;

use Doctrine\ORM\EntityRepository;
use DOMJudgeBundle\Entity\Contest;

class ProblemRepository extends EntityRepository {
	public function findAllForContest(Contest $contest) {
		return $this->createQueryBuilder('p')
			->join('p.contest_problems', 'cp')
			->join('p.testcases', 'tc')
			->select('p', 'cp', 'tc')
			->where('cp.cid = :cid')
			->andWhere('cp.allow_submit = true')
			->setParameter(':cid', $contest->getCid())
			->orderBy('cp.shortname')
			->getQuery()
			->getResult();
	}

	// This repository has no method to get a signel result, because the API entity paramconverter can't use it, because we need the ordinal field
}
