<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\TeamCategory;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<TeamCategory>
 */
class TeamCategoryRepository extends EntityRepository
{
    /**
     * @use FindByExternalidTrait<TeamCategory>
     */
    use FindByExternalidTrait;
}
