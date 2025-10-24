<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Clarification;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<Clarification>
 */
class ClarificationRepository extends EntityRepository
{
    /**
     * @use FindByExternalidTrait<Clarification>
     */
    use FindByExternalidTrait;
}
