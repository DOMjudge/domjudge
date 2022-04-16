<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\TeamAffiliation;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Intl\Countries;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Regex;

class TeamAffiliationType extends AbstractExternalIdEntityType
{
    protected ConfigurationService $configuration;
    protected DOMJudgeService $dj;

    public function __construct(
        EventLogService $eventLogService,
        ConfigurationService $configuration,
        DOMJudgeService $dj
    ) {
        parent::__construct($eventLogService);
        $this->configuration = $configuration;
        $this->dj = $dj;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $countries = [];
        foreach (Countries::getAlpha3Codes() as $alpha3) {
            $name = Countries::getAlpha3Name($alpha3);
            $countries["$name ($alpha3)"] = $alpha3;
        }

        $this->addExternalIdField($builder, TeamAffiliation::class);
        $builder->add('icpcid', TextType::class, [
            'label'       => 'ICPC ID',
            'required'    => false,
            'help'        => 'Optional ID of the organization in the ICPC CMS.',
            'constraints' => [
                new Regex(
                    [
                        'pattern' => '/^[a-zA-Z0-9_-]+$/i',
                        'message' => 'Only letters, numbers, dashes and underscores are allowed.',
                    ]
                )
            ]
        ]);
        $builder->add('shortname');
        $builder->add('name');
        if ($this->configuration->get('show_flags')) {
            $builder->add('country', ChoiceType::class, [
                'required' => false,
                'choices'  => $countries,
                'placeholder' => 'No country',
            ]);
        }
        $builder->add('internalcomments', TextareaType::class, [
            'label' => 'Internal comments (jury viewable only)',
            'required' => false,
            'attr' => [
                'rows' => 6,
            ],
        ]);
        $builder->add('logoFile', FileType::class, [
            'label' => 'Logo',
            'required' => false,
        ]);
        $builder->add('clearLogo', CheckboxType::class, [
            'label' => 'Delete logo',
            'required' => false,
        ]);
        $builder->add('save', SubmitType::class);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var TeamAffiliation|null $affiliation */
            $affiliation = $event->getData();
            $form = $event->getForm();

            $id = $affiliation ? $affiliation->getApiId($this->eventLogService) : null;

            if (!$affiliation || !$this->dj->assetPath($id, 'affiliation')) {
                $form->remove('clearLogo');
            }
        });
    }


    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => TeamAffiliation::class]);
    }
}
