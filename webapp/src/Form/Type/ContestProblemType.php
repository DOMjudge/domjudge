<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\ContestProblem;
use App\Entity\Problem;
use App\Service\DOMJudgeService as DJS;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContestProblemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('problem', EntityType::class, [
            'class' => Problem::class,
            'required' => true,
            'choice_label' => fn(Problem $problem) => sprintf('%s - %s', $problem->getExternalid(), $problem->getName()),
            'choice_value' => 'externalid',
            'query_builder' => fn(EntityRepository $er) => $er
                ->createQueryBuilder('p')
                ->orderBy('p.probid'),
        ]);

        $builder->add('shortname', TextType::class, [
            'label' => 'Short name',
            'empty_data' => '',
            'attr' => ['size' => 4],
        ]);
        $builder->add('points', IntegerType::class, [
            'label' => 'Points',
            'attr' => ['style' => 'width: 5em;'],
        ]);
        $builder->add('allowSubmit', CheckboxType::class, [
            'label' => 'Allow submit',
            'required' => false,
            'attr' => ContestType::TOGGLE_ATTRS,
        ]);
        $builder->add('allowJudge', CheckboxType::class, [
            'label' => 'Allow judge',
            'required' => false,
            'attr' => ContestType::TOGGLE_ATTRS,
        ]);
        $builder->add('color', TextType::class, [
            'required' => false,
            'label' => 'Colour',
            'attr' => [
                'data-color-picker' => '',
                'size' => 6,
            ],
        ]);
        $builder->add('lazyEvalResults', ChoiceType::class, [
            'label' => 'Lazy eval',
            'choices' => [
                'Default' => DJS::EVAL_DEFAULT,
                'Yes' => DJS::EVAL_LAZY,
                'No' => DJS::EVAL_FULL,
                'On demand' => DJS::EVAL_DEMAND,
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ContestProblem::class]);
    }
}
