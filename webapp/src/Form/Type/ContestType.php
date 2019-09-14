<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Team;
use App\Entity\TeamCategory;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContestType extends AbstractExternalIdEntityType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     * @throws \Exception
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->addExternalIdField($builder, Contest::class);
        $builder->add('shortname', TextType::class);
        $builder->add('name', TextType::class);
        $builder->add('activatetimeString', TextType::class, [
            'label' => 'Activate time',
        ]);
        $builder->add('starttimeString', TextType::class, [
            'label' => 'Start time',
        ]);
        $builder->add('starttimeEnabled', ChoiceType::class, [
            'label' => 'Start time enabled',
            'expanded' => true,
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
        ]);
        $builder->add('freezetimeString', TextType::class, [
            'label' => 'Scoreboard freeze time',
            'required' => false,
        ]);
        $builder->add('endtimeString', TextType::class, [
            'label' => 'End time',
        ]);
        $builder->add('unfreezetimeString', TextType::class, [
            'label' => 'Scoreboard unfreeze time',
            'required' => false,
        ]);
        $builder->add('deactivatetimeString', TextType::class, [
            'label' => 'Deactivate time',
            'required' => false,
        ]);
        $builder->add('processBalloons', ChoiceType::class, [
            'expanded' => true,
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
        ]);
        $builder->add('public', ChoiceType::class, [
            'expanded' => true,
            'label' => 'Contest visible on public scoreboard',
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
        ]);
        $builder->add('openToAllTeams', ChoiceType::class, [
            'expanded' => true,
            'label' => 'Contest open to all teams',
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
        ]);
        $builder->add('teams', EntityType::class, [
            'required' => false,
            'class' => Team::class,
            'multiple' => true,
            'choice_label' => function (Team $team) {
                return sprintf('%s (t%d)', $team->getName(), $team->getTeamid());
            },
        ]);
        $builder->add('teamCategories', EntityType::class, [
            'required' => false,
            'class' => TeamCategory::class,
            'multiple' => true,
            'choice_label' => function (TeamCategory $category) {
                return $category->getName();
            },
        ]);
        $builder->add('enabled', ChoiceType::class, [
            'expanded' => true,
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
        ]);
        $builder->add('problems', CollectionType::class, [
            'entry_type' => ContestProblemType::class,
            'prototype' => true,
            'prototype_data' => new ContestProblem(),
            'entry_options' => ['label' => false],
            'allow_add' => true,
            'allow_delete' => true,
            'label' => false,
        ]);

        $builder->add('save', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => Contest::class]);
    }
}
