<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

/**
 * @template-covariant T of EventData
 */
readonly class Event
{
    /**
     * @param T[] $data
     */
    public function __construct(
        public ?string   $id,
        public EventType $type,
        public Operation $operation,
        public ?string   $objectId,
        public array     $data,
    ) {}
}
