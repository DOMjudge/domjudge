<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Stores testcases per problem.
 */
#[ORM\Table(
    name: 'testcase',
    options: [
        'collation' => 'utf8mb4_unicode_ci',
        'charset' => 'utf8mb4',
        'comment' => 'Stores testcases per problem',
    ])]
#[ORM\Index(columns: ['probid'], name: 'probid')]
#[ORM\Index(columns: ['sample'], name: 'sample')]
#[ORM\UniqueConstraint(name: 'rankindex', columns: ['probid', 'ranknumber'])]
#[ORM\Entity]
class Testcase
{
    // Mapping from type to extension
    final public const EXTENSION_MAPPING = [
        'input'  => 'in',
        'output' => 'ans',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(
        name: 'testcaseid',
        type: 'integer',
        length: 4,
        nullable: false,
        options: ['comment' => 'Testcase ID', 'unsigned' => true]
    )]
    private int $testcaseid;

    #[ORM\Column(
        name: 'md5sum_input',
        type: 'string',
        length: 32,
        nullable: true,
        options: ['comment' => 'Checksum of input data', 'fixed' => true]
    )]
    private ?string $md5sum_input = null;

    #[ORM\Column(
        name: 'md5sum_output',
        type: 'string',
        length: 32,
        nullable: true,
        options: ['comment' => 'Checksum of output data', 'fixed' => true]
    )]
    private ?string $md5sum_output = null;

    #[ORM\Column(
        name: '`ranknumber`',
        type: 'integer',
        length: 4,
        nullable: false,
        options: ['comment' => 'Determines order of the testcases in judging', 'unsigned' => true]
    )]
    private int $ranknumber;

    /**
     * @var resource|null
     */
    #[ORM\Column(
        name: 'description',
        type: 'blob',
        length: 4294967295,
        nullable: true,
        options: ['comment' => 'Description of this testcase']
    )]
    #[Serializer\Exclude]
    private $description;

    private ?string $description_as_string = null;

    #[ORM\Column(
        name: 'orig_input_filename',
        type: 'string',
        length: 255,
        nullable: true,
        options: ['comment' => 'Original basename of the input file.', 'default' => null]
    )]
    #[Serializer\Exclude]
    private ?string $orig_input_filename = null;

    #[ORM\Column(
        name: 'image_type',
        type: 'string',
        length: 4,
        nullable: true,
        options: ['comment' => 'File type of the image and thumbnail']
    )]
    #[Serializer\Exclude]
    private ?string $image_type = null;

    #[ORM\Column(
        name: 'sample',
        type: 'boolean',
        nullable: false,
        options: ['comment' => 'Sample testcases that can be shared with teams', 'default' => 0]
    )]
    #[Serializer\Exclude]
    private bool $sample = false;

    #[ORM\Column(
        name: 'deleted',
        type: 'boolean',
        nullable: false,
        options: ['comment' => 'Deleted testcases are kept for referential integrity.', 'default' => 0]
    )]
    #[Serializer\Exclude]
    private bool $deleted = false;

    #[ORM\OneToMany(mappedBy: 'testcase', targetEntity: JudgingRun::class)]
    #[Serializer\Exclude]
    private Collection $judging_runs;

    #[ORM\OneToMany(mappedBy: 'testcase', targetEntity: ExternalRun::class)]
    #[Serializer\Exclude]
    private Collection $external_runs;

    /**
     * We use a OneToMany instead of a OneToOne here, because otherwise this
     * relation will always be loaded. See the commit message of commit
     * 9e421f96691ec67ed62767fe465a6d8751edd884 for a more elaborate explanation.
     */
    #[ORM\OneToMany(mappedBy: 'testcase', targetEntity: TestcaseContent::class, cascade: ['persist'], orphanRemoval: true)]
    #[Serializer\Exclude]
    private Collection $content;

    #[ORM\ManyToOne(targetEntity: Problem::class, inversedBy: 'testcases')]
    #[ORM\JoinColumn(name: 'probid', referencedColumnName: 'probid', onDelete: 'CASCADE')]
    #[Serializer\Exclude]
    private ?Problem $problem = null;

    public function __construct()
    {
        $this->judging_runs  = new ArrayCollection();
        $this->external_runs = new ArrayCollection();
        $this->content       = new ArrayCollection();
    }

    public function getTestcaseid(): int
    {
        return $this->testcaseid;
    }

    public function setMd5sumInput(string $md5sumInput): Testcase
    {
        $this->md5sum_input = $md5sumInput;
        return $this;
    }

    public function getMd5sumInput(): string
    {
        return $this->md5sum_input;
    }

    public function setMd5sumOutput(string $md5sumOutput): Testcase
    {
        $this->md5sum_output = $md5sumOutput;
        return $this;
    }

    public function getMd5sumOutput(): string
    {
        return $this->md5sum_output;
    }

    public function setRank(int $rank): Testcase
    {
        $this->ranknumber = $rank;
        return $this;
    }

    public function getRank(): int
    {
        return $this->ranknumber;
    }

    /**
     * @param resource|string $description
     */
    public function setDescription($description): Testcase
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return resource|string|null
     */
    public function getDescription(bool $asString = false)
    {
        if ($asString && $this->description !== null) {
            if ($this->description_as_string === null) {
                $this->description_as_string = stream_get_contents($this->description);
            }
            return $this->description_as_string;
        }
        return $this->description;
    }

    public function setOrigInputFilename(string $origInputFilename): Testcase
    {
        $this->orig_input_filename = $origInputFilename;
        return $this;
    }

    public function getOrigInputFilename(): ?string
    {
        return $this->orig_input_filename;
    }

    public function setImageType(string $imageType): Testcase
    {
        $this->image_type = $imageType;
        return $this;
    }

    public function getImageType(): ?string
    {
        return $this->image_type;
    }

    public function setSample(bool $sample): Testcase
    {
        $this->sample = $sample;
        return $this;
    }

    public function setDeleted(bool $deleted): Testcase
    {
        $this->deleted = $deleted;
        return $this;
    }

    public function getSample(): bool
    {
        return $this->sample;
    }

    public function getDeleted(): bool
    {
        return $this->deleted;
    }

    public function addJudgingRun(JudgingRun $judgingRun): Testcase
    {
        $this->judging_runs[] = $judgingRun;
        return $this;
    }

    public function removeJudgingRun(JudgingRun $judgingRun): Testcase
    {
        $this->judging_runs->removeElement($judgingRun);
        return $this;
    }

    public function getJudgingRuns(): Collection
    {
        return $this->judging_runs;
    }

    /**
     * Gets the first judging run for this testcase.
     *
     * This is useful when this testcase is joined to a single run to get code completion in Twig templates.
     */
    public function getFirstJudgingRun(): ?JudgingRun
    {
        return $this->judging_runs->first() ?: null;
    }

    /**
     * Gets the first external run for this testcase.
     *
     * This is useful when this testcase is joined to a single external run to get code completion in Twig templates.
     */
    public function getFirstExternalRun(): ?ExternalRun
    {
        return $this->external_runs->first() ?: null;
    }

    public function setContent(?TestcaseContent $content): Testcase
    {
        $this->content->clear();
        $this->content->add($content);
        $content->setTestcase($this);

        return $this;
    }

    public function getContent(): ?TestcaseContent
    {
        return $this->content->first() ?: null;
    }

    public function setProblem(?Problem $problem = null): Testcase
    {
        $this->problem = $problem;
        return $this;
    }

    public function getProblem(): ?Problem
    {
        return $this->problem;
    }

    public function addExternalRun(ExternalRun $externalRun): Testcase
    {
        $this->external_runs[] = $externalRun;
        return $this;
    }

    public function removeExternalRun(ExternalRun $externalRun)
    {
        $this->external_runs->removeElement($externalRun);
    }

    public function getExternalRuns(): Collection
    {
        return $this->external_runs;
    }

    public function getDownloadName(): string
    {
        if ($this->getOrigInputFilename()) {
            return $this->getOrigInputFilename();
        }

        return sprintf('p%d.t%d', $this->getProblem()->getProbid(), $this->getRank());
    }
}
