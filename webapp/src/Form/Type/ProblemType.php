<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Executable;
use App\Entity\Problem;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
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
    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     * @throws \Exception
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->addExternalIdField($builder, Problem::class);
        $builder->add('name', TextType::class);
        $builder->add('timelimit', NumberType::class);
        $builder->add('memlimit', IntegerType::class, [
            'required' => false,
            'help' => 'leave empty for default',
        ]);
        $builder->add('outputlimit', IntegerType::class, [
            'required' => false,
            'help' => 'leave empty for default',
        ]);
        $builder->add('problemtextFile', FileType::class, [
            'label' => 'Problem text',
            'required' => false,
        ]);
        $builder->add('clearProblemtext', CheckboxType::class, [
            'label' => 'Delete problem text',
            'required' => false,
        ]);
        $builder->add('runExecutable', EntityType::class, [
            'label' => 'Run script',
            'class' => Executable::class,
            'required' => false,
            'placeholder' => '-- default run script --',
            'choice_label' => 'description',
            'query_builder' => function (EntityRepository $er) {
                return $er
                    ->createQueryBuilder('e')
                    ->where('e.type = :run')
                    ->setParameter(':run', 'run')
                    ->orderBy('e.execid');
            },
        ]);
        $builder->add('compareExecutable', EntityType::class, [
            'label' => 'Compare script',
            'class' => Executable::class,
            'required' => false,
            'placeholder' => '-- default compare script --',
            'choice_label' => 'description',
            'query_builder' => function (EntityRepository $er) {
                return $er
                    ->createQueryBuilder('e')
                    ->where('e.type = :compare')
                    ->setParameter(':compare', 'compare')
                    ->orderBy('e.execid');
            },
        ]);
        $builder->add('specialCompareArgs', TextType::class, [
            'label' => 'Compare script arguments',
            'required' => false,
        ]);
        $builder->add('combinedRunCompare', CheckboxType::class, [
            'label' => 'Use run script as compare script.',
            'required' => false,
        ]);
        $builder->add('save', SubmitType::class);

        // Remove clearProblemtext field when we do not have a problem text
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var Problem|null $problem */
            $problem = $event->getData();
            $form    = $event->getForm();

            if ($problem && !$problem->getProblemtext()) {
                $form->remove('clearProblemtext');
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => Problem::class]);
    }
}
