<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\ContestProblem;
use App\Entity\Problem;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
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
            'choice_label' => fn(Problem $problem) => sprintf('p%d - %s', $problem->getProbid(), $problem->getName()),
            'query_builder' => fn(EntityRepository $er) => $er
                ->createQueryBuilder('p')
                ->orderBy('p.probid'),
        ]);

        $builder->add('shortname', TextType::class, [
            'label' => 'Short name',
        ]);
        $builder->add('points', IntegerType::class);
        $builder->add('allowSubmit', ChoiceType::class, [
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
        ]);
        $builder->add('allowJudge', ChoiceType::class, [
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
        ]);
        $builder->add('color', TextType::class, [
            'required' => false,
            'label' => 'Colour',
            'attr' => [
                'data-color-picker' => '',
            ],
        ]);
        $builder->add('lazyEvalResults', ChoiceType::class, [
            'label' => 'Lazy eval',
            'choices' => [
                'Default' => null,
                'Yes' => true,
                'No' => false,
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ContestProblem::class]);
    }
}
