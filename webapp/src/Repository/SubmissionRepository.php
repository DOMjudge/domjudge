<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Submission;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<Submission>
 */
class SubmissionRepository extends EntityRepository
{
    /**
     * @use FindByExternalidTrait<Submission>
     */
    use FindByExternalidTrait;
}
