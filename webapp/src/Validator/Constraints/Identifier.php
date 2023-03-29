<?php declare(strict_types=1);

namespace App\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Identifier extends Constraint
{
    public function __construct(
        public string $message = 'Only alphanumeric characters and ._- are allowed',
    ) {
        parent::__construct();
    }
}
