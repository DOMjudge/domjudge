<?php declare(strict_types=1);

namespace App\Entity;

/**
 * Interface for entities that have an external ID.
 */
interface HasExternalIdInterface
{
    public function getExternalId(): ?string;
}
