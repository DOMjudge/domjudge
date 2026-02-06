<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Executable;
use App\Entity\Language;
use App\Entity\Problem;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProblemType extends AbstractExternalIdEntityType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addExternalIdField($builder, Problem::class);
        $builder->add('name', TextType::class, [
            'empty_data' => ''
        ]);
        $builder->add('timelimit', NumberType::class, [
            'label' => 'Time',
            'input_group_after' => 'sec',
        ]);
        $builder->add('memlimit', IntegerType::class, [
            'label' => 'Memory',
            'required' => false,
            'help' => 'leave empty for default',
            'input_group_after' => 'kB',
        ]);
        $builder->add('outputlimit', IntegerType::class, [
            'label' => 'Output size',
            'required' => false,
            'help' => 'leave empty for default',
            'input_group_after' => 'kB',
        ]);
        $builder->add('problemstatementFile', FileType::class, [
            'label' => 'Problem statement',
            'required' => false,
            'attr' => [
                'accept' => 'text/html,text/plain,application/pdf',
            ],
        ]);
        $builder->add('clearProblemstatement', CheckboxType::class, [
            'label' => 'Delete problem statement',
            'required' => false,
        ]);
        $builder->add('runExecutable', EntityType::class, [
            'label' => 'Run script',
            'class' => Executable::class,
            'required' => false,
            'placeholder' => '-- default run script --',
            'choice_label' => 'description',
            'query_builder' => fn(EntityRepository $er) => $er
                ->createQueryBuilder('e')
                ->where('e.type = :run')
                ->setParameter('run', 'run')
                ->orderBy('e.execid'),
        ]);
        $builder->add('compareExecutable', EntityType::class, [
            'label' => 'Compare script',
            'class' => Executable::class,
            'required' => false,
            'placeholder' => '-- default compare script --',
            'choice_label' => 'description',
            'query_builder' => fn(EntityRepository $er) => $er
                ->createQueryBuilder('e')
                ->where('e.type = :compare')
                ->setParameter('compare', 'compare')
                ->orderBy('e.execid'),
        ]);
        $builder->add('specialCompareArgs', TextType::class, [
            'label' => 'Compare script arguments',
            'required' => false,
        ]);
        $builder->add('types', ChoiceType::class, [
            'choices' => [
                'pass-fail' => Problem::TYPE_PASS_FAIL,
                'interactive' => Problem::TYPE_INTERACTIVE,
                'multipass' => Problem::TYPE_MULTI_PASS,
                'scoring' => Problem::TYPE_SCORING,
                'submit-answer' => Problem::TYPE_SUBMIT_ANSWER,
            ],
            'multiple' => true,
            'required' => true,
        ]);
        $builder->add('multipassLimit', IntegerType::class, [
            'label' => 'Multi-pass limit',
            'required' => false,
            'help' => 'leave empty for default',
        ]);
        $builder->add('languages', EntityType::class, [
            'required' => false,
            'class' => Language::class,
            'multiple' => true,
            'choice_value' => 'externalid',
            'choice_label' => fn(Language $language) => sprintf('%s (%s)', $language->getName(), $language->getExternalid()),
            'help' => 'List of languages that can be used for this problem. Leave empty to allow all languages that are enabled for this contest.',
        ]);
        $builder->add('save', SubmitType::class);

        // Remove clearProblemstatement field when we do not have a problem text.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            /** @var Problem|null $problem */
            $problem = $event->getData();
            $form    = $event->getForm();

            if ($problem && !$problem->getProblemstatement()) {
                $form->remove('clearProblemstatement');
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Problem::class]);
    }
}
