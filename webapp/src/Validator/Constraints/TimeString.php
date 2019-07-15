<?php declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class TimeString extends Constraint
{
    public $allowRelative = true;
    public $relativeIsPositive = true;

    public $absoluteMessage = 'Only absolute time strings are allowed';
    public $absoluteRelativeMessage = 'Only absolute or relative time strings are allowed';
}
