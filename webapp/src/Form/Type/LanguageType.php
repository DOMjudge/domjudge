<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Executable;
use App\Entity\Language;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LanguageType extends AbstractExternalIdEntityType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     * @throws \Exception
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->addExternalIdField($builder, Language::class);
        $builder->add('langid', TextType::class, [
            'label' => 'Language ID/ext',
        ]);
        $builder->add('name', TextType::class);
        $builder->add('requireEntryPoint', ChoiceType::class, [
            'expanded' => true,
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
        ]);
        $builder->add('entryPointDescription', TextType::class, [
            'required' => false,
        ]);
        $builder->add('allowSubmit', ChoiceType::class, [
            'expanded' => true,
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
        ]);
        $builder->add('allowJudge', ChoiceType::class, [
            'expanded' => true,
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
        ]);
        $builder->add('timeFactor', TextType::class);
        $builder->add('compileExecutable', EntityType::class, [
            'label' => 'Compile script',
            'class' => Executable::class,
            'required' => false,
            'placeholder' => '-- no executable --',
            'choice_label' => 'execid',
            'query_builder' => function (EntityRepository $er) {
                return $er
                    ->createQueryBuilder('e')
                    ->where('e.type = :compile')
                    ->setParameter(':compile', 'compile')
                    ->orderBy('e.execid');
            },
        ]);
        $builder->add('extensions', CollectionType::class, [
            'error_bubbling' => false,
            'entry_type' => TextType::class,
            'entry_options' => ['label' => false],
            'allow_add' => true,
            'allow_delete' => true,
        ]);
        $builder->add('filterCompilerFiles', ChoiceType::class, [
            'label' => 'Filter files passed to compiler by extension list',
            'expanded' => true,
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
        ]);
        $builder->add('save', SubmitType::class);

        // Remove ID field when doing an edit
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var Language|null $language */
            $language = $event->getData();
            $form     = $event->getForm();

            if ($language && $language->getLangid() !== null) {
                $form->remove('langid');
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => Language::class]);
    }
}
