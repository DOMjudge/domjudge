<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Contest;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProblemUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('contest', EntityType::class, [
            'class' => Contest::class,
            'required' => false,
            'placeholder' => 'Do not add / update contest data',
            'choice_label' => fn(Contest $contest) => sprintf(
                'c%d: %s - %s', $contest->getCid(), $contest->getShortname(), $contest->getName()
            ),
        ]);
        $builder->add('archive', FileType::class, [
            'required' => true,
            'label' => 'Problem archive',
            'attr' => [
                'accept' => 'application/zip',
            ],
        ]);
        if ($options['show_delete_old_data']) {
            $builder->add('delete_old_data', CheckboxType::class, [
                'help'     => 'If checked, old data will be deleted.',
                'required' => false,
            ]);
        }
        $builder->add('upload', SubmitType::class, ['label' => 'Import', 'icon' => 'fa-upload']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['show_delete_old_data' => false]);
    }
}
