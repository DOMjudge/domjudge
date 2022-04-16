<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Contest;
use App\Entity\Judgehost;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints\Regex;

class RejudgingType extends AbstractType
{
    protected EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('reason', TextType::class);
        $builder->add('priority', ChoiceType::class,
            [
                'choices' => [
                    'low' => 'low',
                    'default' => 'default',
                    'high' => 'high',
                ],
                'data' => 'default',
            ]
        );
        $builder->add('repeat', IntegerType::class, [
            'label' => 'Number of times to repeat this rejudging',
            'data' => 1,
            'attr' => ['min' => 1, 'max' => 99]
        ]);
        $builder->add('contests', EntityType::class, [
            'label' => 'Contest',
            'class' => Contest::class,
            'required' => false,
            'multiple' => true,
            'choice_label' => 'name',
            'query_builder' => fn(EntityRepository $er) => $er
                ->createQueryBuilder('c')
                ->where('c.enabled = 1')
                ->orderBy('c.cid'),
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
            'query_builder' => fn(EntityRepository $er) => $er
                ->createQueryBuilder('l')
                ->where('l.allowSubmit = 1')
                ->orderBy('l.name'),
        ]);
        $builder->add('teams', EntityType::class, [
            'multiple' => true,
            'label' => 'Team',
            'class' => Team::class,
            'required' => false,
            'choice_label' => 'name',
            'choices' => [],
        ]);
        $builder->add('users', EntityType::class, [
            'label' => 'User',
            'class' => User::class,
            'required' => false,
            'multiple' => true,
            'choice_label' => 'name',
            'query_builder' => fn(EntityRepository $er) => $er
                ->createQueryBuilder('u')
                ->where('u.enabled = 1')
                ->orderBy('u.name'),
        ]);
        $builder->add('judgehosts', EntityType::class, [
            'multiple' => true,
            'label' => 'Judgehost',
            'class' => Judgehost::class,
            'required' => false,
            'choice_label' => 'hostname',
            'query_builder' => fn(EntityRepository $er) => $er
                ->createQueryBuilder('j')
                ->orderBy('j.hostname'),
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
        $relativeTimeConstraints = [
            new Regex([
                'pattern' => '/^[+-][0-9]+:[0-9]{2}(:[0-9]{2}(\.[0-9]{0,6})?)?$/',
                'message' => 'Invalid relative time format'
            ])
        ];
        $builder->add('after', TextType::class, [
            'label' => 'after',
            'required' => false,
            'constraints' => $relativeTimeConstraints,
            'help' => 'in form ±[HHH]H:MM[:SS[.uuuuuu]], contest relative time',
        ]);
        $builder->add('before', TextType::class, [
            'label' => 'before',
            'required' => false,
            'constraints' => $relativeTimeConstraints,
            'help' => 'in form ±[HHH]H:MM[:SS[.uuuuuu]], contest relative time',
        ]);

        $builder->add('save', SubmitType::class);

        $formProblemModifier = function (FormInterface $form, $contests = []) {
            /** @var Contest[] $contests */
            $problems = $this->em->createQueryBuilder()
                ->from(Problem::class, 'p')
                ->join('p.contest_problems', 'cp')
                ->select('p')
                ->andWhere('cp.contest IN (:contests)')
                ->setParameter('contests', $contests)
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
                ->from(Team::class, 't')
                ->select('t')
                ->andWhere('t.enabled = 1')
                ->addOrderBy('t.name');

            $selectAllTeams = false;
            foreach ($contests as $contest) {
                if ($contest->isOpenToAllTeams()) {
                    $selectAllTeams = true;
                    break;
                }
            }

            if (!$selectAllTeams) {
                $teamsQueryBuilder
                    ->leftJoin('t.contests', 'c')
                    ->join('t.category', 'cat')
                    ->leftJoin('cat.contests', 'cc')
                    ->andWhere('c IN (:contests) OR cc IN (:contests)')
                    ->setParameter('contests', $contests);
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
