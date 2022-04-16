<?php declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Intl\Countries;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class CountryValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof Country) {
            throw new UnexpectedTypeException($constraint, Country::class);
        }

        if (empty($value)) {
            return;
        }

        if (!in_array(strtoupper($value), Countries::getAlpha3Codes())) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
