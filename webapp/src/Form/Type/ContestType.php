<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\ExternalContestSourceType;
use App\Entity\Language;
use App\Entity\ScoreboardType;
use App\Entity\Team;
use App\Entity\TeamCategory;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContestType extends AbstractExternalIdEntityType
{
    public const TOGGLE_ATTRS = ['data-toggle' => 'toggle', 'data-size' => 'mini', 'data-on' => 'Yes', 'data-off' => 'No'];

    public function __construct(EventLogService $eventLogService, protected readonly DOMJudgeService $dj)
    {
        parent::__construct($eventLogService);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addExternalIdField($builder, Contest::class);
        $builder->add('shortname', TextType::class, [
            'help' => 'Contest name as shown in the top right.',
            'empty_data' => ''
        ]);
        $builder->add('name', TextType::class, [
            'help' => 'Contest name in full as shown on the scoreboard.',
            'empty_data' => ''
        ]);
        $builder->add('activatetimeString', TextType::class, [
            'label' => 'Activate time',
            'help' => 'Time when the contest becomes visible for teams. Must be in the past to enable submission of jury submissions.',
        ]);
        $builder->add('starttimeString', TextType::class, [
            'label' => 'Start time',
            'help' => 'Absolute time when the contest starts.',
        ]);
        $builder->add('starttimeEnabled', CheckboxType::class, [
            'label' => 'Start time countdown enabled',
            'required' => false,
            'help' => 'Disable to delay the contest start and stop the countdown. Enable again after setting a new start time.',
            'attr' => self::TOGGLE_ATTRS,
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
        $builder->add('scoreboardType', ChoiceType::class, [
            'label' => 'Scoreboard type',
            'choices' => [
                'pass-fail' => ScoreboardType::PASS_FAIL,
                'score' => ScoreboardType::SCORE,
            ],
            'help' => 'The type of scoreboard to use for this contest.',
        ]);
        $builder->add('allowSubmit', CheckboxType::class, [
            'required' => false,
            'label' => 'Allow submit',
            'help' => 'When disabled, users cannot submit to the contest and a warning will be displayed.',
            'attr' => self::TOGGLE_ATTRS,
        ]);
        $builder->add('processBalloons', CheckboxType::class, [
            'required' => false,
            'label' => 'Record balloons',
            'help' => 'Disable this to stop recording balloons. Usually you can just leave this enabled.',
            'attr' => self::TOGGLE_ATTRS,
        ]);
        $builder->add('runtimeAsScoreTiebreaker', CheckboxType::class, [
            'required' => false,
            'help' => 'Enable this to show runtimes in seconds on scoreboard and use them as tiebreaker instead of penalty. The runtime of a submission is the maximum over all testcases.',
            'attr' => self::TOGGLE_ATTRS,
        ]);
        $builder->add('medalsEnabled', CheckboxType::class, [
            'required' => false,
            'help' => 'Whether to enable medals (gold, silver, bronze) for this contest.',
            'attr' => self::TOGGLE_ATTRS,
        ]);
        $builder->add('medalCategories', EntityType::class, [
            'required' => false,
            'class' => TeamCategory::class,
            'multiple' => true,
            'choice_label' => fn(TeamCategory $category) => $category->getName(),
            'choice_value' => 'externalid',
            'help' => 'List of team categories that will receive medals for this contest.',
            'query_builder' => function (EntityRepository $er) {
                return $er->createQueryBuilder('c')
                    ->andWhere('BIT_AND(c.types, :scoring) = :scoring')
                    ->setParameter('scoring', TeamCategory::TYPE_SCORING);
            }
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
        $builder->add('public', CheckboxType::class, [
            'required' => false,
            'label' => 'Enable public scoreboard',
            'help' => 'When the public scoreboard is enabled, everyone can see it without logging in. When disabled, only logged in users/teams can see the scoreboard.',
            'attr' => self::TOGGLE_ATTRS,
        ]);
        $builder->add('openToAllTeams', CheckboxType::class, [
            'required' => false,
            'label' => 'Open contest to all teams',
            'help' => 'When enabled, any logged in team is part of the contest. When disabled, only the teams/categories listed below are part of the contest.',
            'attr' => self::TOGGLE_ATTRS,
        ]);
        $builder->add('teams', EntityType::class, [
            'required' => false,
            'class' => Team::class,
            'multiple' => true,
            'choice_label' => fn(Team $team) => sprintf('%s (%s)', $team->getEffectiveName(), $team->getExternalid()),
            'choice_value' => 'externalid',
            'help' => 'List of teams participating in the contest, in case it is not open to all teams.',
        ]);
        $builder->add('teamCategories', EntityType::class, [
            'required' => false,
            'class' => TeamCategory::class,
            'multiple' => true,
            'choice_label' => fn(TeamCategory $category) => $category->getName(),
            'choice_value' => 'externalid',
            'help' => 'List of team categories participating in the contest, in case it is not open to all teams.',
        ]);
        $builder->add('enabled', CheckboxType::class, [
            'required' => false,
            'label' => 'Enable contest',
            'help' => 'When disabled, the contest is hidden from teams (even when active) and judging is disabled. Disabling is a quick way to remove access to it without changing any other settings.',
            'attr' => self::TOGGLE_ATTRS,
        ]);
        $builder->add('bannerFile', FileType::class, [
            'label' => 'Banner',
            'required' => false,
        ]);
        $builder->add('clearBanner', CheckboxType::class, [
            'label' => 'Delete banner',
            'required' => false,
        ]);
        $builder->add('contestProblemsetFile', FileType::class, [
            'label' => 'Problemset document',
            'required' => false,
            'attr' => [
                'accept' => 'text/html,text/plain,application/pdf',
            ],
        ]);
        $builder->add('clearContestProblemset', CheckboxType::class, [
            'label' => 'Delete contest problemset document',
            'required' => false,
        ]);
        $builder->add('warningMessage', TextType::class, [
            'required' => false,
            'label' => 'Scoreboard warning message',
            'help' => 'When set, a warning message displayed above all scoreboards for this contest.',
        ]);
        $builder->add('externalSourceEnabled', CheckboxType::class, [
            'required' => false,
            'label' => 'Enable shadow mode',
            'help' => 'When enabled, this contest will shadow an external contest source.',
            'attr' => self::TOGGLE_ATTRS,
        ]);
        $builder->add('externalSourceUseJudgements', CheckboxType::class, [
            'required' => false,
            'label' => 'Use external judgements',
            'help' => 'When enabled, external judgements will be used for results and scoring instead of local judgings.',
            'attr' => self::TOGGLE_ATTRS,
        ]);
        $builder->add('externalSourceType', EnumType::class, [
            'class' => ExternalContestSourceType::class,
            'required' => false,
            'placeholder' => false,
            'label' => 'External source type',
            'choice_label' => 'readable',
        ]);
        $builder->add('externalSourceSource', TextType::class, [
            'required' => false,
            'label' => 'External source',
            'help' => 'For contest package: directory on disk to use. For CCS API: URL to contest in API.',
        ]);
        $builder->add('externalSourceUsername', TextType::class, [
            'required' => false,
            'label' => 'External source username',
        ]);
        $builder->add('externalSourcePassword', TextType::class, [
            'required' => false,
            'label' => 'External source password',
        ]);
        $builder->add('scoreDiffEpsilon', TextType::class, [
            'required' => false,
            'label' => 'Score difference epsilon',
            'help' => 'Minimum absolute score difference to consider as a meaningful shadow difference for scoring problems. Default is 0.0001.',
        ]);
        $builder->add('shadowCompareByScore', CheckboxType::class, [
            'required' => false,
            'label' => 'Compare by score only',
            'help' => 'When enabled, ignore verdict differences if scores match within epsilon for scoring problems.',
            'attr' => self::TOGGLE_ATTRS,
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
        $builder->add('languages', EntityType::class, [
            'required' => false,
            'class' => Language::class,
            'multiple' => true,
            'choice_label' => fn(Language $language) => sprintf('%s (%s)', $language->getName(), $language->getExternalid()),
            'choice_value' => 'externalid',
            'help' => 'List of languages that can be used in this contest. Leave empty to allow all languages that are enabled globally.',
        ]);

        $builder->add('save', SubmitType::class);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            /** @var Contest|null $contest */
            $contest = $event->getData();
            $form = $event->getForm();

            $id = $contest?->getExternalid();

            if (!$contest || !$this->dj->assetPath($id, 'contest')) {
                $form->remove('clearBanner');
            }

            if ($contest && !$contest->getContestProblemset()) {
                $form->remove('clearContestProblemset');
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Contest::class]);
    }
}
