<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<User>
 */
class UserRepository extends EntityRepository
{
    /**
     * @use FindByExternalidTrait<User>
     */
    use FindByExternalidTrait;
}
