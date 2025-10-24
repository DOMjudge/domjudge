<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Problem;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<Problem>
 */
class ProblemRepository extends EntityRepository
{
    /**
     * @use FindByExternalidTrait<Problem>
     */
    use FindByExternalidTrait;
}
