<?php declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class TimeStringValidator extends ConstraintValidator
{
    /**
     * @inheritdoc
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof TimeString) {
            throw new UnexpectedTypeException($constraint, TimeString::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        if ($constraint->allowRelative) {
            $regex   = $constraint->relativeIsPositive ?
                "/^(\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d(\.\d{1,6})? [A-Za-z][A-Za-z0-9_\/+-]{1,35}|\+\d{1,4}:\d\d(:\d\d(\.\d{1,6})?)?)$/" :
                "/^(\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d(\.\d{1,6})? [A-Za-z][A-Za-z0-9_\/+-]{1,35}|-\d{1,4}:\d\d(:\d\d(\.\d{1,6})?)?)$/";
            $message = $constraint->absoluteRelativeMessage;
        } else {
            $regex   = "/^\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d(\.\d{1,6})? [A-Za-z][A-Za-z0-9_\/+-]{1,35}$/";
            $message = $constraint->absoluteMessage;
        }
        if (preg_match($regex, $value) !== 1) {
            $this->context->buildViolation($message)->addViolation();
        }
    }
}
