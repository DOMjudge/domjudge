<?php declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class ProblemAttachmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('content', BootstrapFileType::class, [
            'required' => true,
        ]);
        $builder->add('add', SubmitType::class, [
            'attr' => [
                'class' => 'btn-sm btn-primary',
            ],
        ]);
    }
}
