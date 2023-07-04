<?php declare(strict_types=1);

namespace App\Entity;

use App\Controller\API\AbstractRestController as ARC;
use App\Utils\Utils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Stores testcases per problem.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Problems the teams can submit solutions for',
])]
#[ORM\UniqueConstraint(columns: ['externalid'], name: 'externalid', options: ['lengths' => [190]])]
#[ORM\Index(columns: ['special_run'], name: 'special_run')]
#[ORM\Index(columns: ['special_compare'], name: 'special_compare')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: 'externalid')]
class Problem extends BaseApiEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Problem ID', 'unsigned' => true])]
    #[Serializer\Exclude]
    protected ?int $probid = null;

    #[ORM\Column(
        nullable: true,
        options: [
            'comment' => 'Problem ID in an external system, should be unique inside a single contest',
            'collation' => 'utf8mb4_bin',
        ]
    )]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    protected ?string $externalid = null;

    #[ORM\Column(options: ['comment' => 'Descriptive name'])]
    private string $name;

    #[ORM\Column(options: [
        'comment' => 'Maximum run time (in seconds) for this problem',
        'default' => 0,
        'unsigned' => true,
    ])]
    #[Assert\GreaterThan(0)]
    #[Serializer\Exclude]
    private float $timelimit = 0;

    #[ORM\Column(
        nullable: true,
        options: [
            'comment' => 'Maximum memory available (in kB) for this problem',
            'unsigned' => true,
        ]
    )]
    #[Assert\GreaterThan(0)]
    #[Serializer\Exclude]
    private ?int $memlimit = null;

    #[ORM\Column(
        nullable: true,
        options: ['comment' => 'Maximum output size (in kB) for this problem', 'unsigned' => true]
    )]
    #[Assert\GreaterThan(0)]
    #[Serializer\Exclude]
    private ?int $outputlimit = null;

    #[ORM\Column(nullable: true, options: ['comment' => 'Optional arguments to special_compare script'])]
    #[Serializer\Exclude]
    private ?string $special_compare_args = null;

    #[ORM\Column(options: [
        'comment' => 'Use the exit code of the run script to compute the verdict',
        'default' => 0,
    ])]
    #[Serializer\Exclude]
    private bool $combined_run_compare = false;

    /**
     * @var resource|string|null
     */
    #[ORM\Column(
        type: 'blob',
        nullable: true,
        options: ['comment' => 'Problem text in HTML/PDF/ASCII']
    )]
    #[Serializer\Exclude]
    private mixed $problemtext = null;

    #[Assert\File]
    #[Serializer\Exclude]
    private ?UploadedFile $problemtextFile = null;

    #[Serializer\Exclude]
    private bool $clearProblemtext = false;

    #[ORM\Column(
        length: 4,
        nullable: true,
        options: ['comment' => 'File type of problem text']
    )]
    #[Serializer\Exclude]
    private ?string $problemtext_type = null;

    /**
     * @var Collection<int, Submission>
     */
    #[ORM\OneToMany(mappedBy: 'problem', targetEntity: Submission::class)]
    #[Serializer\Exclude]
    private Collection $submissions;

    /**
     * @var Collection<int, Clarification>
     */
    #[ORM\OneToMany(mappedBy: 'problem', targetEntity: Clarification::class)]
    #[Serializer\Exclude]
    private Collection $clarifications;

    /**
     * @var Collection<int, ContestProblem>
     */
    #[ORM\OneToMany(mappedBy: 'problem', targetEntity: ContestProblem::class)]
    #[Serializer\Exclude]
    private Collection $contest_problems;

    #[ORM\ManyToOne(inversedBy: 'problems_compare')]
    #[ORM\JoinColumn(name: 'special_compare', referencedColumnName: 'execid', onDelete: 'SET NULL')]
    #[Serializer\Exclude]
    private ?Executable $compare_executable = null;

    #[ORM\ManyToOne(inversedBy: 'problems_run')]
    #[ORM\JoinColumn(name: 'special_run', referencedColumnName: 'execid', onDelete: 'SET NULL')]
    #[Serializer\Exclude]
    private ?Executable $run_executable = null;

    /**
     * @var Collection<int, Testcase>
     */
    #[ORM\OneToMany(mappedBy: 'problem', targetEntity: Testcase::class)]
    #[ORM\OrderBy(['ranknumber' => 'ASC'])]
    #[Serializer\Exclude]
    private Collection $testcases;

    /**
     * @var Collection<int, ProblemAttachment>
     */
    #[ORM\OneToMany(mappedBy: 'problem', targetEntity: ProblemAttachment::class, orphanRemoval: true)]
    #[ORM\OrderBy(['name' => 'ASC'])]
    #[Serializer\Exclude]
    private Collection $attachments;

    public function setProbid(int $probid): Problem
    {
        $this->probid = $probid;
        return $this;
    }

    public function getProbid(): ?int
    {
        return $this->probid;
    }

    public function setExternalid(?string $externalid): Problem
    {
        $this->externalid = $externalid;
        return $this;
    }

    public function getExternalid(): ?string
    {
        return $this->externalid;
    }

    public function setName(string $name): Problem
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getShortDescription() : ?string
    {
        return $this->getName();
    }

    public function setTimelimit(float $timelimit): Problem
    {
        $this->timelimit = $timelimit;
        return $this;
    }

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('time_limit')]
    #[Serializer\Type('float')]
    public function getTimelimit(): float
    {
        return Utils::roundedFloat($this->timelimit);
    }

    public function setMemlimit(?int $memlimit): Problem
    {
        $this->memlimit = $memlimit;
        return $this;
    }

    public function getMemlimit(): ?int
    {
        return $this->memlimit;
    }

    public function setOutputlimit(?int $outputlimit): Problem
    {
        $this->outputlimit = $outputlimit;
        return $this;
    }

    public function getOutputlimit(): ?int
    {
        return $this->outputlimit;
    }

    public function setSpecialCompareArgs(?string $specialCompareArgs): Problem
    {
        $this->special_compare_args = $specialCompareArgs;
        return $this;
    }

    public function getSpecialCompareArgs(): ?string
    {
        return $this->special_compare_args;
    }

    public function setCombinedRunCompare(bool $combinedRunCompare): Problem
    {
        $this->combined_run_compare = $combinedRunCompare;
        return $this;
    }

    public function getCombinedRunCompare(): bool
    {
        return $this->combined_run_compare;
    }

    /**
     * @param resource|string|null $problemtext
     */
    public function setProblemtext($problemtext): Problem
    {
        $this->problemtext = $problemtext;
        return $this;
    }

    public function setProblemtextFile(?UploadedFile $problemtextFile): Problem
    {
        $this->problemtextFile = $problemtextFile;

        // Clear the problem text to make sure the entity is modified.
        $this->problemtext = '';

        return $this;
    }

    public function setClearProblemtext(bool $clearProblemtext): Problem
    {
        $this->clearProblemtext = $clearProblemtext;
        $this->problemtext = null;

        return $this;
    }

    /**
     * @return resource|string
     */
    public function getProblemtext()
    {
        return $this->problemtext;
    }

    public function getProblemtextFile(): ?UploadedFile
    {
        return $this->problemtextFile;
    }

    public function isClearProblemtext(): bool
    {
        return $this->clearProblemtext;
    }

    public function setProblemtextType(?string $problemtextType): Problem
    {
        $this->problemtext_type = $problemtextType;
        return $this;
    }

    public function getProblemtextType(): ?string
    {
        return $this->problemtext_type;
    }

    public function setCompareExecutable(?Executable $compareExecutable = null): Problem
    {
        $this->compare_executable = $compareExecutable;
        return $this;
    }

    public function getCompareExecutable(): ?Executable
    {
        return $this->compare_executable;
    }

    public function setRunExecutable(?Executable $runExecutable = null): Problem
    {
        $this->run_executable = $runExecutable;
        return $this;
    }

    public function getRunExecutable(): ?Executable
    {
        return $this->run_executable;
    }

    public function __construct()
    {
        $this->testcases        = new ArrayCollection();
        $this->submissions      = new ArrayCollection();
        $this->clarifications   = new ArrayCollection();
        $this->contest_problems = new ArrayCollection();
        $this->attachments      = new ArrayCollection();
    }

    public function addTestcase(Testcase $testcase): Problem
    {
        $this->testcases[] = $testcase;
        return $this;
    }

    /**
     * @return Collection<int, Testcase>
     */
    public function getTestcases(): Collection
    {
        return $this->testcases;
    }

    public function addContestProblem(ContestProblem $contestProblem): Problem
    {
        $this->contest_problems[] = $contestProblem;
        return $this;
    }

    /**
     * @return Collection<int, ContestProblem>
     */
    public function getContestProblems(): Collection
    {
        return $this->contest_problems;
    }

    public function addSubmission(Submission $submission): Problem
    {
        $this->submissions[] = $submission;
        return $this;
    }

    /**
     * @return Collection<int, Submission>
     */
    public function getSubmissions(): Collection
    {
        return $this->submissions;
    }

    public function addClarification(Clarification $clarification): Problem
    {
        $this->clarifications[] = $clarification;
        return $this;
    }

    /**
     * @return Collection<int, Clarification>
     */
    public function getClarifications(): Collection
    {
        return $this->clarifications;
    }

    public function addAttachment(ProblemAttachment $attachment): self
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments[] = $attachment;
            $attachment->setProblem($this);
        }

        return $this;
    }

    public function removeAttachment(ProblemAttachment $attachment): self
    {
        if ($this->attachments->contains($attachment)) {
            $this->attachments->removeElement($attachment);
            // set the owning side to null (unless already changed)
            if ($attachment->getProblem() === $this) {
                $attachment->setProblem(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ProblemAttachment>
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function processProblemText(): void
    {
        if ($this->isClearProblemtext()) {
            $this
                ->setProblemtext(null)
                ->setProblemtextType(null);
        } elseif ($this->getProblemtextFile()) {
            $content         = file_get_contents($this->getProblemtextFile()->getRealPath());
            $clientName      = $this->getProblemtextFile()->getClientOriginalName();
            $problemTextType = null;

            if (strrpos($clientName, '.') !== false) {
                $ext = substr($clientName, strrpos($clientName, '.') + 1);
                if (in_array($ext, ['txt', 'html', 'pdf'])) {
                    $problemTextType = $ext;
                }
            }
            if (!isset($problemTextType)) {
                $finfo = finfo_open(FILEINFO_MIME);

                [$type] = explode('; ', finfo_file($finfo, $this->getProblemtextFile()->getRealPath()));

                finfo_close($finfo);

                switch ($type) {
                    case 'application/pdf':
                        $problemTextType = 'pdf';
                        break;
                    case 'text/html':
                        $problemTextType = 'html';
                        break;
                    case 'text/plain':
                        $problemTextType = 'txt';
                        break;
                }
            }

            if (!isset($problemTextType)) {
                throw new Exception('Problem statement has unknown file type.');
            }

            $this
                ->setProblemtext($content)
                ->setProblemtextType($problemTextType);
        }
    }

    public function getProblemTextStreamedResponse(): StreamedResponse
    {
        $mimetype = match ($this->getProblemtextType()) {
            'pdf' => 'application/pdf',
            'html' => 'text/html',
            'txt' => 'text/plain',
            default => throw new BadRequestHttpException(sprintf('Problem p%d text has unknown type', $this->getProbid())),
        };

        $filename    = sprintf('prob-%s.%s', $this->getName(), $this->getProblemtextType());
        $problemText = stream_get_contents($this->getProblemtext());

        $response = new StreamedResponse();
        $response->setCallback(function () use ($problemText) {
            echo $problemText;
        });
        $response->headers->set('Content-Type', sprintf('%s; name="%s"', $mimetype, $filename));
        $response->headers->set('Content-Disposition', sprintf('inline; filename="%s"', $filename));
        $response->headers->set('Content-Length', (string)strlen($problemText));

        return $response;
    }
}
