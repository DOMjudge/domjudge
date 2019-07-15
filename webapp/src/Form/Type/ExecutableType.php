<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Executable;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExecutableType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('execid', TextType::class, [
            'label' => 'ID',
        ]);
        $builder->add('description');
        $builder->add('type', ChoiceType::class, [
            'choices' => [
                'compare' => 'compare',
                'compile' => 'compile',
                'run' => 'run',
            ]
        ]);
        $builder->add('save', SubmitType::class);

        // Remove ID field when doing an edit
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var Executable|null $executable */
            $executable = $event->getData();
            $form       = $event->getForm();

            if ($executable && $executable->getExecid() !== null) {
                $form->remove('execid');
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => Executable::class]);
    }
}
