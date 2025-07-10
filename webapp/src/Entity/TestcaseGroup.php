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

    #[ORM\Column(type: 'string', length: 255, nullable: true, options: ['comment' => 'Score if this group is accepted'])]
    private ?string $acceptScore = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, options: ['comment' => 'Lower bound of the score range'])]
    private ?string $rangeLowerBound = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, options: ['comment' => 'Upper bound of the score range'])]
    private ?string $rangeUpperBound = null;

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
    private Collection $children;

    /**
     * @var Collection<int, Testcase>
     */
    #[ORM\OneToMany(targetEntity: Testcase::class, mappedBy: 'testcaseGroup')]
    private Collection $testcases;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->testcases = new ArrayCollection();
    }

    public function getTestcaseGroupId(): int
    {
        return $this->testcaseGroupId;
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
        return $this->acceptScore;
    }

    public function setAcceptScore(?string $acceptScore): self
    {
        $this->acceptScore = $acceptScore;
        return $this;
    }

    public function getRangeLowerBound(): ?string
    {
        return $this->rangeLowerBound;
    }

    public function setRangeLowerBound(?string $rangeLowerBound): self
    {
        $this->rangeLowerBound = $rangeLowerBound;
        return $this;
    }

    public function getRangeUpperBound(): ?string
    {
        return $this->rangeUpperBound;
    }

    public function setRangeUpperBound(?string $rangeUpperBound): self
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
}
