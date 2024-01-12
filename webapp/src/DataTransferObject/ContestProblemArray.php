<?php declare(strict_types=1);

namespace App\DataTransferObject;

/**
 * This class is used to output a list of problems
 */
class ContestProblemArray
{
    /** @var OrdinalContestProblemWrapper[] */
    protected array $items;

    /**
     * @param ContestProblemWrapper[] $items
     */
    public function __construct(array $items)
    {
        $this->items = [];
        $ordinal = 0;
        foreach ($items as $item) {
            $this->items[] = new OrdinalContestProblemWrapper($ordinal, $item);
            $ordinal++;
        }
    }

    /**
     * @return OrdinalContestProblemWrapper[]
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
