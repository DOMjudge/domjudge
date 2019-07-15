<?php declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class GeneratePasswordsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $choices = [
            'All teams' => 'team',
            'Teams without password' => 'team_nopass',
            'Jury members' => 'judge',
            'Administrators' => 'admin',
        ];
        $builder->add('group', ChoiceType::class, [
            'label' => 'Generate a new password for:',
            'expanded' => true,
            'multiple' => true,
            'choices' => $choices]);
        $builder->add('generate', SubmitType::class,
            [ 'attr' => ['class' => 'btn-warning']]);
    }
}
