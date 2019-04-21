<?php declare(strict_types=1);
namespace DOMJudgeBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Doctrine\ORM\EntityManagerInterface;

class UserRegistrationValidator extends ConstraintValidator
{
    protected $em;
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }
    public function validate($value, Constraint $constraint)
    {
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
