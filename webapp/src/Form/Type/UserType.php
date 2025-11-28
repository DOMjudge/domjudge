<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Role;
use App\Entity\Team;
use App\Entity\User;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractExternalIdEntityType
{
    public function __construct(
        protected readonly EntityManagerInterface $em,
        EventLogService $eventLogService,
        #[Autowire(param: 'min_password_length')]
        private readonly int $minimumPasswordLength,
    ) {
        parent::__construct($eventLogService);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addExternalIdField($builder, Team::class);
        /** @var Team[] $teams */
        $teams = $this->em->createQueryBuilder()
            ->from(Team::class, 't', 't.teamid')
            ->select('t')
            ->getQuery()
            ->getResult();
        uasort(
            $teams,
            static fn(Team $a, Team $b) => $a->getEffectiveName() <=> $b->getEffectiveName()
        );

        $builder->add('username', TextType::class, [
            'empty_data' => ''
        ]);
        $builder->add('name', TextType::class, [
            'label' => 'Full name',
            'required' => false,
            'help' => 'Optional full name for the user.',
            'empty_data' => ''
        ]);
        $builder->add('email', EmailType::class, [
            'required' => false,
            'attr' => [
                'autocomplete' => 'user-email',
            ],
        ]);
        $builder->add('plainPassword', PasswordType::class, [
            'required' => false,
            'label' => 'Password',
        ]);
        $builder->add('ipAddress', TextType::class, [
            'required' => false,
            'label' => 'IP address',
        ]);
        $builder->add('enabled', ChoiceType::class, [
            'expanded' => true,
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
        ]);
        $builder->add('team', ChoiceType::class, [
            'choice_label' => 'effective_name',
            'required' => false,
            'placeholder' => '-- no team --',
            'choices' => $teams,
        ]);
        $builder->add('user_roles', EntityType::class, [
            'label' => 'Roles',
            'class' => Role::class,
            'choice_label' => 'description',
            'required' => false,
            'multiple' => true,
            'expanded' => true,
        ]);
        $builder->add('save', SubmitType::class);

        // Remove ID field when doing an edit
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            /** @var User|null $user */
            $user = $event->getData();
            $form = $event->getForm();

            if ($user && $user->getUserid() !== null) {
                $form->remove('username');
            }

            $set = $user->getPassword() ? 'set' : 'not set';
            $form->add('plainPassword', PasswordType::class, [
                'required' => false,
                'label' => 'Password',
                'help' => sprintf('Currently %s - fill to change. Any current login session of the user will be terminated. Minimum length: %d characters', $set, $this->minimumPasswordLength),
                'attr' => [
                    'autocomplete' => 'new-password',
                    'minlength' => $this->minimumPasswordLength,
                ],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
