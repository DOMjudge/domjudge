<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\TeamCategory;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
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
                    pattern: '/^[a-zA-Z0-9_-]+$/i',
                    message: 'Only letters, numbers, dashes and underscores are allowed.',
                )
            ]
        ]);
        $builder->add('name', null, ['empty_data' => '']);

        $builder->add('types', ChoiceType::class, [
            'label' => 'Category Types',
            'choices' => array_flip(TeamCategory::TYPES_TO_HUMAN_STRING),
            'multiple' => true,
            'expanded' => false,
            'required' => false,
            'help'     => 'Leave empty to use only for categorization.'
        ]);

        $builder->add('sortorder', IntegerType::class, [
            'required' => false,
            'attr' => [
                'data-conditional-field' => 'types',
                'data-conditional-field-value' => TeamCategory::TYPE_SCORING,
            ],
        ]);

        $builder->add('color', TextType::class, [
            'required' => false,
            'attr' => [
                'data-color-picker' => '',
                'data-conditional-field' => 'types',
                'data-conditional-field-value' => TeamCategory::TYPE_BACKGROUND,
            ],
            'help' => '<a target="_blank" href="https://en.wikipedia.org/wiki/Web_colors"><i class="fas fa-question-circle"></i></a>',
            'help_html' => true,
        ]);

        $builder->add('css_class', TextType::class, [
            'label' => 'CSS Class',
            'required' => false,
            'attr' => [
                'data-conditional-field' => 'types',
                'data-conditional-field-value' => TeamCategory::TYPE_CSS_CLASS,
            ],
            'help' => 'CSS class to apply to scoreboard rows for teams in this category.',
        ]);
        $builder->add('visible', CheckboxType::class, [
            'required' => false,
            'attr' => [
                'data-toggle' => 'toggle',
                'data-size' => 'mini',
                'data-on' => 'Yes',
                'data-off' => 'No',
            ],
        ]);
        $builder->add('allow_self_registration', CheckboxType::class, [
            'label' => 'Allow self-registration',
            'required' => false,
            'attr' => [
                'data-toggle' => 'toggle',
                'data-size' => 'mini',
                'data-on' => 'Yes',
                'data-off' => 'No',
            ],
        ]);
        $builder->add('allow_password_change', ChoiceType::class, [
            'label' => 'Allow password change',
            'expanded' => true,
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
            'help' => 'Allow users in this category to change their own password.',
        ]);
        $builder->add('save', SubmitType::class);
    }


    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => TeamCategory::class]);
    }
}
