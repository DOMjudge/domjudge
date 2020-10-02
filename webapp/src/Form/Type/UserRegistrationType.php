<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Role;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Entity\User;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use stdClass;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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
    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var ConfigurationService
     */
    protected $config;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * UserRegistrationType constructor.
     *
     * @param DOMJudgeService        $dj
     * @param ConfigurationService   $config
     * @param EntityManagerInterface $em
     */
    public function __construct(
        DOMJudgeService $dj,
        ConfigurationService $config,
        EntityManagerInterface $em
    ) {
        $this->dj     = $dj;
        $this->config = $config;
        $this->em     = $em;
    }

    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
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
            ->add('team', ChoiceType::class, [
                'choices' => [                    
                    'Join existing team' => 'existing',
                    'Create new team' => 'new',
                ],
                'expanded' => true,
                'mapped' => false,
                'label' => false,
            ])
            ->add('existingTeam', EntityType::class, [
                'class' => Team::class,
                'label' => false,
                'required' => false,
                'mapped' => false,
                'choice_label' => 'name',
                'placeholder' => '-- Select team --',
                'query_builder' => function (EntityRepository $er) {
                    return $er
                        ->createQueryBuilder('t')
                        ->join('t.category', 'c')
                        ->where('c.allow_self_registration = 1')
                        ->orderBy('t.name');
                },
                'attr' => [
                    'placeholder' => 'Team',
                ],
            ])
            ->add('teamName', TextType::class, [
                'label' => false,
                'required' => false,                
                'attr' => [
                    'placeholder' => 'Team name',
                ],
                'mapped' => false,
            ]);

        $selfRegistrationCategoriesCount = $this->em->getRepository(TeamCategory::class)->count(['allow_self_registration' => 1]);
        if ($selfRegistrationCategoriesCount > 1) {
            $builder
                ->add('teamCategory', EntityType::class, [
                    'class' => TeamCategory::class,
                    'label' => false,
                    'required' => false,
                    'mapped' => false,
                    'choice_label' => 'name',
                    'placeholder' => '-- Select team category --',
                    'query_builder' => function (EntityRepository $er) {
                        return $er
                            ->createQueryBuilder('c')
                            ->where('c.allow_self_registration = 1')
                            ->orderBy('c.sortorder');
                    },
                    'attr' => [
                        'placeholder' => 'Category',
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
                        'No affiliation' => 'none',
                        'Add new affiliation' => 'new',
                        'Use existing affiliation' => 'existing',
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
                ->setParameter(':team', 'team')
                ->getQuery()
                ->getOneOrNullResult();
            $user->addUserRole($role);
        });

    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $validateTeam = function ($data, ExecutionContext $context) {
            /** @var Form $form */
            $form = $context->getRoot();
            switch ($form->get('team')->getData()) {
                case 'new':
                    $teamName = $form->get('teamName')->getData();
                    if (empty($teamName)) {
                        $context->buildViolation('This value should not be blank.')
                            ->atPath('teamName')
                            ->addViolation();
                    }
                    if ($this->em->getRepository(Team::class)->findOneBy(['name' => $teamName])) {
                        $context->buildViolation('This team name is already in use.')
                            ->atPath('teamName')
                            ->addViolation();
                    }                    
                    if ($this->em->getRepository(TeamCategory::class)->count(['allow_self_registration' => 1]) > 1 
                        && empty($form->get('teamCategory')->getData())) {
                            $context->buildViolation('This value should not be blank.')
                            ->atPath('teamCategory')
                            ->addViolation();
                    }
                    break;
                case 'existing':
                    if (empty($form->get('existingTeam')->getData())) {
                        $context->buildViolation('This value should not be blank.')
                            ->atPath('existingTeam')
                            ->addViolation();
                    }                    
                    break;
            }
        };

        $validateAffiliation = function ($data, ExecutionContext $context) {
            if ($this->config->get('show_affiliations')) {
                /** @var Form $form */
                $form = $context->getRoot();
                switch ($form->get('affiliation')->getData()) {
                    case 'new':
                        $affiliationName = $form->get('affiliationName')->getData();
                        if (empty($affiliationName)) {
                            $context->buildViolation('This value should not be blank.')
                                ->atPath('affiliationName')
                                ->addViolation();
                        }
                        if ($this->em->getRepository(TeamAffiliation::class)->findOneBy(['name' => $affiliationName])) {
                            $context->buildViolation('This affiliation name is already in use.')
                                ->atPath('affiliationName')
                                ->addViolation();
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
                'constraints' => [
                    new Callback($validateTeam),
                    new Callback($validateAffiliation),
                ],
            ]
        );
    }
}
