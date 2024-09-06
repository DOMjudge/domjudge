<?php declare(strict_types=1);

namespace App\Entity;

/**
 * Interface for entities that have an external ID that is calculated from another field that
 * is not the internal ID.
 */
interface CalculatedExternalIdBasedOnRelatedFieldInterface
{
    public function getCalculatedExternalId(): string;
}
