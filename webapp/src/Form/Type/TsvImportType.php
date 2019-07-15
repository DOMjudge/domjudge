<?php declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class TsvImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('type', ChoiceType::class, [
            'choices' => [
                'groups' => 'groups',
                'teams' => 'teams',
                'accounts' => 'accounts',
            ],
        ]);
        $builder->add('file', BootstrapFileType::class, [
            'required' => true,
        ]);
        $builder->add('import', SubmitType::class);
    }
}
