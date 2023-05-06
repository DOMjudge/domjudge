<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Judgehost;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JudgehostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('hostname', TextType::class, [
            'label' => 'Hostname',
            'attr' => ['readonly' => true],
        ]);
        $builder->add('enabled', ChoiceType::class, [
            'label' => 'Enabled',
            'choices' => [
                'yes' => true,
                'no' => false,
            ],
        ]);
        $builder->add('hidden', ChoiceType::class, [
            'label' => 'Hidden',
            'choices' => [
                'yes' => true,
                'no' => false,
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Judgehost::class]);
    }
}
