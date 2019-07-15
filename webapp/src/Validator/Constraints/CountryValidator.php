<?php declare(strict_types=1);

namespace App\Validator\Constraints;

use App\Utils\Utils;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class CountryValidator extends ConstraintValidator
{
    /**
     * @inheritdoc
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Country) {
            throw new UnexpectedTypeException($constraint, Country::class);
        }

        if (empty($value)) {
            return;
        }

        if (!key_exists(strtoupper($value), Utils::ALPHA3_COUNTRIES)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
