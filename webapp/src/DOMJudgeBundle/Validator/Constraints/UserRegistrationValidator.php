<?php
namespace DOMJudgeBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Doctrine\ORM\EntityManager;

class UserRegistrationValidator extends ConstraintValidator
{
    protected $em;
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }
    public function validate($value, Constraint $constraint)
    {
        if (!ctype_alnum($value)) {
            $this->context->buildViolation("Username must consist of only alphanumeric characters")->addViolation();
        }
        $user = $this->em->getRepository('DOMJudgeBundle:User')->findOneByUsername($value);
        if ($user) {
            $this->context->buildViolation("User with that username already exists")->addViolation();
        }
        $team = $this->em->getRepository('DOMJudgeBundle:Team')->findOneByName($value);
        if ($team) {
            $this->context->buildViolation("Team with that name already exists")->addViolation();
        }
    }
}
