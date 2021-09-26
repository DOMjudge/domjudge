<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Judgehost;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JudgehostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('hostname', TextType::class, [
            'attr' => ['readonly' => true],
        ]);
        $builder->add('active', ChoiceType::class, [
            'choices' => [
                'yes' => true,
                'no' => false,
            ],
        ]);
        $builder->add('hidden', ChoiceType::class, [
            'choices' => [
                'yes' => true,
                'no' => false,
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => Judgehost::class]);
    }
}
