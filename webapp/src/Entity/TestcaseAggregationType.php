<?php declare(strict_types=1);

namespace App\Entity;

enum TestcaseAggregationType: string
{
    case SUM = 'sum';
    case MIN = 'min';
    case MAX = 'max';
    case AVG = 'avg';
}
