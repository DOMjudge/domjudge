<?php declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class TimeString extends Constraint
{
    public bool $allowRelative = true;
    public bool $relativeIsPositive = true;

    public string $absoluteMessage = 'Only absolute time strings are allowed';
    public string $absoluteRelativeMessage = 'Only absolute or relative time strings are allowed';
}
