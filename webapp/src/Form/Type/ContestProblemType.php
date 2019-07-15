<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\ContestProblem;
use App\Entity\Problem;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContestProblemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('problem', EntityType::class, [
            'class' => Problem::class,
            'required' => true,
            'choice_label' => function (Problem $problem) {
                return sprintf('p%d - %s', $problem->getProbid(), $problem->getName());
            },
            'query_builder' => function (EntityRepository $er) {
                return $er
                    ->createQueryBuilder('p')
                    ->orderBy('p.probid');
            },
        ]);

        $builder->add('shortname', TextType::class, [
            'label' => 'Short name',
        ]);
        $builder->add('points');
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
                'class' => 'color {required:false,adjust:false,hash:true,caps:false}',
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

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => ContestProblem::class]);
    }
}
