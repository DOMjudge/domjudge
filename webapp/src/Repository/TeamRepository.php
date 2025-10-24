<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Team;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<Team>
 */
class TeamRepository extends EntityRepository
{
    /**
     * @use FindByExternalidTrait<Team>
     */
    use FindByExternalidTrait;
}
