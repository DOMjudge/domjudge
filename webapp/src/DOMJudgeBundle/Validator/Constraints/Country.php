<?php declare(strict_types=1);

namespace DOMJudgeBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Country extends Constraint
{
    public $message = 'Only ISO3166-1 alpha-3 values are allowed';
}
