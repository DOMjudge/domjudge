<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Problem;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<ContestProblem>
 */
class ContestProblemRepository extends EntityRepository
{
    public function findByProblemAndContest(Contest|string $contest, Problem|string $problem): ?ContestProblem
    {
        if ($contest instanceof Contest) {
            $contest = $contest->getExternalid();
        }
        if ($problem instanceof Problem) {
            $problem = $problem->getExternalid();
        }
        return $this->createQueryBuilder('cp')
            ->innerJoin('cp.contest', 'c')
            ->innerJoin('cp.problem', 'p')
            ->andWhere('c.externalid = :contestId')
            ->andWhere('p.externalid = :problemId')
            ->setParameter('contestId', $contest)
            ->setParameter('problemId', $problem)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
