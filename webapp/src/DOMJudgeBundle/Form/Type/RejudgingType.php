<?php declare(strict_types=1);

namespace DOMJudgeBundle\Form\Type;

use Doctrine\ORM\EntityRepository;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\Judgehost;
use DOMJudgeBundle\Entity\Language;
use DOMJudgeBundle\Entity\Problem;
use DOMJudgeBundle\Entity\Rejudging;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class RejudgingType extends AbstractType
{
    /**
     * @var DOMJudgeService
     */
    private $DOMJudgeService;

    public function __construct(DOMJudgeService $DOMJudgeService) {
        $this->DOMJudgeService = $DOMJudgeService;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     * @throws \Exception
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $currentContest = $this->DOMJudgeService->getCurrentContest();
        $builder->add('reason', TextType::class);
        $builder->add('contest', EntityType::class, [
            'mapped' => false,
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
            'data' => [$currentContest],
        ]);
        $problems = array_map(function($contestProblem) {
            return $contestProblem->getProblem();
          },
          $currentContest->getProblems()->getValues()
        );
        $builder->add('problem', EntityType::class, [
            'mapped' => false,
            'multiple' => true,
            'label' => 'Problem',
            'class' => Problem::class,
            'required' => false,
            'choice_label' => 'name',
            'query_builder' => function (EntityRepository $er) {
                return $er
                    ->createQueryBuilder('p')
                    ->orderBy('p.name');
            },
            'data' => $problems,
        ]);
        $builder->add('language', EntityType::class, [
            'mapped' => false,
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
        $builder->add('team', EntityType::class, [
            'mapped' => false,
            'multiple' => true,
            'label' => 'Team',
            'class' => Team::class,
            'required' => false,
            'choice_label' => 'name',
            'query_builder' => function (EntityRepository $er) {
                return $er
                    ->createQueryBuilder('t')
                    ->where('t.enabled = 1')
                    ->orderBy('t.name');
            },
        ]);
        $builder->add('judgehost', EntityType::class, [
            'mapped' => false,
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
        $incorrectVerdicts = array_filter($verdicts, function($k) {
            return $k != 'correct';
        });
        $builder->add('verdict', ChoiceType::class, [
            'label' => 'Verdict',
            'mapped' => false,
            'multiple' => true,
            'required' => false,
            'choices' => array_combine($verdicts, $verdicts),
            'data' => $incorrectVerdicts,
        ]);
        $builder->add('before', TextType::class, [
            'label' => 'before (in form ±[HHH]H:MM[:SS[.uuuuuu]])',
            'required' => false,
            'mapped' => false,
        ]);
        $builder->add('after', TextType::class, [
            'label' => 'after (in form ±[HHH]H:MM[:SS[.uuuuuu]])',
            'required' => false,
            'mapped' => false,
        ]);

        $builder->add('save', SubmitType::class);
    }
}
