<?php declare(strict_types=1);

namespace DOMJudgeBundle\Helpers;

use JMS\Serializer\Annotation as Serializer;

/**
 * Class OrdinalItem
 *
 * This class is used to output an ordinal item
 *
 * @package DOMJudgeBundle\Serializer
 */
class OrdinalItem
{
    /**
     * @var int
     * @Serializer\SerializedName("ordinal")
     */
    protected $ordinal;

    /**
     * @var object
     * @Serializer\Inline()
     */
    protected $item;

    /**
     * OrdinalItem constructor.
     * @param int $ordinal
     * @param object $item
     */
    public function __construct(int $ordinal, $item)
    {
        $this->ordinal = $ordinal;
        $this->item    = $item;
    }

    /**
     * @return object
     */
    public function getItem()
    {
        return $this->item;
    }
}
