<?php declare(strict_types=1);
namespace App\Entity;

use App\Controller\API\AbstractRestController as ARC;
use App\Utils\Utils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Uuid;

/**
 * Result of judging a submission.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Result of judging a submission',
])]
#[ORM\Index(columns: ['submitid'], name: 'submitid')]
#[ORM\Index(columns: ['cid'], name: 'cid')]
#[ORM\Index(columns: ['rejudgingid'], name: 'rejudgingid')]
#[ORM\Index(columns: ['prevjudgingid'], name: 'prevjudgingid')]
class Judging extends AbstractJudgement
{
    final public const RESULT_CORRECT = 'correct';
    final public const RESULT_COMPILER_ERROR = 'compiler-error';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Judging ID', 'unsigned' => true])]
    #[Serializer\SerializedName('id')]
    #[Serializer\Type('string')]
    protected int $judgingid;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: true,
        options: ['comment' => 'Time judging started', 'unsigned' => true]
    )]
    #[Serializer\Exclude]
    private string|float|null $starttime = null;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: true,
        options: ['comment' => 'Time judging ended, null = still busy', 'unsigned' => true]
    )]
    #[OA\Property(nullable: true)]
    #[Serializer\Exclude]
    private string|float|null $endtime = null;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: true,
        options: ['comment' => 'The maximum runtime for all runs that resulted in the verdict', 'unsigned' => true]
    )]
    #[Serializer\Exclude]
    private string|float|null $maxRuntimeForVerdict = null;

    #[ORM\Column(
        length: 32,
        nullable: true,
        options: ['comment' => 'Result string as defined in config.php']
    )]
    #[Serializer\Exclude]
    private ?string $result = null;

    #[ORM\Column(options: ['comment' => 'Result verified by jury member?', 'default' => 0])]
    #[Serializer\Exclude]
    private bool $verified = false;

    #[ORM\Column(nullable: true, options: ['comment' => 'Name of jury member who verified this'])]
    #[Serializer\Exclude]
    private ?string $jury_member = null;

    #[ORM\Column(nullable: true, options: ['comment' => 'Optional additional information provided by the verifier'])]
    #[Serializer\Exclude]
    private ?string $verify_comment = null;

    #[ORM\Column(
        options: ['comment' => 'Old judging is marked as invalid when rejudging', 'default' => 1]
    )]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    private bool $valid = true;

    /**
     * @var resource|null
     */
    #[ORM\Column(
        type: 'blob',
        nullable: true,
        options: ['comment' => 'Output of the compiling the program']
    )]
    #[Serializer\Exclude]
    private $output_compile;

    #[ORM\Column(
        name: 'metadata',
        type: 'blobtext',
        nullable: true,
        options: ['comment' => 'Compilation metadata']
    )]
    #[Serializer\Exclude]
    private ?string $compile_metadata = null;

    #[Serializer\Exclude]
    private ?string $output_compile_as_string = null;

    #[ORM\Column(options: ['comment' => 'Whether the team has seen this judging', 'default' => 0])]
    #[Serializer\Exclude]
    private bool $seen = false;

    #[ORM\Column(options: ['comment' => 'Explicitly requested to be judged completely.', 'default' => 0])]
    #[Serializer\Exclude]
    private bool $judgeCompletely = false;

    #[ORM\Column(options: ['comment' => 'UUID, to make caching of compilation results safe.'])]
    #[Serializer\Exclude]
    private string $uuid;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        options: [
            'comment' => 'Optional score for this run, e.g. for partial scoring',
            'default' => '0.000000000',
        ]
    )]
    private string|float $score = 0;


    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'cid', referencedColumnName: 'cid', onDelete: 'CASCADE')]
    #[Serializer\Exclude]
    private ?Contest $contest = null;

    #[ORM\ManyToOne(inversedBy: 'judgings')]
    #[ORM\JoinColumn(name: 'submitid', referencedColumnName: 'submitid', onDelete: 'CASCADE')]
    #[Serializer\Exclude]
    private Submission $submission;

    /**
     * rejudgings have one parent judging
     */
    #[ORM\ManyToOne(inversedBy: 'judgings')]
    #[ORM\JoinColumn(name: 'rejudgingid', referencedColumnName: 'rejudgingid', onDelete: 'SET NULL')]
    #[Serializer\Exclude]
    private ?Rejudging $rejudging = null;

    /**
     * Rejudgings have one parent judging.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'prevjudgingid', referencedColumnName: 'judgingid', onDelete: 'SET NULL')]
    #[Serializer\Exclude]
    private ?Judging $original_judging = null;

    /**
     * @var Collection<int, JudgingRun>
     */
    #[ORM\OneToMany(mappedBy: 'judging', targetEntity: JudgingRun::class)]
    #[Serializer\Exclude]
    private Collection $runs;

    /**
     * @var Collection<int, DebugPackage>
     */
    #[ORM\OneToMany(mappedBy: 'judging', targetEntity: DebugPackage::class)]
    #[Serializer\Exclude]
    private Collection $debug_packages;

    /**
     * Rejudgings have one parent judging.
     */
    #[ORM\ManyToOne(inversedBy: 'affectedJudgings')]
    #[ORM\JoinColumn(name: 'errorid', referencedColumnName: 'errorid', onDelete: 'SET NULL')]
    #[Serializer\Exclude]
    private ?InternalError $internalError = null;

    public function getJudgingid(): int
    {
        return $this->judgingid;
    }

    public function setStarttime(string|float $starttime): Judging
    {
        $this->starttime = $starttime;
        return $this;
    }

    public function getStarttime(): string|float|null
    {
        return $this->starttime;
    }

    public function setEndtime(string|float $endtime): Judging
    {
        $this->endtime = $endtime;
        return $this;
    }

    public function getEndtime(): string|float|null
    {
        return $this->endtime;
    }

    public function setMaxRuntimeForVerdict(string|float $maxRuntimeForVerdict): Judging
    {
        $this->maxRuntimeForVerdict = $maxRuntimeForVerdict;
        return $this;
    }

    public function getMaxRuntimeForVerdict(): string|float|null
    {
        return $this->maxRuntimeForVerdict;
    }

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('max_run_time')]
    #[Serializer\Type('float')]
    #[OA\Property(nullable: true)]
    public function getRoundedMaxRuntimeForVerdict(): ?float
    {
        return $this->maxRuntimeForVerdict ? Utils::roundedFloat((float)$this->maxRuntimeForVerdict) : null;
    }

    public function setResult(?string $result): Judging
    {
        $this->result = $result;
        return $this;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function setVerified(bool $verified): Judging
    {
        $this->verified = $verified;
        return $this;
    }

    public function getVerified(): bool
    {
        return $this->verified;
    }

    public function setJuryMember(?string $juryMember): Judging
    {
        $this->jury_member = $juryMember;
        return $this;
    }

    public function getJuryMember(): ?string
    {
        return $this->jury_member;
    }

    public function setVerifyComment(?string $verifyComment): Judging
    {
        $this->verify_comment = $verifyComment;
        return $this;
    }

    public function getVerifyComment(): ?string
    {
        return $this->verify_comment;
    }

    public function setValid(bool $valid): Judging
    {
        $this->valid = $valid;
        return $this;
    }

    public function getValid(): bool
    {
        return $this->valid;
    }

    /**
     * @param resource|string $outputCompile
     */
    public function setOutputCompile($outputCompile): Judging
    {
        $this->output_compile = $outputCompile;
        return $this;
    }

    /**
     * @return resource|string|null
     */
    public function getOutputCompile(bool $asString = false)
    {
        if ($asString && $this->output_compile !== null) {
            if ($this->output_compile_as_string === null) {
                $this->output_compile_as_string = stream_get_contents($this->output_compile);
            }
            return $this->output_compile_as_string;
        }
        return $this->output_compile;
    }

    public function setSeen(bool $seen): Judging
    {
        $this->seen = $seen;
        return $this;
    }

    public function getSeen(): bool
    {
        return $this->seen;
    }

    public function setJudgeCompletely(bool $judgeCompletely): Judging
    {
        $this->judgeCompletely = $judgeCompletely;
        return $this;
    }

    public function getJudgeCompletely(): bool
    {
        return $this->judgeCompletely;
    }

    public function setSubmission(?Submission $submission = null): Judging
    {
        $this->submission = $submission;
        return $this;
    }

    public function getSubmission(): Submission
    {
        return $this->submission;
    }

    public function getSubmissionId(): int
    {
        return $this->getSubmission()->getSubmitid();
    }

    public function setContest(?Contest $contest = null): Judging
    {
        $this->contest = $contest;
        return $this;
    }

    public function getContest(): ?Contest
    {
        return $this->contest;
    }

    public function setRejudging(?Rejudging $rejudging = null): Judging
    {
        $this->rejudging = $rejudging;
        return $this;
    }

    public function getRejudging(): ?Rejudging
    {
        return $this->rejudging;
    }

    public function setOriginalJudging(?Judging $originalJudging = null): Judging
    {
        $this->original_judging = $originalJudging;
        return $this;
    }

    public function getOriginalJudging(): ?Judging
    {
        return $this->original_judging;
    }

    public function __construct()
    {
        $this->runs = new ArrayCollection();
        $this->debug_packages = new ArrayCollection();
        $this->uuid = Uuid::uuid4()->toString();
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function addRun(JudgingRun $run): Judging
    {
        $this->runs[] = $run;
        return $this;
    }

    /**
     * @return Collection<int, JudgingRun>
     * @phpstan-ignore method.childReturnType (JudgingRun is a subtype of AbstractRun)
     */
    public function getRuns(): Collection
    {
        return $this->runs;
    }

    public function setInternalError(?InternalError $internalError = null): Judging
    {
        $this->internalError = $internalError;
        return $this;
    }

    public function getInternalError(): ?InternalError
    {
        return $this->internalError;
    }

    /**
     * Check whether this judging has started judging
     */
    public function isStarted(): bool
    {
        return $this->getStarttime() !== null;
    }

    /**
     * Check whether this judging is for an aborted judging.
     */
    public function isAborted(): bool
    {
        // This logic has been copied from putSubmissions().
        return $this->getEndtime() === null && !$this->getValid() &&
            (!$this->getRejudging() || !$this->getRejudging()->getValid());
    }

    /**
     * Check whether this judging is still busy while the final result is already known,
     * e.g. with non-lazy evaluation.
     */
    public function isStillBusy(): bool
    {
        return !empty($this->getResult()) && empty($this->getEndtime()) && !$this->isAborted();
    }

    /**
     * @return string[]
     */
    public function getJudgehosts(): array
    {
        $hostnames = [];
        /** @var JudgingRun $run */
        foreach ($this->getRuns() as $run) {
            if ($run->getJudgeTask() === null || $run->getJudgeTask()->getJudgehost() === null) {
                continue;
            }
            $hostnames[] = $run->getJudgeTask()->getJudgehost()->getHostname();
        }
        $hostnames = array_unique($hostnames);
        sort($hostnames);
        return $hostnames;
    }

    /**
     * @return Collection<int, DebugPackage>
     */
    public function getDebugPackages(): Collection
    {
        return $this->debug_packages;
    }

    public function getCompileMetadata(): ?string
    {
        return $this->compile_metadata;
    }

    public function setCompileMetadata(?string $compile_metadata): self
    {
        $this->compile_metadata = $compile_metadata;
        return $this;
    }

    public function getScore(): string
    {
        return (string)$this->score;
    }

    public function setScore(string|float $score): Judging
    {
        $this->score = $score;
        return $this;
    }
}
