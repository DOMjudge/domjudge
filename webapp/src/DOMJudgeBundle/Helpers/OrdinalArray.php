<?php declare(strict_types=1);

namespace DOMJudgeBundle\Helpers;

/**
 * Class OrdinalArray
 *
 * This class is used to output an ordinal list
 *
 * @package DOMJudgeBundle\Serializer
 */
class OrdinalArray
{
    /**
     * @var OrdinalItem[]
     */
    protected $items;

    /**
     * OrdinalArray constructor.
     * @param array|\Traversable $items
     */
    public function __construct($items)
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
