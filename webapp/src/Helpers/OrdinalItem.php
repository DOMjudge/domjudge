<?php declare(strict_types=1);

namespace App\Helpers;

use JMS\Serializer\Annotation as Serializer;

/**
 * Class OrdinalItem
 *
 * This class is used to output an ordinal item.
 *
 * @package App\Serializer
 */
class OrdinalItem
{
    /** @Serializer\SerializedName("ordinal") */
    protected int $ordinal;

    /** @Serializer\Inline() */
    protected object $item;

    public function __construct(int $ordinal, object $item)
    {
        $this->ordinal = $ordinal;
        $this->item    = $item;
    }

    public function getItem(): object
    {
        return $this->item;
    }
}
