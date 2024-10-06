<?php declare(strict_types=1);

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InputGroupExtension extends AbstractTypeExtension
{

    public static function getExtendedTypes(): iterable
    {
        return [NumberType::class, IntegerType::class, TextType::class];
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        if (isset($options['input_group_before'])) {
            $view->vars['input_group_before'] = $options['input_group_before'];
        }
        if (isset($options['input_group_after'])) {
            $view->vars['input_group_after'] = $options['input_group_after'];
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'input_group_before' => null,
            'input_group_after' => null,
        ]);
    }
}
