<?php declare(strict_types=1);

namespace App\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Country extends Constraint
{
    public function __construct(
        public string $message = 'Only (uppercase) ISO3166-1 alpha-3 values are allowed'
    ) {
        parent::__construct();
    }
}
