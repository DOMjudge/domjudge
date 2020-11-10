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
        $builder->add('name', TextType::class, [
            'help' => 'Contest name as shown in the top right.'
        ]);
        $builder->add('activatetimeString', TextType::class, [
            'label' => 'Activate time',
            'help' => 'Time when the contest becomes visible for teams to join. Must be in the past to enable submission of jury submissions.',
        ]);
        $builder->add('starttimeString', TextType::class, [
            'label' => 'Start time',
            'help' => 'Absolute time when the contest starts.',
        ]);
        $builder->add('starttimeEnabled', ChoiceType::class, [
            'label' => 'Start time enabled',
            'expanded' => true,
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
            'help' => 'Disable to delay the contest start and stop the countdown. Enable again after setting a new start time.',
        ]);
        $builder->add('freezetimeString', TextType::class, [
            'label' => 'Scoreboard freeze time',
            'required' => false,
            'help' => 'Time when the freeze starts: the results of submissions made after this time are not revealed until the scoreboard unfreeze time below has passed.',
        ]);
        $builder->add('endtimeString', TextType::class, [
            'label' => 'End time',
            'help' => 'Time when the contest ends. Submissions made after this time will be accepted but shown as \'too-late\' and not counted towards the score.',
        ]);
        $builder->add('unfreezetimeString', TextType::class, [
            'label' => 'Scoreboard unfreeze time',
            'required' => false,
            'help' => 'Time when the final scoreboard is revealed. Usually this is a few hours after the contest ends and the award ceremony is over.',
        ]);
        $builder->add('deactivatetimeString', TextType::class, [
            'label' => 'Deactivate time',
            'required' => false,
            'help' => 'Time when the contest and scoreboard are hidden again. Usually a few hours/days after the contest ends.',
        ]);
        $builder->add('processBalloons', ChoiceType::class, [
            'expanded' => true,
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
            'help' => 'Disable this to stop recording balloons. Usually you can just leave this to true.',
        ]);
        $builder->add('public', ChoiceType::class, [
            'expanded' => true,
            'label' => 'Contest visible on public scoreboard',
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
            'help' => 'When true, the public scoreboard is enabled and everyone can see it without logging in. When false, only logged in users/teams participating in the contest can see the scoreboard.',
        ]);
        $builder->add('openToAllTeams', ChoiceType::class, [
            'expanded' => true,
            'label' => 'Contest open to all teams',
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
            'help' => 'When true, any logged in team can join this contest, and the teams/categories listed below are added by default. When false, only the teams/categories listed below will participate.',
        ]);
        $builder->add('teams', EntityType::class, [
            'required' => false,
            'class' => Team::class,
            'multiple' => true,
            'choice_label' => function (Team $team) {
                return sprintf('%s (t%d)', $team->getEffectiveName(), $team->getTeamid());
            },
            'help' => 'List of teams participating in the contest, in case it is not open to all teams.',
        ]);
        $builder->add('teamCategories', EntityType::class, [
            'required' => false,
            'class' => TeamCategory::class,
            'multiple' => true,
            'choice_label' => function (TeamCategory $category) {
                return $category->getName();
            },
            'help' => 'List of team categories participating in the contest, in case it is not open to all teams.',
        ]);
        $builder->add('enabled', ChoiceType::class, [
            'expanded' => true,
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
            'help' => 'When false, the contest is hidden from teams (even when active) and judging is disabled. Disabling is a quick way to remove access to it without changing any other settings.',
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
