<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\TeamCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TeamCategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name');
        $builder->add('sortorder', IntegerType::class);
        $builder->add('color', TextType::class, [
            'required' => false,
            'attr' => [
                'class' => 'color {required:false,adjust:false,hash:true,caps:false}',
            ],
            'help' => '<a target="_blank" href="https://en.wikipedia.org/wiki/Web_colors"><i class="fas fa-question-circle"></i></a>',
            'help_html' => true,
        ]);
        $builder->add('visible', ChoiceType::class, [
            'expanded' => true,
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
        ]);
        $builder->add('allow_self_registration', ChoiceType::class, [
            'label' => 'Allow self-registration',
            'expanded' => true,
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
        ]);
        $builder->add('save', SubmitType::class);
    }


    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => TeamCategory::class]);
    }
}
