<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\ExternalContestSource;
use App\Entity\ExternalContestSourceType as ExternalContestSourceTypeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExternalContestSourceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('type', EnumType::class, [
            'class' => ExternalContestSourceTypeEnum::class,
            'choice_label' => 'readable',
        ]);
        $builder->add('source', TextType::class, [
            'help' => 'For contest package: directory on disk to use. For CCS API: URL to contest in API.',
        ]);
        $builder->add('username', TextType::class, [
            'required' => false,
        ]);
        $builder->add('password', TextType::class, [
            'required' => false,
        ]);
        $builder->add('save', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ExternalContestSource::class]);
    }
}
