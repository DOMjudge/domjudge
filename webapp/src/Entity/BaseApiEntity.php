<?php declare(strict_types=1);

namespace App\Entity;

use App\Service\EventLogService;

/**
 * Class BaseApiEntity
 *
 * Base entity class that entities should use to support getting their API ID.
 *
 * @package App\Entity
 */
abstract class BaseApiEntity
{
    /**
     * Get the API ID field name for this entity.
     */
    public function getApiIdField(EventLogService $eventLogService): string
    {
        return $eventLogService->apiIdFieldForEntity($this);
    }

    /**
     * Get the API ID for this entity.
     */
    public function getApiId(EventLogService $eventLogService): string
    {
        $field = $eventLogService->apiIdFieldForEntity($this);
        $method = 'get'.ucfirst($field);
        return (string)$this->{$method}();
    }
}
