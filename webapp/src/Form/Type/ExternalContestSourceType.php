<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Contest;
use App\Entity\ExternalContestSource;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExternalContestSourceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('type', ChoiceType::class, [
            'choices' => [
                ExternalContestSource::readableType(ExternalContestSource::TYPE_CCS_API)         => ExternalContestSource::TYPE_CCS_API,
                ExternalContestSource::readableType(ExternalContestSource::TYPE_CONTEST_PACKAGE) => ExternalContestSource::TYPE_CONTEST_PACKAGE,
            ],
        ]);
        $builder->add('source', TextType::class, [
            'help' => 'For contest package: directory on disk to use. For CCS API: URL to contest in API.'
        ]);
        $builder->add('username', TextType::class, [
            'required' => false,
        ]);
        $builder->add('password', TextType::class, [
            'required' => false,
        ]);
        $builder->add('save', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => ExternalContestSource::class]);
    }
}
