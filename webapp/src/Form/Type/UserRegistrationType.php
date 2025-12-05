<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Role;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Entity\User;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Intl\Countries;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContext;

class UserRegistrationType extends AbstractType
{
    public function __construct(
        protected readonly DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly EntityManagerInterface $em,
        #[Autowire(param: 'min_password_length')]
        private readonly int $minimumPasswordLength,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Username',
                    'autocomplete' => 'username',
                ],
            ])
            ->add('name', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'Full name (optional)',
                    'autocomplete' => 'name',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'Email address (optional)',
                    'autocomplete' => 'email',
                ],
                'constraints' => new Email(),
            ])
            ->add('teamName', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Team name',
                ],
                'constraints' => [
                    new NotBlank(),
                    new Callback(function ($teamName, ExecutionContext $context): void {
                        if ($this->em->getRepository(Team::class)->findOneBy(['name' => $teamName])) {
                            $context->buildViolation('This team name is already in use.')
                                ->addViolation();
                        }
                    }),
                ],
                'mapped' => false,
            ]);

        $selfRegistrationCategoriesCount = $this->em->getRepository(TeamCategory::class)->count(['allow_self_registration' => 1]);
        if ($selfRegistrationCategoriesCount > 1) {
            $builder
                ->add('teamCategory', EntityType::class, [
                    'class' => TeamCategory::class,
                    'label' => false,
                    'mapped' => false,
                    'choice_label' => 'name',
                    'placeholder' => '-- Select category --',
                    'query_builder' => fn(EntityRepository $er) => $er
                        ->createQueryBuilder('c')
                        ->where('c.allow_self_registration = 1')
                        ->orderBy('c.sortorder'),
                    'attr' => [
                        'placeholder' => 'Category',
                    ],
                    'constraints' => [
                        new NotBlank(),
                    ],
                ]);
        }

        if ($this->config->get('show_affiliations')) {
            $countries = [];
            foreach (Countries::getAlpha3Codes() as $alpha3) {
                $name = Countries::getAlpha3Name($alpha3);
                $countries["$name ($alpha3)"] = $alpha3;
            }

            $builder
                ->add('affiliation', ChoiceType::class, [
                    'choices' => [
                        'Use existing affiliation' => 'existing',
                        'Add new affiliation' => 'new',
                        'No affiliation' => 'none',
                    ],
                    'expanded' => true,
                    'mapped' => false,
                    'label' => false,
                ])
                ->add('affiliationName', TextType::class, [
                    'label' => false,
                    'required' => false,
                    'attr' => [
                        'placeholder' => 'Affiliation name',
                    ],
                    'mapped' => false,
                ])
                ->add('affiliationShortName', TextType::class, [
                    'label' => false,
                    'required' => false,
                    'attr' => [
                        'placeholder' => 'Affiliation shortname',
                        'maxlength' => '32',
                    ],
                    'mapped' => false,
                ]);
            if ($this->config->get('show_flags')) {
                $builder->add('affiliationCountry', ChoiceType::class, [
                    'label' => false,
                    'required' => false,
                    'mapped' => false,
                    'choices' => $countries,
                    'placeholder' => 'No country',
                ]);
            }
            $builder->add('existingAffiliation', EntityType::class, [
                    'class' => TeamAffiliation::class,
                    'label' => false,
                    'required' => false,
                    'mapped' => false,
                    'choice_label' => 'name',
                    'placeholder' => '-- Select affiliation --',
                    'query_builder' => fn(EntityRepository $er) => $er
                        ->createQueryBuilder('a')
                        ->orderBy('a.name'),
                    'attr' => [
                        'placeholder' => 'Affiliation',
                    ],
                ]);
        }

        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'The password fields must match.',
                'first_options' => [
                    'label' => false,
                    'attr' => [
                        'placeholder' => 'Password',
                        'autocomplete' => 'new-password',
                        'spellcheck' => 'false',
                        'minlength' => $this->minimumPasswordLength,
                    ],
                    'help' => sprintf('Minimum length: %d characters', $this->minimumPasswordLength),
                ],
                'second_options' => [
                    'label' => false,
                    'attr' => [
                        'placeholder' => 'Repeat Password',
                        'autocomplete' => 'new-password',
                        'spellcheck' => 'false',
                        'minlength' => $this->minimumPasswordLength,
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

        // Make sure the user has the team role to make validation work
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            /** @var User $user */
            $user = $event->getData();
            /** @var Role $role */
            $role = $this->em->createQueryBuilder()
                ->from(Role::class, 'r')
                ->select('r')
                ->andWhere('r.dj_role = :team')
                ->setParameter('team', 'team')
                ->getQuery()
                ->getOneOrNullResult();
            $user->addUserRole($role);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $validateAffiliation = function ($data, ExecutionContext $context): void {
            if ($this->config->get('show_affiliations')) {
                /** @var Form $form */
                $form = $context->getRoot();
                switch ($form->get('affiliation')->getData()) {
                    case 'new':
                        foreach (['Name','ShortName'] as $identifier) {
                            $name = $form->get('affiliation'.$identifier)->getData();
                            if (empty($name)) {
                                $context->buildViolation('This value should not be blank.')
                                    ->atPath('affiliation'.$identifier)
                                    ->addViolation();
                            }
                            if ($this->em->getRepository(TeamAffiliation::class)->findOneBy([strtolower($identifier) => $name])) {
                                $context->buildViolation('This affiliation '.strtolower($identifier).' is already in use.')
                                    ->atPath('affiliation'.$identifier)
                                    ->addViolation();
                            }
                        }
                        break;
                    case 'existing':
                        if (empty($form->get('existingAffiliation')->getData())) {
                            $context->buildViolation('This value should not be blank.')
                                ->atPath('existingAffiliation')
                                ->addViolation();
                        }
                        break;
                }
            }
        };
        $resolver->setDefaults(
            [
                'data_class' => User::class,
                'constraints' => new Callback($validateAffiliation)
            ]
        );
    }
}
