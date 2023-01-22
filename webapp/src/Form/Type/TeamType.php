<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Contest;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Entity\User;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Regex;

class TeamType extends AbstractExternalIdEntityType
{
    protected EntityManagerInterface $em;
    protected DOMJudgeService $dj;

    public function __construct(
        EventLogService $eventLogService,
        EntityManagerInterface $em,
        DOMJudgeService $dj
    ) {
        parent::__construct($eventLogService);
        $this->em = $em;
        $this->dj = $dj;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addExternalIdField($builder, Team::class);
        $builder->add('name', TextType::class, [
            'label' => 'Team name',
        ]);
        $builder->add('icpcid', TextType::class, [
            'label'       => 'ICPC ID',
            'required'    => false,
            'help'        => 'Optional ID of the team in the ICPC CMS.',
            'constraints' => [
                new Regex(
                    [
                        'pattern' => '/^[a-zA-Z0-9_-]+$/i',
                        'message' => 'Only letters, numbers, dashes and underscores are allowed.',
                    ]
                )
            ]
        ]);
        $builder->add('displayName', TextType::class, [
            'label'    => 'Display name',
            'required' => false,
            'help'     => 'If provided, will display this instead of the team name in certain places, like the scoreboard.',
        ]);
        $builder->add('category', EntityType::class, [
            'class' => TeamCategory::class,
        ]);
        $builder->add('publicdescription', TextareaType::class, [
            'label' => 'Public description',
            'required' => false,
        ]);
        $builder->add('affiliation', EntityType::class, [
            'class'         => TeamAffiliation::class,
            'required'      => false,
            'choice_label'  => 'name',
            'placeholder'   => '-- no affiliation --',
            'query_builder' => fn(EntityRepository $er) => $er->createQueryBuilder('a')->orderBy('a.name'),
        ]);
        $builder->add('penalty', IntegerType::class, [
            'label' => 'Penalty time',
        ]);
        $builder->add('room', TextType::class, [
            'label'    => 'Location',
            'required' => false,
        ]);
        $builder->add('internalcomments', TextareaType::class, [
            'label' => 'Internal comments (jury viewable only)',
            'required' => false,
            'attr'     => [
                'rows' => 10,
            ]
        ]);
        $builder->add('contests', EntityType::class, [
            'class'         => Contest::class,
            'required'      => false,
            'choice_label'  => 'name',
            'multiple'      => true,
            'by_reference'  => false,
            'query_builder' => fn(EntityRepository $er) => $er
                ->createQueryBuilder('c')
                ->where('c.openToAllTeams = false')
                ->orderBy('c.name'),
        ]);
        $builder->add('enabled', ChoiceType::class, [
            'expanded' => true,
            'choices'  => [
                'Yes' => true,
                'No'  => false,
            ],
        ]);
        $builder->add('photoFile', FileType::class, [
            'label'    => 'Photo',
            'required' => false,
        ]);
        $builder->add('clearPhoto', CheckboxType::class, [
            'label'    => 'Delete photo',
            'required' => false,
        ]);
        $builder->add('addUserForTeam', ChoiceType::class, [
            'label'   => 'Add user to this team',
            'choices' => [
                "Don't add user"    => Team::DONT_ADD_USER,
                'Create new user'   => Team::CREATE_NEW_USER,
                'Add existing user' => Team::ADD_EXISTING_USER,
            ],
        ]);
        $builder->add('existingUser', EntityType::class, [
            'class'        => User::class,
            'label'        => "User",
            'required'     => true,
            'choice_label' => 'name',
        ]);
        $builder->add('newUsername', TextType::class, [
            'label'    => 'Username',
            'required' => true,
        ]);

        $builder->add('save', SubmitType::class);

        // Remove ID field when doing an edit.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var Team|null $team */
            $team = $event->getData();
            $form = $event->getForm();

            if ($team && $team->getTeamid() !== null && $team->getUsers()->count()>0) {
                $form->remove('addUserForTeam');
                $form->remove('existingUser');
                $form->remove('newUsername');
            }

            if (!$team || !$this->dj->assetPath((string)$team->getTeamid(), 'team')) {
                $form->remove('clearPhoto');
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Team::class]);
    }
}
