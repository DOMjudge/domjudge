<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\HasExternalIdInterface as T;

/**
 * @template T of T
 */
trait FindByExternalidTrait
{
    /**
     * @return T|null
     */
    public function findByExternalId(string $externalId): ?T
    {
        return $this->findOneBy(['externalid' => $externalId]);
    }
}
