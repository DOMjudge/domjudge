<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\TeamCategory;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Regex;

class TeamCategoryType extends AbstractExternalIdEntityType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addExternalIdField($builder, TeamCategory::class);
        $builder->add('icpcid', TextType::class, [
            'label'       => 'ICPC ID',
            'required'    => false,
            'help'        => 'Optional ID of the category in the ICPC CMS.',
            'constraints' => [
                new Regex(
                    [
                        'pattern' => '/^[a-zA-Z0-9_-]+$/i',
                        'message' => 'Only letters, numbers, dashes and underscores are allowed.',
                    ]
                )
            ]
        ]);
        $builder->add('name');
        $builder->add('sortorder', IntegerType::class);
        $builder->add('color', TextType::class, [
            'required' => false,
            'attr' => [
                'data-color-picker' => '',
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


    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => TeamCategory::class]);
    }
}
