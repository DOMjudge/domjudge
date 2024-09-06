<?php declare(strict_types=1);

namespace App\Entity;

/**
 * Entities implementing this interface will get their external ID set to the
 * internal ID when they are persisted, unless an external ID has already been set.
 *
 * If an entity with that external ID already exists, an exception will be thrown and
 * the transaction will be rolled back.
 */
interface ExternalIdFromInternalIdInterface
{
    public function setExternalId(?string $externalid): self;
    public function getExternalId(): ?string;
}
