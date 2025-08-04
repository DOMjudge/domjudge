<?php declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class TimeStringValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        $timezoneRegex = "[A-Za-z][A-Za-z0-9_\/+-]{1,35}"; # See: https://en.wikipedia.org/wiki/List_of_tz_database_time_zones
        $offsetRegex   = "[+-]\d{1,2}(:\d\d)?";
        $absoluteRegex = "\d\d\d\d-\d\d-\d\d( |T)\d\d:\d\d:\d\d(\.\d{1,6})?( " . $timezoneRegex . "|" . $offsetRegex . "|Z)";
        $relativeRegex = "\d+:\d\d(:\d\d(\.\d{1,6})?)?";
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
                "/^(" . $absoluteRegex . "|\+?" . $relativeRegex . ")$/" :
                "/^(" . $absoluteRegex . "|-"   . $relativeRegex . ")$/";
            $message = $constraint->absoluteRelativeMessage;
        } else {
            $regex   = "/^" . $absoluteRegex . "$/";
            $message = $constraint->absoluteMessage;
        }
        if (preg_match($regex, $value) !== 1) {
            $this->context->buildViolation($message)->addViolation();
        }
    }
}
