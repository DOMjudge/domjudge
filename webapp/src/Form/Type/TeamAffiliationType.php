<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\TeamAffiliation;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TeamAffiliationType extends AbstractExternalIdEntityType
{
    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * TeamAffiliationType constructor.
     *
     * @param EventLogService      $eventLogService
     * @param DOMJudgeService      $dj
     */
    public function __construct(
        EventLogService $eventLogService,
        DOMJudgeService $dj
    ) {
        parent::__construct($eventLogService);
        $this->dj = $dj;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     * @throws \Exception
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $countries = [];
        foreach (Utils::ALPHA3_COUNTRIES as $alpha3 => $country) {
            $countries["$country ($alpha3)"] = $alpha3;
        }

        $this->addExternalIdField($builder, TeamAffiliation::class);
        $builder->add('shortname');
        $builder->add('name');
        if ($this->dj->dbconfig_get('show_flags')) {
            $builder->add('country', ChoiceType::class, [
                'required' => false,
                'choices'  => $countries,
                'placeholder' => 'No country',
            ]);
        }
        $builder->add('comments', TextareaType::class, [
            'required' => false,
            'attr' => [
                'rows' => 6,
            ],
        ]);
        $builder->add('save', SubmitType::class);
    }


    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => TeamAffiliation::class]);
    }
}
