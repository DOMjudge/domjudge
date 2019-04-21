<?php declare(strict_types=1);

namespace DOMJudgeBundle\Form\Type;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Entity\TeamAffiliation;
use DOMJudgeBundle\Entity\User;
use DOMJudgeBundle\Service\DOMJudgeService;
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
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * UserRegistrationType constructor.
     * @param DOMJudgeService        $dj
     * @param EntityManagerInterface $em
     */
    public function __construct(DOMJudgeService $dj, EntityManagerInterface $em)
    {
        $this->dj = $dj;
        $this->em = $em;
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
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'Email address (optional)',
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
                    new Callback(function ($teamName, ExecutionContext $context) {
                        if ($this->em->getRepository(Team::class)->findOneBy(['name' => $teamName])) {
                            $context->buildViolation('This team name is already in use.')
                                ->atPath('teamName')
                                ->addViolation();
                        }
                    }),
                ],
                'mapped' => false,
            ]);

        if ($this->dj->dbconfig_get('show_affiliations', true)) {
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
                    'constraints' => [
                        new Callback(function ($affiliationName, ExecutionContext $context) {
                            if ($this->em->getRepository(TeamAffiliation::class)->findOneBy(['name' => $affiliationName])) {
                                $context->buildViolation('This affiliation name is already in use.')
                                    ->atPath('team_name')
                                    ->addViolation();
                            }
                        }),
                    ],
                    'mapped' => false,
                ])
                // TODO: after #544 is merged, improve this
                ->add('affiliationCountry', TextType::class, [
                    'label' => false,
                    'required' => false,
                    'mapped' => false,
                    'attr' => [
                        'placeholder' => 'Affiliation country',
                    ],
                ])
                ->add('existingAffiliation', EntityType::class, [
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

    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $validateAffiliation = function ($data, ExecutionContext $context) {
            if ($this->dj->dbconfig_get('show_affiliations', true)) {
                /** @var Form $form */
                $form = $context->getRoot();
                switch ($form->get('affiliation')->getData()) {
                    case 'new':
                        if (empty($form->get('affiliationName')->getData())) {
                            $context->buildViolation('This value should not be blank.')
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
                'constraints' => new Callback($validateAffiliation)
            ]
        );
    }
}
