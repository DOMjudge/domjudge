<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Language;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<Language>
 */
class LanguageRepository extends EntityRepository
{
    /**
     * @use FindByExternalidTrait<Language>
     */
    use FindByExternalidTrait;
}
