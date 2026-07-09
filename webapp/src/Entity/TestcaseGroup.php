<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    options: [
        'collation' => 'utf8mb4_unicode_ci',
        'charset' => 'utf8mb4',
        'comment' => 'Testcase group metadata',
    ]
)]
class TestcaseGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Testcase group ID', 'unsigned' => true])]
    private int $testcaseGroupId;

    #[ORM\Column(type: 'string', length: 255, options: ['comment' => 'Name of the testcase group'])]
    private string $name;

    #[ORM\Column(type: 'decimal', precision: 32, scale: 9, nullable: true, options: ['comment' => 'Score if this group is accepted'])]
    private string|float|null $acceptScore = null;

    #[ORM\Column(type: 'decimal', precision: 32, scale: 9, nullable: true, options: ['comment' => 'Lower bound of the score range'])]
    private string|float|null $rangeLowerBound = null;

    #[ORM\Column(type: 'decimal', precision: 32, scale: 9, nullable: true, options: ['comment' => 'Upper bound of the score range'])]
    private string|float|null $rangeUpperBound = null;

    #[ORM\Column(type: 'string', enumType: TestcaseAggregationType::class, options: ['default' => 'sum', 'comment' => 'How to aggregate scores for this group'])]
    private TestcaseAggregationType $aggregationType = TestcaseAggregationType::SUM;

    #[ORM\Column(type: 'boolean', options: ['default' => false, 'comment' => 'Ignore the sample testcases when aggregating scores'])]
    private bool $ignoreSample = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true, options: ['comment' => 'Flags for output validation'])]
    private string $outputValidatorFlags = '';

    #[ORM\Column(type: 'boolean', options: ['default' => false, 'comment' => 'Continue on reject'])]
    private bool $onRejectContinue = false;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'testcase_group_id', nullable: true, onDelete: 'SET NULL')]
    private ?self $parent = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $children;

    /**
     * @var Collection<int, Testcase>
     */
    #[ORM\OneToMany(targetEntity: Testcase::class, mappedBy: 'testcaseGroup')]
    private Collection $testcases;

    /**
     * @var Collection<int, Problem>
     */
    #[ORM\OneToMany(targetEntity: Problem::class, mappedBy: 'parentTestcaseGroup')]
    private Collection $problems;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->testcases = new ArrayCollection();
        $this->problems = new ArrayCollection();
    }

    public function getTestcaseGroupId(): int
    {
        return $this->testcaseGroupId;
    }

    public function setTestcaseGroupId(int $testcaseGroupId): self
    {
        $this->testcaseGroupId = $testcaseGroupId;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getAcceptScore(): ?string
    {
        return $this->acceptScore !== null ? (string)$this->acceptScore : null;
    }

    public function setAcceptScore(string|float|null $acceptScore): self
    {
        $this->acceptScore = $acceptScore;
        return $this;
    }

    public function getRangeLowerBound(): ?string
    {
        return $this->rangeLowerBound !== null ? (string)$this->rangeLowerBound : null;
    }

    public function setRangeLowerBound(string|float|null $rangeLowerBound): self
    {
        $this->rangeLowerBound = $rangeLowerBound;
        return $this;
    }

    public function getRangeUpperBound(): ?string
    {
        return $this->rangeUpperBound !== null ? (string)$this->rangeUpperBound : null;
    }

    public function setRangeUpperBound(string|float|null $rangeUpperBound): self
    {
        $this->rangeUpperBound = $rangeUpperBound;
        return $this;
    }

    public function getAggregationType(): TestcaseAggregationType
    {
        return $this->aggregationType;
    }

    public function setAggregationType(TestcaseAggregationType $aggregationType): self
    {
        $this->aggregationType = $aggregationType;
        return $this;
    }

    public function isIgnoreSample(): bool
    {
        return $this->ignoreSample;
    }

    public function setIgnoreSample(bool $ignoreSample): self
    {
        $this->ignoreSample = $ignoreSample;
        return $this;
    }

    public function getParent(): ?TestcaseGroup
    {
        return $this->parent;
    }

    public function setParent(?TestcaseGroup $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * @return Collection<int, Testcase>
     */
    public function getTestcases(): Collection
    {
        return $this->testcases;
    }

    /**
     * @return Collection<int, Problem>
     */
    public function getProblems(): Collection
    {
        return $this->problems;
    }

    public function getOutputValidatorFlags(): string
    {
        return $this->outputValidatorFlags;
    }

    public function setOutputValidatorFlags(string $outputValidatorFlags): self
    {
        $this->outputValidatorFlags = $outputValidatorFlags;
        return $this;
    }

    public function isOnRejectContinue(): bool
    {
        return $this->onRejectContinue;
    }

    public function setOnRejectContinue(bool $onRejectContinue): self
    {
        $this->onRejectContinue = $onRejectContinue;
        return $this;
    }

    /**
     * @return TestcaseGroup[]
     */
    public function getLineage(): array
    {
        $lineage = [$this];
        $parent  = $this->getParent();
        while ($parent !== null) {
            array_unshift($lineage, $parent);
            $parent = $parent->getParent();
        }
        return $lineage;
    }
}
