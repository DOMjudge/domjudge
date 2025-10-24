<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\TeamAffiliation;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<TeamAffiliation>
 */
class TeamAffiliationRepository extends EntityRepository
{
    /**
     * @use FindByExternalidTrait<TeamAffiliation>
     */
    use FindByExternalidTrait;
}
