<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Contest;
use App\Entity\Role;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Entity\User;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
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

class TeamType extends AbstractType
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    public function __construct(EntityManagerInterface $em, DOMJudgeService $dj)
    {
        $this->em = $em;
        $this->dj = $dj;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     * @throws \Exception
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name', TextType::class, [
            'label' => 'Team name',
        ]);
        $builder->add('displayName', TextType::class, [
            'label' => 'Display name',
            'required' => false,
            'help' => 'If provided, will display this instead of the team name in certain places, like the scoreboard.',
        ]);
        $builder->add('icpcid', TextType::class, [
            'label' => 'ICPC ID',
            'required' => false,
            'help' => 'Optional ID of the team in the ICPC CMS.',
            'constraints' => [
                new Regex(
                    [
                        'pattern' => '/^[a-zA-Z0-9_-]+$/i',
                        'message' => 'Only letters, numbers, dashes and underscores are allowed.',
                    ]
                )
            ]
        ]);
        $builder->add('category', EntityType::class, [
            'class' => TeamCategory::class,
        ]);
        $builder->add('members', TextareaType::class, [
            'required' => false,
        ]);
        $builder->add('affiliation', EntityType::class, [
            'class' => TeamAffiliation::class,
            'required' => false,
            'choice_label' => 'name',
            'placeholder' => '-- no affiliation --',
            'query_builder' => function (EntityRepository $er) {
                return $er->createQueryBuilder('a')->orderBy('a.name');
            },
        ]);
        $builder->add('penalty', IntegerType::class, [
            'label' => 'Penalty time',
        ]);
        $builder->add('room', TextType::class, [
            'label' => 'Location',
            'required' => false,
        ]);
        $builder->add('comments', TextareaType::class, [
            'required' => false,
            'attr' => [
                'rows' => 10,
            ]
        ]);
        $builder->add('contests', EntityType::class, [
            'class' => Contest::class,
            'required' => false,
            'choice_label' => 'name',
            'multiple' => true,
            'by_reference' => false,
            'query_builder' => function (EntityRepository $er) {
                return $er->createQueryBuilder('c')->where('c.openToAllTeams = false')->orderBy('c.name');
            },
        ]);
        $builder->add('enabled', ChoiceType::class, [
            'expanded' => true,
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
        ]);
        $builder->add('photoFile', FileType::class, [
            'label' => 'Photo',
            'required' => false,
        ]);
        $builder->add('clearPhoto', CheckboxType::class, [
            'label' => 'Delete photo',
            'required' => false,
        ]);
        $builder->add('addUserForTeam', CheckboxType::class, [
            'label' => 'Add new user for this team',
            'required' => false,
        ]);
        $builder->add('users', CollectionType::class, [
            'entry_type' => MinimalUserType::class,
            'entry_options' => ['label' => false],
            'label' => false,
            'required' => false,
        ]);

        $builder->add('save', SubmitType::class);

        // Remove ID field when doing an edit
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var Team|null $team */
            $team = $event->getData();
            $form = $event->getForm();

            if ($team && $team->getTeamid() !== null) {
                $form->remove('addUserForTeam');
                $form->remove('users');
            }

            if ($team) {
                // Make sure the user has the team role to make validation work
                /** @var User|false $user */
                $user = $team->getUsers()->first();
                if ($user && !$this->em->contains($team)) {
                    /** @var Role $role */
                    $role = $this->em->createQueryBuilder()
                        ->from(Role::class, 'r')
                        ->select('r')
                        ->andWhere('r.dj_role = :team')
                        ->setParameter(':team', 'team')
                        ->getQuery()
                        ->getOneOrNullResult();
                    $user->addUserRole($role);
                }
            }

            if (!$team || !$this->dj->assetPath((string)$team->getTeamid(), 'team')) {
                $form->remove('clearPhoto');
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => Team::class]);
    }
}
