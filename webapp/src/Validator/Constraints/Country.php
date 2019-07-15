<?php declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Country extends Constraint
{
    public $message = 'Only (uppercase) ISO3166-1 alpha-3 values are allowed';
}
