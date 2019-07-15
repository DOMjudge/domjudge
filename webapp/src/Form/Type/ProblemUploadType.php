<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Contest;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class ProblemUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('contest', EntityType::class, [
            'class' => Contest::class,
            'required' => false,
            'placeholder' => 'Do not add / update contest data',
            'choice_label' => function (Contest $contest) {
                return sprintf('c%d: %s - %s', $contest->getCid(), $contest->getShortname(), $contest->getName());
            },
        ]);
        $builder->add('archive', FileType::class, [
            'required' => true,
            'label' => 'Problem archive',
            'attr' => [
                'accept' => 'application/zip',
            ],
        ]);
        $builder->add('upload', SubmitType::class);
    }
}
