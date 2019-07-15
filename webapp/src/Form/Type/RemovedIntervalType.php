<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\RemovedInterval;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RemovedIntervalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('starttimeString', TextType::class, [
            'required' => true,
        ]);
        $builder->add('endtimeString', TextType::class, [
            'required' => true,
        ]);
        $builder->add('add', SubmitType::class, [
            'attr' => [
                'class' => 'btn-sm btn-primary',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => RemovedInterval::class]);
    }
}
