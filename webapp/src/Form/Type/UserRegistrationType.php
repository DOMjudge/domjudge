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
use Ramsey\Uuid\Uuid;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
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
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected EntityManagerInterface $em;

    public function __construct(
        DOMJudgeService $dj,
        ConfigurationService $config,
        EntityManagerInterface $em
    ) {
        $this->dj     = $dj;
        $this->config = $config;
        $this->em     = $em;
    }

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
                'attr' => [
                    'placeholder' => 'Full name',
                    'autocomplete' => 'name',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Email address',
                    'autocomplete' => 'email',
                ],
                'constraints' => new Email(),
            ]);

        if($this->config->get('allow_custom_team_name_registration')) {
            $builder
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
                                    ->addViolation();
                            }
                        }),
                    ],
                    'mapped' => false,
                ]);
        }

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

            $affiliationChoices = [
                'Use existing school' => 'existing'
            ];

            if ($this->config->get('allow_create_affiliation_during_registration')) {
                $affiliationChoices['Add new school'] = 'new';
            }

            if ($this->config->get('allow_registration_without_affiliation')) {
                $affiliationChoices['No school'] = 'none';
            }

            $builder
                ->add('affiliation', ChoiceType::class, [
                    'choices' => $affiliationChoices,
                    'expanded' => true,
                    'mapped' => false,
                    'label' => false,
                ])
                ->add('affiliationName', TextType::class, [
                    'label' => false,
                    'required' => false,
                    'attr' => [
                        'placeholder' => 'School name',
                    ],
                    'mapped' => false,
                ])
                ->add('affiliationShortName', HiddenType::class, [
                    'label' => false,
                    'required' => false,
                    'empty_data' => Uuid::uuid4()->toString(),
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
                    'placeholder' => '-- Select school --',
                    'attr' => [
                        'placeholder' => 'School',
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

        // Make sure the user has the team role to make validation work
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
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
        $validateAffiliation = function ($data, ExecutionContext $context) {
            if ($this->config->get('show_affiliations')) {
                /** @var Form $form */
                $form = $context->getRoot();
                switch ($form->get('affiliation')->getData()) {
                    case 'new':
                        foreach(['Name','ShortName'] as $identifier) {
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
