<?php declare(strict_types=1);

namespace DOMJudgeBundle\Form\Type;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Entity\User;
use DOMJudgeBundle\Validator\Constraints;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContext;

class UserRegistrationType extends AbstractType
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * UserRegistrationType constructor.
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('username', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Username',
                ],
            ])
            ->add('teamName', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Team name',
                ],
                'constraints' => [
                    new NotBlank(),
                    new Callback(function ($teamName, ExecutionContext $context) {
                        if ($this->em->getRepository(Team::class)->findOneBy(['name' => $teamName])) {
                            $context->buildViolation('This team name is already in use.')
                                ->atPath('teamName')
                                ->addViolation();
                        }
                    }),
                ],
                'mapped' => false,
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'The password fields must match.',
                'first_options' => [
                    'label' => false,
                    'attr' => [
                        'placeholder' => 'Password',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'second_options' => [
                    'label' => false,
                    'attr' => [
                        'placeholder' => 'Repeat Password',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'mapped' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Register',
                'attr' => [
                    'class' => 'btn btn-lg btn-primary btn-block',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
