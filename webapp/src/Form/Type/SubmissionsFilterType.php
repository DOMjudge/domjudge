<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class SubmissionsFilterType extends AbstractType
{
    public function __construct(
        protected readonly ConfigurationService $config,
        protected readonly DOMJudgeService $dj,
        protected readonly EntityManagerInterface $em,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $contests = $builder->getData()["contests"];

        $problems = $this->em
            ->createQueryBuilder()
            ->from(Problem::class, "p")
            ->join("p.contest_problems", "cp")
            ->select("p")
            ->andWhere("cp.contest IN (:contests)")
            ->setParameter(":contests", $contests)
            ->addOrderBy("p.name")
            ->getQuery()
            ->getResult();
        $builder->add("problem_id", EntityType::class, [
            "multiple" => true,
            "label" => "Filter on problem(s)",
            "class" => Problem::class,
            "required" => false,
            "choice_label" => "name",
            "choices" => $problems,
            "attr" => ["data-filter-field" => "problem-id"],
        ]);
        $builder->add("language_id", EntityType::class, [
            "multiple" => true,
            "label" => "Filter on language(s)",
            "class" => Language::class,
            "required" => false,
            "choice_label" => "name",
            "query_builder" => fn(EntityRepository $er) => $er
                ->createQueryBuilder("l")
                ->where("l.allowSubmit = 1")
                ->orderBy("l.name"),
            "attr" => ["data-filter-field" => "language-id"],
        ]);
        $builder->add("category_id", EntityType::class, [
            "multiple" => true,
            "label" => "Filter on category(s)",
            "class" => TeamCategory::class,
            "required" => false,
            "choice_label" => "name",
            "query_builder" => fn(EntityRepository $er) => $er
                ->createQueryBuilder("tc")
                ->orderBy("tc.name"),
            "attr" => ["data-filter-field" => "category-id"],
        ]);
        $builder->add("affiliation_id", EntityType::class, [
            "multiple" => true,
            "label" => "Filter on affiliation(s)",
            "class" => TeamAffiliation::class,
            "required" => false,
            "choice_label" => "name",
            "query_builder" => fn(EntityRepository $er) => $er
                ->createQueryBuilder("ta")
                ->orderBy("ta.name"),
            "attr" => ["data-filter-field" => "affiliation-id"],
        ]);

        $teamsQueryBuilder = $this->em
            ->createQueryBuilder()
            ->from(Team::class, "t")
            ->select("t")
            ->andWhere("t.enabled = 1")
            ->addOrderBy("t.name");

        $selectAllTeams = false;
        foreach ($contests as $contest) {
            if ($contest->isOpenToAllTeams()) {
                $selectAllTeams = true;
                break;
            }
        }

        if (!$selectAllTeams) {
            $teamsQueryBuilder
                ->leftJoin("t.contests", "c")
                ->join("t.category", "cat")
                ->leftJoin("cat.contests", "cc")
                ->andWhere("c IN (:contests) OR cc IN (:contests)")
                ->setParameter(":contests", $contests);
        }

        $teams = $teamsQueryBuilder->getQuery()->getResult();
        $builder->add("team_id", EntityType::class, [
            "multiple" => true,
            "label" => "Filter on team(s)",
            "class" => Team::class,
            "required" => false,
            "choice_label" => "name",
            "choices" => $teams,
            "attr" => ["data-filter-field" => "team-id"],
        ]);

        $verdicts = array_keys($this->config->getVerdicts(['final', 'error', 'in_progress']));
        $builder->add("result", ChoiceType::class, [
            "label" => "Filter on result(s)",
            "multiple" => true,
            "required" => false,
            "choices" => array_combine($verdicts, $verdicts),
            "attr" => ["data-filter-field" => "result"],
        ]);

        $builder->add("apply", SubmitType::class, [
            "label" => "Apply filters",
        ]);

        $builder->add("clear", SubmitType::class, [
            "label" => "Clear all filters",
            "attr" => ["class" => "btn-secondary"],
        ]);
    }
}
