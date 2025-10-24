<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contest;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<Contest>
 */
class ContestRepository extends EntityRepository
{
    /**
     * @use FindByExternalidTrait<Contest>
     */
    use FindByExternalidTrait;
}
