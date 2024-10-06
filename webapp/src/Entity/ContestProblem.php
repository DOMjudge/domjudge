<?php declare(strict_types=1);
namespace App\Entity;

use App\Controller\API\AbstractRestController as ARC;
use App\Service\DOMJudgeService as DJS;
use App\Utils\Utils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Many-to-Many mapping of contests and problems.
 */
#[ORM\Entity]
#[ORM\Table(
    name: 'contestproblem',
    options: [
        'collation' => 'utf8mb4_unicode_ci',
        'charset' => 'utf8mb4',
        'comment' => 'Many-to-Many mapping of contests and problems',
    ]
)]
#[ORM\Index(columns: ['cid'], name: 'cid')]
#[ORM\Index(columns: ['probid'], name: 'probid')]
#[ORM\UniqueConstraint(name: 'shortname', columns: ['cid', 'shortname'], options: ['lengths' => [null, 190]])]
#[Serializer\VirtualProperty(
    name: 'probid',
    exp: 'object.getProblem().getProbid()',
    options: [new Serializer\Groups([ARC::GROUP_NONSTRICT])]
)]

#[Serializer\VirtualProperty(
    name: 'short_name',
    exp: 'object.getShortname()',
    options: [new Serializer\Groups([ARC::GROUP_NONSTRICT]), new Serializer\Type('string')]
)]
class ContestProblem extends BaseApiEntity
{
    #[ORM\Column(options: [
        'comment' => 'Unique problem ID within contest, used to sort problems in the scoreboard and typically a single letter',
    ])]
    #[Assert\NotBlank]
    #[Serializer\SerializedName('label')]
    private string $shortname;

    #[ORM\Column(options: [
            'comment' => 'Number of points earned by solving this problem',
            'unsigned' => true,
            'default' => 1,
    ])]
    #[Assert\GreaterThanOrEqual(0)]
    #[Serializer\Exclude]
    private int $points = 1;

    #[ORM\Column(options: ['comment' => 'Are submissions accepted for this problem?', 'default' => 1])]
    #[Serializer\Exclude]
    private bool $allowSubmit = true;

    #[ORM\Column(options: ['comment' => 'Are submissions for this problem judged?', 'default' => 1])]
    #[Serializer\Exclude]
    private bool $allowJudge = true;

    #[ORM\Column(
        length: 32,
        nullable: true,
        options: ['comment' => 'Balloon colour to display on the scoreboard']
    )]
    #[Serializer\Exclude]
    private ?string $color = null;

    #[ORM\Column(
        nullable: false,
        options: ['comment' => 'Whether to do lazy evaluation for this problem; if set this overrides the global configuration setting', 'unsigned' => true]
    )]
    #[Serializer\Exclude]
    private int $lazyEvalResults = 0;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'problems')]
    #[ORM\JoinColumn(name: 'cid', referencedColumnName: 'cid', onDelete: 'CASCADE')]
    #[Serializer\Exclude]
    private ?Contest $contest = null;

    #[ORM\Id]
    #[ORM\ManyToOne(fetch: 'EAGER', inversedBy: 'contest_problems')]
    #[ORM\JoinColumn(name: 'probid', referencedColumnName: 'probid', onDelete: 'CASCADE')]
    #[Serializer\Inline]
    private ?Problem $problem = null;

    /**
     * @var Collection<int, Submission>
     */
    #[ORM\OneToMany(mappedBy: 'contest_problem', targetEntity: Submission::class)]
    #[Serializer\Exclude]
    private Collection $submissions;

    public function __construct()
    {
        $this->submissions = new ArrayCollection();
    }

    public function getCid(): int
    {
        return $this->getContest()->getCid();
    }

    public function getProbid(): int
    {
        return $this->getProblem()->getProbid();
    }

    public function setShortname(string $shortname): ContestProblem
    {
        $this->shortname = $shortname;
        return $this;
    }

    public function getShortname(): string
    {
        return $this->shortname;
    }

    public function getShortDescription(): string
    {
        return $this->getShortname();
    }

    public function setPoints(int $points): ContestProblem
    {
        $this->points = $points;
        return $this;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setAllowSubmit(bool $allowSubmit): ContestProblem
    {
        $this->allowSubmit = $allowSubmit;
        return $this;
    }

    public function getAllowSubmit(): bool
    {
        return $this->allowSubmit;
    }

    public function setAllowJudge(bool $allowJudge): ContestProblem
    {
        $this->allowJudge = $allowJudge;
        return $this;
    }

    public function getAllowJudge(): bool
    {
        return $this->allowJudge;
    }

    public function setColor(?string $color): ContestProblem
    {
        $this->color = $color;
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('rgb')]
    public function getApiRgb(): ?string
    {
        if (!$this->getColor()) {
            return null;
        }

        return Utils::convertToHex($this->getColor());
    }

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('color')]
    public function getApiColor(): ?string
    {
        if (!$this->getColor()) {
            return null;
        }

        return Utils::convertToColor($this->getColor());
    }

    public function setLazyEvalResults(int $lazyEvalResults): ContestProblem
    {
        $this->lazyEvalResults = $lazyEvalResults;
        return $this;
    }

    public function getLazyEvalResults(): int
    {
        return $this->lazyEvalResults;
    }

    public function determineOnDemand(int $config_lazy_eval_results): bool {
        if ($this->lazyEvalResults === DJS::EVAL_DEMAND) {
            return true;
        }
        if ($this->lazyEvalResults === DJS::EVAL_DEFAULT && $config_lazy_eval_results === DJS:: EVAL_DEMAND) {
            return true;
        }
        return false;
    }

    public function setContest(?Contest $contest = null): ContestProblem
    {
        $this->contest = $contest;
        return $this;
    }

    public function getContest(): Contest
    {
        return $this->contest;
    }

    public function setProblem(?Problem $problem = null): ContestProblem
    {
        $this->problem = $problem;
        return $this;
    }

    public function getProblem(): ?Problem
    {
        return $this->problem;
    }

    public function addSubmission(Submission $submission): ContestProblem
    {
        $this->submissions->add($submission);
        return $this;
    }

    /**
     * @return Collection<int, Submission>
     */
    public function getSubmissions(): Collection
    {
        return $this->submissions;
    }

    public function getExternalId(): string
    {
        return $this->getProblem()->getExternalid();
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->getColor() && Utils::convertToHex($this->getColor()) === null) {
            $context
                ->buildViolation('This is not a valid color')
                ->atPath('color')
                ->addViolation();
        }
    }
}
