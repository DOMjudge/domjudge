<?php declare(strict_types=1);

namespace App\Entity;

use App\Utils\Utils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use OpenApi\Annotations as OA;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * All incoming submissions.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="submission",
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4","comment"="All incoming submissions"},
 *     indexes={
 *         @ORM\Index(name="teamid", columns={"cid","teamid"}),
 *         @ORM\Index(name="teamid_2", columns={"teamid"}),
 *         @ORM\Index(name="userid", columns={"userid"}),
 *         @ORM\Index(name="probid", columns={"probid"}),
 *         @ORM\Index(name="langid", columns={"langid"}),
 *         @ORM\Index(name="origsubmitid", columns={"origsubmitid"}),
 *         @ORM\Index(name="rejudgingid", columns={"rejudgingid"}),
 *         @ORM\Index(name="probid_2", columns={"cid","probid"})
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="externalid", columns={"cid", "externalid"}, options={"lengths": {null, 190}}),
 *     })
 * @UniqueEntity("externalid")
 */
class Submission extends BaseApiEntity implements ExternalRelationshipEntityInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", length=4, name="submitid",
     *     options={"comment"="Submission ID","unsigned"=true},
     *     nullable=false)
     * @Serializer\SerializedName("id")
     * @Serializer\Type("string")
     */
    protected int $submitid;

    /**
     * @ORM\Column(type="string", name="externalid", length=255,
     *     options={"comment"="Specifies ID of submission if imported from external CCS, e.g. Kattis",
     *              "collation"="utf8mb4_bin"},
     *     nullable=true)
     * @Serializer\Groups({"Nonstrict"})
     * @Serializer\SerializedName("external_id")
     * @OA\Property(nullable=true)
     */
    protected ?string $externalid = null;

    /**
     * @var double|string
     * @ORM\Column(type="decimal", precision=32, scale=9, name="submittime", options={"comment"="Time submitted",
     *                             "unsigned"=true}, nullable=false)
     * @Serializer\Exclude()
     */
    private $submittime;

    /**
     * @ORM\Column(type="boolean", name="valid",
     *     options={"comment"="If false ignore this submission in all scoreboard calculations",
     *              "default"="1"},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private bool $valid = true;

    /**
     * @ORM\Column(type="json", name="expected_results", length=255,
     *     options={"comment"="JSON encoded list of expected results - used to validate jury submissions"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private ?array $expected_results;

    /**
     * @ORM\Column(type="string", name="entry_point", length=255,
     *     options={"comment"="Optional entry point. Can be used e.g. for java main class."},
     *     nullable=true)
     * @Serializer\Expose(if="context.getAttribute('domjudge_service').checkrole('jury')")
     * @OA\Property(nullable=true)
     */
    private ?string $entry_point;

    /**
     * @ORM\ManyToOne(targetEntity="Contest", inversedBy="submissions")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private Contest $contest;

    /**
     * @ORM\ManyToOne(targetEntity="Language", inversedBy="submissions")
     * @ORM\JoinColumn(name="langid", referencedColumnName="langid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private Language $language;

    /**
     * @ORM\ManyToOne(targetEntity="Team", inversedBy="submissions")
     * @ORM\JoinColumn(name="teamid", referencedColumnName="teamid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private Team $team;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="submissions")
     * @ORM\JoinColumn(name="userid", referencedColumnName="userid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private ?User $user;

    /**
     * @ORM\ManyToOne(targetEntity="Problem", inversedBy="submissions")
     * @ORM\JoinColumn(name="probid", referencedColumnName="probid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private Problem $problem;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ContestProblem", inversedBy="submissions")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="CASCADE"),
     *   @ORM\JoinColumn(name="probid", referencedColumnName="probid", onDelete="CASCADE")
     * })
     * @Serializer\Exclude()
     */
    private ContestProblem $contest_problem;

    /**
     * @ORM\OneToMany(targetEntity="Judging", mappedBy="submission")
     * @Serializer\Exclude()
     */
    private Collection $judgings;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ExternalJudgement", mappedBy="submission")
     * @Serializer\Exclude()
     */
    private Collection $external_judgements;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="SubmissionFile", mappedBy="submission")
     * @Serializer\Exclude()
     */
    private Collection $files;

    /**
     * @ORM\OneToMany(targetEntity="Balloon", mappedBy="submission")
     * @Serializer\Exclude()
     */
    private Collection $balloons;

    /**
     * rejudgings have one parent judging
     * @ORM\ManyToOne(targetEntity="Rejudging", inversedBy="submissions")
     * @ORM\JoinColumn(name="rejudgingid", referencedColumnName="rejudgingid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private ?Rejudging $rejudging;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Submission", inversedBy="resubmissions")
     * @ORM\JoinColumn(name="origsubmitid", referencedColumnName="submitid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private ?Submission $originalSubmission;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="App\Entity\Submission", mappedBy="originalSubmission")
     * @Serializer\Exclude()
     */
    private Collection $resubmissions;

    /**
     * Holds the old result in the case this submission is displayed in a rejudging table.
     * @Serializer\Exclude()
     */
    private ?string $old_result;

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

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("language_id")
     * @Serializer\Type("string")
     */
    public function getLanguageId(): string
    {
        return $this->getLanguage()->getExternalid();
    }

    /** @param string|float $submittime */
    public function setSubmittime($submittime): Submission
    {
        $this->submittime = $submittime;
        return $this;
    }

    /** @return string|float */
    public function getSubmittime()
    {
        return $this->submittime;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("time")
     * @Serializer\Type("string")
     */
    public function getAbsoluteSubmitTime(): string
    {
        return Utils::absTime($this->getSubmittime());
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("contest_time")
     * @Serializer\Type("string")
     */
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

    public function setExpectedResults(array $expectedResults): Submission
    {
        $this->expected_results = $expectedResults;
        return $this;
    }

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

    public function setTeam(?Team $team = null): Submission
    {
        $this->team = $team;
        return $this;
    }

    public function getTeam(): Team
    {
        return $this->team;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("team_id")
     * @Serializer\Type("string")
     */
    public function getTeamId(): int
    {
        return $this->getTeam()->getTeamid();
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

    public function removeJudging(Judging $judging): void
    {
        $this->judgings->removeElement($judging);
    }

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

    public function removeFile(SubmissionFile $file): void
    {
        $this->files->removeElement($file);
    }

    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addBalloon(Balloon $balloon): Submission
    {
        $this->balloons[] = $balloon;
        return $this;
    }

    public function removeBalloon(Balloon $balloon): void
    {
        $this->balloons->removeElement($balloon);
    }

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

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("problem_id")
     * @Serializer\Type("string")
     */
    public function getProblemId(): int
    {
        return $this->getProblem()->getProbid();
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

    /**
     * Get the entities to check for external ID's while serializing.
     *
     * This method should return an array with as keys the JSON field names and as values the actual entity
     * objects that the SetExternalIdVisitor should check for applicable external ID's.
     */
    public function getExternalRelationships(): array
    {
        return [
            'language_id' => $this->getLanguage(),
            'problem_id'  => $this->getProblem(),
            'team_id'     => $this->getTeam(),
        ];
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

    public function removeResubmission(Submission $submission): Submission
    {
        $this->resubmissions->removeElement($submission);
        return $this;
    }

    /**
     * @return Collection|Submission[]
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

    public function removeExternalJudgement(ExternalJudgement $externalJudgement): void
    {
        $this->external_judgements->removeElement($externalJudgement);
    }

    public function getExternalJudgements(): Collection
    {
        return $this->external_judgements;
    }
}
