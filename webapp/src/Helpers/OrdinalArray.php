<?php declare(strict_types=1);

namespace App\Helpers;

use Traversable;

/**
 * This class is used to output an ordinal list.
 */
class OrdinalArray
{
    /** @var OrdinalItem[] */
    protected array $items;

    public function __construct(Traversable|array $items)
    {
        $this->items = [];
        $ordinal     = 0;
        foreach ($items as $item) {
            $this->items[] = new OrdinalItem($ordinal, $item);
            $ordinal++;
        }
    }

    /**
     * @return OrdinalItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
