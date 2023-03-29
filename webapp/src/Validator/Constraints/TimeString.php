<?php declare(strict_types=1);

namespace App\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY)]
class TimeString extends Constraint
{
    public function __construct(
        public bool $allowRelative = true,
        public bool $relativeIsPositive = true,
        public string $absoluteMessage = 'Only absolute time strings are allowed',
        public string $absoluteRelativeMessage = 'Only absolute or relative time strings are allowed',
    ) {
        parent::__construct();
    }
}
