<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Contest;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
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
     * @param FormBuilderInterface $builder
     * @param array                $options
     * @throws \Exception
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('icpcid', TextType::class, [
            'label' => 'ICPC ID',
            'required' => false,
            'help' => 'ID of the team in the ICPC CMS',
            'constraints' => [
                new Regex(
                    [
                        'pattern' => '/^[a-zA-Z0-9_-]+$/i',
                        'message' => 'Only letters, numbers, dashes and underscores are allowed',
                    ]
                )
            ]
        ]);
        $builder->add('name', TextType::class, [
            'label' => 'Team name',
        ]);
        $builder->add('displayName', TextType::class, [
            'label' => 'Display name',
            'required' => false,
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
        $builder->add('addUserForTeam', CheckboxType::class, [
            'label' => 'Add user for this team',
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
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => Team::class]);
    }
}
