<?php declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class ContestImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('file', BootstrapFileType::class, [
            'required' => true,
            'help' => 'Importing a contest may overwrite some settings (e.g. penalty time, clarification categories, clarification answers, etc.). This action can not be undone.',
        ]);
        $builder->add('import', SubmitType::class, ['icon' => 'fa-upload']);
    }
}
