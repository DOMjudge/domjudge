<?php declare(strict_types=1);

namespace App\Entity;

use App\Controller\API\AbstractRestController as ARC;
use App\DataTransferObject\FileWithName;
use App\Utils\Utils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * All incoming submissions.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'All incoming submissions',
])]
#[ORM\Index(columns: ['cid', 'teamid'], name: 'teamid')]
#[ORM\Index(columns: ['teamid'], name: 'teamid_2')]
#[ORM\Index(columns: ['userid'], name: 'userid')]
#[ORM\Index(columns: ['probid'], name: 'probid')]
#[ORM\Index(columns: ['langid'], name: 'langid')]
#[ORM\Index(columns: ['origsubmitid'], name: 'origsubmitid')]
#[ORM\Index(columns: ['rejudgingid'], name: 'rejudgingid')]
#[ORM\Index(columns: ['cid', 'probid'], name: 'probid_2')]
#[ORM\UniqueConstraint(
    name: 'externalid',
    columns: ['cid', 'externalid'],
    options: ['lengths' => [null, 190]]
)]
#[UniqueEntity(fields: 'externalid')]
class Submission extends BaseApiEntity implements
    HasExternalIdInterface,
    ExternalIdFromInternalIdInterface,
    PrefixedExternalIdInShadowModeInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Submission ID', 'unsigned' => true])]
    #[Serializer\SerializedName('submitid')]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    protected int $submitid;

    #[ORM\Column(
        nullable: true,
        options: [
            'comment' => 'Specifies ID of submission if imported from external CCS, e.g. Kattis',
            'collation' => 'utf8mb4_bin',
        ]
    )]
    #[OA\Property(nullable: true)]
    #[Serializer\SerializedName('id')]
    protected ?string $externalid = null;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        options: ['comment' => 'Time submitted', 'unsigned' => true]
    )]
    #[Serializer\Exclude]
    private string|float|null $submittime = null;

    #[ORM\Column(options: [
        'comment' => 'If false ignore this submission in all scoreboard calculations',
        'default' => 1,
    ])]
    #[Serializer\Exclude]
    private bool $valid = true;

    /** @var string[]|null $expected_results */
    #[ORM\Column(
        type: 'json',
        length: AbstractMySQLPlatform::LENGTH_LIMIT_TINYTEXT,
        nullable: true,
        options: ['comment' => 'JSON encoded list of expected results - used to validate jury submissions']
    )]
    #[Serializer\Exclude]
    private ?array $expected_results;

    #[ORM\Column(
        nullable: true,
        options: ['comment' => 'Optional entry point. Can be used e.g. for java main class.']
    )]
    #[OA\Property(nullable: true)]
    #[Serializer\Expose(if: "context.getAttribute('domjudge_service').checkrole('jury')")]
    private ?string $entry_point = null;

    #[ORM\Column(
        nullable: true,
        options: ['comment' => 'The error message for submissions which got an error during shadow importing.']
    )]
    #[OA\Property(nullable: true)]
    #[Serializer\Expose(if: "context.getAttribute('domjudge_service').checkrole('jury')")]
    #[Serializer\Groups([ARC::GROUP_NONSTRICT])]
    private ?string $importError = null;

    #[ORM\ManyToOne(inversedBy: 'submissions')]
    #[ORM\JoinColumn(name: 'cid', referencedColumnName: 'cid', onDelete: 'CASCADE')]
    #[Serializer\Exclude]
    private Contest $contest;

    #[ORM\ManyToOne(inversedBy: 'submissions')]
    #[ORM\JoinColumn(name: 'langid', referencedColumnName: 'langid', onDelete: 'CASCADE')]
    #[Serializer\Exclude]
    private Language $language;

    #[ORM\ManyToOne(inversedBy: 'submissions')]
    #[ORM\JoinColumn(name: 'teamid', referencedColumnName: 'teamid', onDelete: 'CASCADE')]
    #[Serializer\Exclude]
    private Team $team;

    #[ORM\ManyToOne(inversedBy: 'submissions')]
    #[ORM\JoinColumn(name: 'userid', referencedColumnName: 'userid', onDelete: 'SET NULL')]
    #[Serializer\Exclude]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'submissions')]
    #[ORM\JoinColumn(name: 'probid', referencedColumnName: 'probid', onDelete: 'CASCADE')]
    #[Serializer\Exclude]
    private Problem $problem;

    #[ORM\ManyToOne(inversedBy: 'submissions')]
    #[ORM\JoinColumn(name: 'cid', referencedColumnName: 'cid', onDelete: 'CASCADE')]
    #[ORM\JoinColumn(name: 'probid', referencedColumnName: 'probid', onDelete: 'CASCADE')]
    #[Serializer\Exclude]
    private ContestProblem $contest_problem;

    /**
     * @var Collection<int, Judging>
     */
    #[ORM\OneToMany(mappedBy: 'submission', targetEntity: Judging::class)]
    #[Serializer\Exclude]
    private Collection $judgings;

    /**
     * @var Collection<int, ExternalJudgement>
     */
    #[ORM\OneToMany(mappedBy: 'submission', targetEntity: ExternalJudgement::class)]
    #[Serializer\Exclude]
    private Collection $external_judgements;

    /**
     * @var Collection<int, SubmissionFile>
     */
    #[ORM\OneToMany(mappedBy: 'submission', targetEntity: SubmissionFile::class)]
    #[Serializer\Exclude]
    private Collection $files;

    /**
     * @var Collection<int, Balloon>
     */
    #[ORM\OneToMany(mappedBy: 'submission', targetEntity: Balloon::class)]
    #[Serializer\Exclude]
    private Collection $balloons;

    /**
     * rejudgings have one parent judging
     */
    #[ORM\ManyToOne(inversedBy: 'submissions')]
    #[ORM\JoinColumn(name: 'rejudgingid', referencedColumnName: 'rejudgingid', onDelete: 'SET NULL')]
    #[Serializer\Exclude]
    private ?Rejudging $rejudging = null;

    #[ORM\ManyToOne(inversedBy: 'resubmissions')]
    #[ORM\JoinColumn(name: 'origsubmitid', referencedColumnName: 'submitid', onDelete: 'SET NULL')]
    #[Serializer\Exclude]
    private ?Submission $originalSubmission = null;

    /**
     * @var Collection<int, Submission>
     */
    #[ORM\OneToMany(mappedBy: 'originalSubmission', targetEntity: Submission::class)]
    #[Serializer\Exclude]
    private Collection $resubmissions;

    /**
     * Holds the old result in the case this submission is displayed in a rejudging table.
     */
    #[Serializer\Exclude]
    private ?string $old_result = null;

    // This field gets filled by the submission visitor with a data transfer
    // object that represents the submission file
    #[Serializer\Exclude]
    private ?FileWithName $fileForApi = null;

    public function getResult(): ?string
    {
        foreach ($this->judgings as $j) {
            if ($j->getValid()) {
                return $j->getResult();
            }
        }
        return null;
    }

    public function getSubmitid(): int
    {
        return $this->submitid;
    }

    public function setExternalid(?string $externalid): Submission
    {
        $this->externalid = $externalid;
        return $this;
    }

    public function getExternalid(): ?string
    {
        return $this->externalid;
    }

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('language_id')]
    public function getLanguageId(): string
    {
        return $this->getLanguage()->getExternalid();
    }

    public function setSubmittime(string|float $submittime): Submission
    {
        $this->submittime = $submittime;
        return $this;
    }

    public function getSubmittime(): string|float
    {
        return $this->submittime;
    }

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('time')]
    #[Serializer\Type('string')]
    public function getAbsoluteSubmitTime(): string
    {
        return Utils::absTime($this->getSubmittime());
    }

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('contest_time')]
    #[Serializer\Type('string')]
    public function getRelativeSubmitTime(): string
    {
        return Utils::relTime($this->getContest()->getContestTime((float)$this->getSubmittime()));
    }

    public function setValid(bool $valid): Submission
    {
        $this->valid = $valid;
        return $this;
    }

    public function getValid(): bool
    {
        return $this->valid;
    }

    /**
     * @param string[] $expectedResults
     */
    public function setExpectedResults(array $expectedResults): Submission
    {
        $this->expected_results = $expectedResults;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getExpectedResults(): ?array
    {
        return $this->expected_results;
    }

    public function setEntryPoint(?string $entryPoint): Submission
    {
        $this->entry_point = $entryPoint;
        return $this;
    }

    public function getEntryPoint(): ?string
    {
        return $this->entry_point;
    }

    public function setImportError(?string $importError): Submission
    {
        $this->importError = $importError;
        return $this;
    }

    public function isImportError(): ?string
    {
        return $this->importError;
    }

    public function setTeam(?Team $team = null): Submission
    {
        $this->team = $team;
        return $this;
    }

    public function getTeam(): Team
    {
        return $this->team;
    }

    public function getTeamId(): int
    {
        return $this->getTeam()->getTeamid();
    }

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('team_id')]
    public function getApiTeamId(): string
    {
        return $this->getTeam()->getExternalid();
    }

    public function setUser(?User $user = null): Submission
    {
        $this->user = $user;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function __construct()
    {
        $this->judgings            = new ArrayCollection();
        $this->files               = new ArrayCollection();
        $this->resubmissions       = new ArrayCollection();
        $this->external_judgements = new ArrayCollection();
        $this->balloons            = new ArrayCollection();
    }

    public function addJudging(Judging $judging): Submission
    {
        $this->judgings[] = $judging;
        return $this;
    }

    /**
     * @return Collection<int, Judging>
     */
    public function getJudgings(): Collection
    {
        return $this->judgings;
    }

    public function setLanguage(?Language $language = null): Submission
    {
        $this->language = $language;
        return $this;
    }

    public function getLanguage(): Language
    {
        return $this->language;
    }

    public function addFile(SubmissionFile $file): Submission
    {
        $this->files->add($file);
        return $this;
    }

    /**
     * @return Collection<int, SubmissionFile>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addBalloon(Balloon $balloon): Submission
    {
        $this->balloons[] = $balloon;
        return $this;
    }

    /**
     * @return Collection<int, Balloon>
     */
    public function getBalloons(): Collection
    {
        return $this->balloons;
    }

    public function setContest(?Contest $contest = null): Submission
    {
        $this->contest = $contest;
        return $this;
    }

    public function getContest(): Contest
    {
        return $this->contest;
    }

    public function setProblem(?Problem $problem = null): Submission
    {
        $this->problem = $problem;
        return $this;
    }

    public function getProblem(): Problem
    {
        return $this->problem;
    }

    public function getProblemId(): int
    {
        return $this->getProblem()->getProbid();
    }

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('problem_id')]
    public function getApiProblemId(): string
    {
        return $this->getProblem()->getExternalid();
    }

    public function setContestProblem(?ContestProblem $contestProblem = null): Submission
    {
        $this->contest_problem = $contestProblem;
        return $this;
    }

    public function getContestProblem(): ContestProblem
    {
        return $this->contest_problem;
    }

    public function setRejudging(?Rejudging $rejudging = null): Submission
    {
        $this->rejudging = $rejudging;
        return $this;
    }

    public function getRejudging(): ?Rejudging
    {
        return $this->rejudging;
    }

    public function isAfterFreeze(): bool
    {
        return $this->getContest()->getFreezetime() !== null && (float)$this->getSubmittime() >= (float)$this->getContest()->getFreezetime();
    }

    public function getOldResult(): ?string
    {
        return $this->old_result;
    }

    public function setOldResult(?string $old_result): Submission
    {
        $this->old_result = $old_result;
        return $this;
    }

    public function getOriginalSubmission(): ?Submission
    {
        return $this->originalSubmission;
    }

    public function setOriginalSubmission(?Submission $originalSubmission): Submission
    {
        $this->originalSubmission = $originalSubmission;
        return $this;
    }

    public function addResubmission(Submission $submission): Submission
    {
        $this->resubmissions->add($submission);
        return $this;
    }

    /**
     * @return Collection<int, Submission>
     */
    public function getResubmissions(): Collection
    {
        return $this->resubmissions;
    }

    public function isAborted(): bool
    {
        // This logic has been copied from putSubmissions().
        /** @var Judging|null $judging */
        $judging = $this->getJudgings()->first();
        if (!$judging) {
            return false;
        }

        return $judging->getEndtime() === null && !$judging->getValid() &&
            (!$judging->getRejudging() || !$judging->getRejudging()->getValid());
    }

    /**
     * Check whether this submission is still busy while the final result is already known,
     * e.g. with non-lazy evaluation.
     */
    public function isStillBusy(): bool
    {
        /** @var Judging|null $judging */
        $judging = $this->getJudgings()->first();
        if (!$judging) {
            return false;
        }

        return !empty($judging->getResult()) && empty($judging->getEndtime()) && !$this->isAborted();
    }

    public function addExternalJudgement(ExternalJudgement $externalJudgement): Submission
    {
        $this->external_judgements[] = $externalJudgement;
        return $this;
    }

    /**
     * @return Collection<int, ExternalJudgement>
     */
    public function getExternalJudgements(): Collection
    {
        return $this->external_judgements;
    }

    public function setFileForApi(?FileWithName $fileForApi = null): Submission
    {
        $this->fileForApi = $fileForApi;
        return $this;
    }

    /**
     * @return FileWithName[]
     */
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('files')]
    #[Serializer\Type('array<App\DataTransferObject\FileWithName>')]
    public function getFileForApi(): array
    {
        return array_filter([$this->fileForApi]);
    }
}
