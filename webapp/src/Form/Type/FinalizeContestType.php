<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Contest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FinalizeContestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('b', IntegerType::class, [
                'label' => 'Additional Bronze Medals'
            ])
            ->add('b2', IntegerType::class, [
                'label' => 'High honors',
                'help' => 'Last rank to receive high honors (leave empty to use 25th percentile).'
            ])
            ->add('b3', IntegerType::class, [
                'label' => 'Honors',
                'help' => 'Last rank to receive honors (leave empty to use 50th percentile).'
            ])
            ->add('finalizecomment', TextareaType::class, [
                'label' => 'Comment',
                'required' => false,
            ])
            ->add('finalize', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => Contest::class]);
    }
}
