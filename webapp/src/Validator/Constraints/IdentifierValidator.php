<?php declare(strict_types=1);

namespace App\Validator\Constraints;

use App\Service\DOMJudgeService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class IdentifierValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof Identifier) {
            throw new UnexpectedTypeException($constraint, Identifier::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        if (preg_match(DOMJudgeService::EXTERNAL_IDENTIFIER_REGEX, $value) !== 1) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
