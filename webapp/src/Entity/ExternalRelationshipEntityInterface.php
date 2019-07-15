<?php declare(strict_types=1);

namespace App\Entity;

/**
 * Interface ExternalRelationshipEntityInterface
 *
 * For entities implementing this interface, the SetExternalIdVisitor class will replace ID's
 * with external ID's for related entities if applicable
 * @package App\Controller\API
 */
interface ExternalRelationshipEntityInterface
{
    /**
     * Get the entities to check for external ID's while serializing.
     *
     * This method should return an array with as keys the JSON field names and as values the actual entity
     * objects that the SetExternalIdVisitor should check for applicable external ID's
     * @return array
     */
    public function getExternalRelationships(): array;
}
