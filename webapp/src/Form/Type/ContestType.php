<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Team;
use App\Entity\TeamCategory;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContestType extends AbstractExternalIdEntityType
{
    protected DOMJudgeService $dj;

    public function __construct(EventLogService $eventLogService, DOMJudgeService $dj)
    {
        parent::__construct($eventLogService);
        $this->dj = $dj;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addExternalIdField($builder, Contest::class);
        $builder->add('shortname', TextType::class, [
            'help' => 'Contest name as shown in the top right.'
        ]);
        $builder->add('name', TextType::class, [
            'help' => 'Contest name in full as shown on the scoreboard.'
        ]);
        $builder->add('activatetimeString', TextType::class, [
            'label' => 'Activate time',
            'help' => 'Time when the contest becomes visible for teams. Must be in the past to enable submission of jury submissions.',
        ]);
        $builder->add('starttimeString', TextType::class, [
            'label' => 'Start time',
            'help' => 'Absolute time when the contest starts.',
        ]);
        $builder->add('starttimeEnabled', ChoiceType::class, [
            'label' => 'Start time countdown enabled',
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
            'help' => 'Time when the contest ends. Submissions made after this time will be accepted and judged but shown (to teams and public) as \'too-late\' and not counted towards the score.',
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
        $builder->add('allowSubmit', ChoiceType::class, [
            'expanded' => true,
            'label' => 'Allow submit',
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
            'help' => 'When disabled, users cannot submit to the contest and a warning will be displayed.',
        ]);
        $builder->add('processBalloons', ChoiceType::class, [
            'expanded' => true,
            'label' => 'Record balloons',
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
            'help' => 'Disable this to stop recording balloons. Usually you can just leave this enabled.',
        ]);
        $builder->add('medalsEnabled', ChoiceType::class, [
            'expanded' => true,
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
            'help' => 'Whether to enable medals (gold, silver, bronze) for this contest.',
        ]);
        $builder->add('medalCategories', EntityType::class, [
            'required' => false,
            'class' => TeamCategory::class,
            'multiple' => true,
            'choice_label' => fn(TeamCategory $category) => $category->getName(),
            'help' => 'List of team categories that will receive medals for this contest.',
        ]);
        foreach (['gold', 'silver', 'bronze'] as $medalType) {
            $help = "The number of $medalType medals for this contest.";
            if ($medalType === 'bronze') {
                $help .= ' Note that when finalizing a contest, the "Additional Bronze Medals" will be added to this.';
            }
            $builder->add($medalType . 'Medals', IntegerType::class, [
                'required' => false,
                'help'     => $help,
            ]);
        }
        $builder->add('public', ChoiceType::class, [
            'expanded' => true,
            'label' => 'Enable public scoreboard',
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
            'help' => 'When the public scoreboard is enabled, everyone can see it without logging in. When disabled, only logged in users/teams can see the scoreboard.',
        ]);
        $builder->add('openToAllTeams', ChoiceType::class, [
            'expanded' => true,
            'label' => 'Open contest to all teams',
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
            'help' => 'When enabled, any logged in team is part of the contest. When disabled, only the teams/categories listed below are part of the contest.',
        ]);
        $builder->add('teams', EntityType::class, [
            'required' => false,
            'class' => Team::class,
            'multiple' => true,
            'choice_label' => fn(Team $team) => sprintf('%s (t%d)', $team->getEffectiveName(), $team->getTeamid()),
            'help' => 'List of teams participating in the contest, in case it is not open to all teams.',
        ]);
        $builder->add('teamCategories', EntityType::class, [
            'required' => false,
            'class' => TeamCategory::class,
            'multiple' => true,
            'choice_label' => fn(TeamCategory $category) => $category->getName(),
            'help' => 'List of team categories participating in the contest, in case it is not open to all teams.',
        ]);
        $builder->add('enabled', ChoiceType::class, [
            'expanded' => true,
            'label' => 'Enable contest',
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
            'help' => 'When disabled, the contest is hidden from teams (even when active) and judging is disabled. Disabling is a quick way to remove access to it without changing any other settings.',
        ]);
        $builder->add('bannerFile', FileType::class, [
            'label' => 'Banner',
            'required' => false,
        ]);
        $builder->add('clearBanner', CheckboxType::class, [
            'label' => 'Delete banner',
            'required' => false,
        ]);
        $builder->add('warningMessage', TextType::class, [
            'required' => false,
            'label' => 'Scoreboard warning message',
            'help' => 'When set, a warning message displayed above all scoreboards for this contest.',
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

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var Contest|null $contest */
            $contest = $event->getData();
            $form = $event->getForm();

            $id = $contest ? $contest->getApiId($this->eventLogService) : null;

            if (!$contest || !$this->dj->assetPath($id, 'contest')) {
                $form->remove('clearBanner');
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Contest::class]);
    }
}
