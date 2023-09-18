<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Language;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class ProblemAttachmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('content', FileType::class, [
            'required' => true,
        ]);
        $builder->add('language', EntityType::class, [
            'class' => Language::class,
            'required' => false,
            'query_builder' => fn(EntityRepository $er) => $er
                ->createQueryBuilder('l')
                ->andWhere('l.allowSubmit = 1'),
            'choice_label' => 'name',
            'placeholder' => 'Select a language (optional)',
        ]);
        $builder->add('add', SubmitType::class, [
            'attr' => [
                'class' => 'btn-sm btn-primary',
            ],
        ]);
    }
}
