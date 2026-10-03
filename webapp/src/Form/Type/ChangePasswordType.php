<?php declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChangePasswordType extends AbstractType
{
    public function __construct(
        #[Autowire(param: 'min_password_length')]
        private readonly int $minimumPasswordLength
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('currentPassword', PasswordType::class, [
            'label' => 'Current password',
            'mapped' => false,
            'constraints' => [
                new NotBlank(),
                new UserPassword(),
            ],
            'attr' => [
                'autocomplete' => 'current-password',
            ],
        ]);
        $builder->add('newPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'invalid_message' => 'The password fields must match.',
            'mapped' => false,
            'first_options'  => [
                'label' => 'New password',
                'help' => sprintf('Minimum length: %d characters', $this->minimumPasswordLength),
                'attr' => [
                    'autocomplete' => 'new-password',
                    'minlength' => $this->minimumPasswordLength,
                ],
            ],
            'second_options' => [
                'label' => 'Repeat new password',
                'attr' => [
                    'autocomplete' => 'new-password',
                    'minlength' => $this->minimumPasswordLength,
                ],
            ],
            'constraints' => [
                new NotBlank(),
                new Length([
                    'min' => $this->minimumPasswordLength,
                ]),
            ],
        ]);
        $builder->add('save', SubmitType::class, [
            'label' => 'Change password',
        ]);
    }
}
