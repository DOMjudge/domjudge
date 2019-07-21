<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Language;
use App\Entity\Problem;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class SubmitProblemType extends AbstractType
{
    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    public function __construct(DOMJudgeService $dj, EntityManagerInterface $em)
    {
        $this->dj = $dj;
        $this->em = $em;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $allowMultipleFiles = $this->dj->dbconfig_get('sourcefiles_limit', 100) > 1;
        $user               = $this->dj->getUser();
        $contest            = $this->dj->getCurrentContest($user->getTeamid());

        $builder->add('code', BootstrapFileType::class, [
            'label' => 'Source file' . ($allowMultipleFiles ? 's' : ''),
            'multiple' => $allowMultipleFiles,
        ]);

        $builder->add('problem', EntityType::class, [
            'class' => Problem::class,
            'query_builder' => function (EntityRepository $er) use ($contest) {
                return $er->createQueryBuilder('p')
                    ->join('p.contest_problems', 'cp', Join::WITH, 'cp.contest = :contest')
                    ->select('p', 'cp')
                    ->andWhere('cp.allowSubmit = 1')
                    ->setParameter(':contest', $contest)
                    ->addOrderBy('cp.shortname');
            },
            'choice_label' => function (Problem $problem) {
                return sprintf('%s - %s', $problem->getContestProblems()->first()->getShortName(), $problem->getName());
            },
            'placeholder' => 'Select a problem',
        ]);

        $builder->add('language', EntityType::class, [
            'class' => Language::class,
            'query_builder' => function (EntityRepository $er) {
                return $er->createQueryBuilder('l')
                    ->andWhere('l.allowSubmit = 1');
            },
            'choice_label' => 'name',
            'placeholder' => 'Select a language',
        ]);

        $builder->add('entry_point', TextType::class, [
            'label' => 'Entry point',
            'required' => false,
            'help' => 'The entry point for your code.',
            'constraints' => [
                new Callback(function ($value, ExecutionContextInterface $context) {
                    /** @var Form $form */
                    $form = $context->getRoot();
                    /** @var Language $language */
                    $language = $form->get('language')->getData();
                    if ($language->getRequireEntryPoint() && empty($value)) {
                        $message = sprintf('%s required, but not specified',
                                           $language->getEntryPointDescription() ?: 'Entry point');
                        $context
                            ->buildViolation($message)
                            ->atPath('entry_point')
                            ->addViolation();
                    }
                }),
            ]
        ]);
    }
}
