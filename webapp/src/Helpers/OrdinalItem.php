<?php declare(strict_types=1);

namespace App\Helpers;

use JMS\Serializer\Annotation as Serializer;

/**
 * This class is used to output an ordinal item.
 */
class OrdinalItem
{
    public function __construct(
        #[Serializer\SerializedName('ordinal')]
        protected readonly int $ordinal,
        #[Serializer\Inline]
        protected readonly object $item
    ) {}

    public function getItem(): object
    {
        return $this->item;
    }
}
