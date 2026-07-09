<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Clarification;
use App\Entity\ContestProblem;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;

class TeamClarificationType extends AbstractType
{
    public function __construct(
        protected readonly DOMJudgeService $dj,
        protected readonly ConfigurationService $config
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('recipient', TextType::class, [
            'data' => 'Jury',
            'disabled' => true,
        ]);

        $subjects = [];
        /** @var string[] $categories */
        $categories = $this->config->get('clar_categories');
        $user = $this->dj->getUser();
        $contest = $this->dj->getCurrentContest($user->getTeam()->getTeamid());
        if ($contest) {
            foreach ($categories as $categoryId => $categoryName) {
                $subjects[$categoryName] = sprintf('%s%s%s', $contest->getExternalid(), Clarification::CATEGORY_BASED_SEPARATOR, $categoryId);
            }
            if ($contest->getFreezeData()->started()) {
                /** @var ContestProblem $problem */
                foreach ($contest->getProblems() as $problem) {
                    if ($problem->getAllowSubmit()) {
                        $problemName = sprintf('%s: %s', $problem->getShortname(),
                            $problem->getProblem()->getName());
                        $subjects[$problemName] = sprintf('%s%s%s', $contest->getExternalid(), Clarification::PROBLEM_BASED_SEPARATOR, $problem->getExternalId());
                    }
                }
            }
        }

        $builder->add('subject', ChoiceType::class, [
            'choices' => $subjects,
        ]);
        $maxLength = $this->config->get('clar_max_body_length');
        $constraints = [];
        if ($maxLength > 0) {
            $constraints[] = new Length(max: $maxLength, maxMessage: 'Clarification body is too long: {{ value }} characters, maximum is {{ limit }}.');
        }
        $builder->add('message', TextareaType::class, [
            'attr' => [
                'rows' => 5,
                'cols' => 85,
            ],
            'constraints' => $constraints,
        ]);
    }
}
