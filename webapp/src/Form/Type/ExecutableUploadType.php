<?php declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class ExecutableUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('type', ChoiceType::class, [
            'choices' => [
                'compare' => 'compare',
                'compile' => 'compile',
                'run' => 'run',
            ],
        ]);
        $builder->add('archives', FileType::class, [
            'required' => true,
            'multiple' => true,
            'label' => 'Archive(s)',
            'attr' => [
                'accept' => 'application/zip',
            ],
        ]);
        $builder->add('upload', SubmitType::class);
    }
}
