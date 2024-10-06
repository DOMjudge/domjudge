<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\ContestProblem;
use App\Entity\Team;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotEqualTo;

class JuryClarificationType extends AbstractType
{
    public const RECIPIENT_MUST_SELECT = 'domjudge-must-select';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ConfigurationService $config,
        private readonly DOMJudgeService $dj,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $recipientOptions = [
            '(select...)' => static::RECIPIENT_MUST_SELECT,
            'ALL' => '',
        ];

        $limitToTeam = $options['limit_to_team'] ?? null;
        if ($limitToTeam) {
            $recipientOptions[$this->getTeamLabel($limitToTeam)] = $limitToTeam->getTeamid();
        } else {
            /** @var Team|null $limitToTeam */
            $teams = $this->em->getRepository(Team::class)->findAll();
            foreach ($teams as $team) {
                $recipientOptions[$this->getTeamLabel($team)] = $team->getTeamid();
            }
        }

        $subjectOptions = [];
        $subjectGroupBy = null;

        $categories = $this->config->get('clar_categories');
        $contest = $this->dj->getCurrentContest();
        $hasCurrentContest = $contest !== null;
        if ($hasCurrentContest) {
            $contests = [$contest->getCid() => $contest];
        } else {
            $contests = $this->dj->getCurrentContests();
        }

        /** @var ContestProblem[] $contestproblems */
        $contestproblems = $this->em->createQueryBuilder()
            ->from(ContestProblem::class, 'cp')
            ->select('cp, p')
            ->innerJoin('cp.problem', 'p')
            ->where('cp.contest IN (:contests)')
            ->setParameter('contests', $contests)
            ->orderBy('cp.shortname')
            ->getQuery()->getResult();

        foreach ($contests as $cid => $cdata) {
            $contestShortName = $cdata->getShortName();
            $namePrefix = '';
            if (!$hasCurrentContest) {
                $namePrefix = $contestShortName . ' - ';
                $subjectGroupBy = function (string $choice, string $key) {
                    return substr($key, 0, strpos($key, '-'));
                };
            }
            foreach ($categories as $name => $desc) {
                $subjectOptions["$namePrefix $desc"] = "$cid-$name";
            }

            foreach ($contestproblems as $cp) {
                if ($cp->getCid() != $cid) {
                    continue;
                }
                $subjectOptions[$namePrefix . $cp->getShortname() . ': ' . $cp->getProblem()->getName()] = "$cid-" . $cp->getProbid();
            }
        }

        $builder->add('recipient', ChoiceType::class, [
            'label' => 'Send to',
            'choices' => $recipientOptions,
            'constraints' => [
                new NotEqualTo('domjudge-must-select', message: 'You must select somewhere to send the clarification to.'),
            ],
        ]);

        $builder->add('subject', ChoiceType::class, [
            'choices' => $subjectOptions,
            'group_by' => $subjectGroupBy,
        ]);

        $builder->add('message', TextareaType::class, [
            'attr' => [
                'rows' => 5,
                'cols' => 85,
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('limit_to_team', null);
    }

    private function getTeamLabel(Team $team): string
    {
        if ($team->getLabel()) {
            return sprintf('%s (%s)', $team->getEffectiveName(), $team->getLabel());
        }

        return sprintf('%s (%s)', $team->getEffectiveName(), $team->getExternalId());
    }
}
