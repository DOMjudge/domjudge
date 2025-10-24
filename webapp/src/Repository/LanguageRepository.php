<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Language;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<Language>
 */
class LanguageRepository extends EntityRepository
{
    public function findByExternalId(string $externalId): ?Language
    {
        return $this->findOneBy(['externalid' => $externalId]);
    }
}
