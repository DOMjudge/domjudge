<?php declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigKeyValueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (['key', 'val'] as $field) {
            $choices = $options[$field . '_options'];
            if ($choices !== null) {
                $builder->add($field, ChoiceType::class, [
                    'label' => false,
                    'required' => false,
                    'placeholder' => 'Select…',
                ] + ConfigurationType::choiceOptions($choices));
            } else {
                $type = $field === 'val' && $options['val_type'] === 'int' ? IntegerType::class : TextType::class;
                $builder->add($field, $type, [
                    'label' => false,
                    'attr' => ['placeholder' => $options[$field . '_placeholder']],
                ]);
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'key_options' => null,
            'val_options' => null,
            'key_placeholder' => null,
            'val_placeholder' => null,
            'val_type' => null,
        ]);
    }
}
