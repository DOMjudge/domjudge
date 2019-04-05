<?php declare(strict_types=1);

namespace DOMJudgeBundle\Form\Type;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\Judgehost;
use DOMJudgeBundle\Entity\Language;
use DOMJudgeBundle\Entity\Problem;
use DOMJudgeBundle\Entity\Team;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;

class RejudgingType extends AbstractType
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * RejudgingType constructor.
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     * @throws \Exception
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('reason', TextType::class);
        $builder->add('contests', EntityType::class, [
            'label' => 'Contest',
            'class' => Contest::class,
            'required' => false,
            'multiple' => true,
            'choice_label' => 'name',
            'query_builder' => function (EntityRepository $er) {
                return $er
                    ->createQueryBuilder('c')
                    ->where('c.enabled = 1')
                    ->orderBy('c.cid');
            },
        ]);
        $builder->add('problems', EntityType::class, [
            'multiple' => true,
            'label' => 'Problem',
            'class' => Problem::class,
            'required' => false,
            'choice_label' => 'name',
            'choices' => [],
        ]);
        $builder->add('languages', EntityType::class, [
            'multiple' => true,
            'label' => 'Language',
            'class' => Language::class,
            'required' => false,
            'choice_label' => 'name',
            'query_builder' => function (EntityRepository $er) {
                return $er
                    ->createQueryBuilder('l')
                    ->where('l.allowSubmit = 1')
                    ->orderBy('l.name');
            },
        ]);
        $builder->add('teams', EntityType::class, [
            'multiple' => true,
            'label' => 'Team',
            'class' => Team::class,
            'required' => false,
            'choice_label' => 'name',
            'choices' => [],
        ]);
        $builder->add('judgehosts', EntityType::class, [
            'multiple' => true,
            'label' => 'Judgehost',
            'class' => Judgehost::class,
            'required' => false,
            'choice_label' => 'hostname',
            'query_builder' => function (EntityRepository $er) {
                return $er
                    ->createQueryBuilder('j')
                    ->orderBy('j.hostname');
            },
        ]);

        $verdicts = [
            'correct',
            'compiler-error',
            'no-output',
            'output-limit',
            'run-error',
            'timelimit',
            'wrong-answer',
        ];
        $builder->add('verdicts', ChoiceType::class, [
            'label' => 'Verdict',
            'multiple' => true,
            'required' => false,
            'choices' => array_combine($verdicts, $verdicts),
        ]);
        $builder->add('before', TextType::class, [
            'label' => 'before (in form ±[HHH]H:MM[:SS[.uuuuuu]])',
            'required' => false,
        ]);
        $builder->add('after', TextType::class, [
            'label' => 'after (in form ±[HHH]H:MM[:SS[.uuuuuu]])',
            'required' => false,
        ]);

        $builder->add('save', SubmitType::class);

        $formProblemModifier = function (FormInterface $form, $contests = []) {
            /** @var Contest[] $contests */
            $problems = $this->em->createQueryBuilder()
                ->from('DOMJudgeBundle:Problem', 'p')
                ->join('p.contest_problems', 'cp')
                ->select('p')
                ->andWhere('cp.contest IN (:contests)')
                ->setParameter(':contests', $contests)
                ->addOrderBy('p.name')
                ->getQuery()
                ->getResult();

            $form->add('problems', EntityType::class, [
                'multiple' => true,
                'label' => 'Problem',
                'class' => Problem::class,
                'required' => false,
                'choice_label' => 'name',
                'choices' => $problems,
            ]);

            $teamsQueryBuilder = $this->em->createQueryBuilder()
                ->from('DOMJudgeBundle:Team', 't')
                ->select('t')
                ->andWhere('t.enabled = 1')
                ->addOrderBy('t.name');

            $anyPublic = false;
            foreach ($contests as $contest) {
                if ($contest->getPublic()) {
                    $anyPublic = true;
                    break;
                }
            }

            if (!$anyPublic) {
                $teamsQueryBuilder
                    ->join('t.contests', 'c')
                    ->andWhere('c IN (:contests)')
                    ->setParameter(':contests', $contests);
            }

            $teams = $teamsQueryBuilder->getQuery()->getResult();

            $form->add('teams', EntityType::class, [
                'multiple' => true,
                'label' => 'Team',
                'class' => Team::class,
                'required' => false,
                'choice_label' => 'name',
                'choices' => $teams,
            ]);
        };

        $builder->addEventListener(FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($formProblemModifier) {
                $data = $event->getData();
                $formProblemModifier($event->getForm(), $data['contests'] ?? []);
            }
        );

        $builder->get('contests')->addEventListener(FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($formProblemModifier) {
                $contests = $event->getForm()->getData();
                $formProblemModifier($event->getForm()->getParent(), $contests);
            }
        );
    }
}
