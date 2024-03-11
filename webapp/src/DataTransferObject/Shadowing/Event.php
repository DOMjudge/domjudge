<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

/**
 * @template-covariant T of EventData
 */
class Event
{
    /**
     * @param T[] $data
     */
    public function __construct(
        public readonly ?string $id,
        public readonly EventType $type,
        public readonly Operation $operation,
        public readonly ?string $objectId,
        public readonly array $data,
    ) {}
}
