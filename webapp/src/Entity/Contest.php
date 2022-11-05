<?php declare(strict_types=1);

namespace App\Entity;

use App\Utils\FreezeData;
use App\Utils\Utils;
use App\Validator\Constraints\Identifier;
use App\Validator\Constraints\TimeString;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use JMS\Serializer\Annotation as Serializer;
use OpenApi\Annotations as OA;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Contests that will be run with this install.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="contest",
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Contests that will be run with this install"},
 *     indexes={@ORM\Index(name="cid", columns={"cid", "enabled"})},
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="externalid", columns={"externalid"}, options={"lengths": {190}}),
 *         @ORM\UniqueConstraint(name="shortname", columns={"shortname"}, options={"lengths": {190}})
 *     }
 * )
 * @Serializer\VirtualProperty(
 *     "formalName",
 *     exp="object.getName()",
 *     options={@Serializer\Type("string")}
 * )
 * @Serializer\VirtualProperty(
 *     "penaltyTime",
 *     exp="0",
 *     options={@Serializer\Type("int")}
 * )
 * @ORM\HasLifecycleCallbacks()
 * @UniqueEntity("shortname")
 * @UniqueEntity("externalid")
 */
class Contest extends BaseApiEntity implements AssetEntityInterface
{
    const STARTTIME_UPDATE_MIN_SECONDS_BEFORE = 30;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="cid", options={"comment"="Contest ID", "unsigned"=true}, nullable=false, length=4)
     * @Serializer\SerializedName("id")
     * @Serializer\Type("string")
     */
    protected ?int $cid = null;

    /**
     * @ORM\Column(type="string", name="externalid", length=255, options={"comment"="Contest ID in an external system",
     *                            "collation"="utf8mb4_bin"}, nullable=true)
     * @Serializer\Groups({"Nonstrict"})
     * @Serializer\SerializedName("external_id")
     */
    protected ?string $externalid = null;

    /**
     * @ORM\Column(type="string", name="name", length=255, options={"comment"="Descriptive name"}, nullable=false)
     * @Assert\NotBlank()
     */
    private string $name = '';

    /**
     * @ORM\Column(type="string", name="shortname", length=255, options={"comment"="Short name for this contest"},
     *                            nullable=false)
     * @Serializer\Groups({"Nonstrict"})
     * @Identifier()
     * @Assert\NotBlank()
     */
    private string $shortname = '';

    /**
     * @var double|string
     * @ORM\Column(type="decimal", precision=32, scale=9, name="activatetime",
     *     options={"comment"="Time contest becomes visible in team/public views",
     *              "unsigned"=true},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $activatetime;

    /**
     * @var double|string
     * @ORM\Column(type="decimal", precision=32, scale=9, name="starttime",
     *     options={"comment"="Time contest starts, submissions accepted",
     *              "unsigned"=true},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $starttime;

    /**
     * @ORM\Column(type="boolean", name="starttime_enabled",
     *     options={"comment"="If disabled, starttime is not used, e.g. to delay contest start","default"=1},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private bool $starttimeEnabled = true;

    /**
     * @var double|string
     * @ORM\Column(type="decimal", precision=32, scale=9, name="freezetime",
     *     options={"comment"="Time scoreboard is frozen","unsigned"=true},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $freezetime;

    /**
     * @var double|string
     * @ORM\Column(type="decimal", precision=32, scale=9, name="endtime",
     *     options={"comment"="Time after which no more submissions are accepted",
     *              "unsigned"=true},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $endtime;

    /**
     * @var double|string|null
     * @ORM\Column(type="decimal", precision=32, scale=9, name="unfreezetime",
     *     options={"comment"="Unfreeze a frozen scoreboard at this time",
     *              "unsigned"=true},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $unfreezetime = null;

    /**
     * @var double|string|null
     * @ORM\Column(type="decimal", precision=32, scale=9, name="finalizetime",
     *     options={"comment"="Time when contest was finalized, null if not yet",
     *              "unsigned"=true},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $finalizetime = null;

    /**
     * @ORM\Column(type="text", name="finalizecomment", length=65535,
     *     options={"comment"="Comments by the finalizer"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private ?string $finalizecomment = null;

    /**
     * @ORM\Column(type="smallint", length=3, name="b",
     *     options={"comment"="Number of extra bronze medals","unsigned"="true","default"=0},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private ?int $b = 0;

    /**
     * @ORM\Column(type="boolean", name="medals_enabled",
     *     options={"default"=0},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private ?bool $medalsEnabled = false;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\TeamCategory", inversedBy="contests_for_medals")
     * @ORM\JoinTable(name="contestteamcategoryformedals",
     *                joinColumns={@ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="CASCADE")},
     *                inverseJoinColumns={@ORM\JoinColumn(name="categoryid", referencedColumnName="categoryid", onDelete="CASCADE")}
     *               )
     * @Serializer\Exclude()
     */
    private Collection $medal_categories;

    /**
     * @ORM\Column(type="smallint", length=3, name="gold_medals",
     *     options={"comment"="Number of gold medals","unsigned"="true","default"=4},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private int $goldMedals = 4;

    /**
     * @ORM\Column(type="smallint", length=3, name="silver_medals",
     *     options={"comment"="Number of silver medals","unsigned"="true","default"=4},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private int $silverMedals = 4;

    /**
     * @ORM\Column(type="smallint", length=3, name="bronze_medals",
     *     options={"comment"="Number of bronze medals","unsigned"="true","default"=4},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private int $bronzeMedals = 4;

    /**
     * @var double|string
     * @ORM\Column(type="decimal", precision=32, scale=9, name="deactivatetime",
     *     options={"comment"="Time contest becomes invisible in team/public views",
     *              "unsigned"=true},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $deactivatetime = null;

    /**
     * @ORM\Column(type="string", length=64, name="activatetime_string",
     *     options={"comment"="Authoritative absolute or relative string representation of activatetime"},
     *     nullable=false)
     * @Serializer\Exclude()
     * @TimeString(relativeIsPositive=false)
     */
    private string $activatetimeString = '';

    /**
     * @ORM\Column(type="string", length=64, name="starttime_string",
     *     options={"comment"="Authoritative absolute (only!) string representation of starttime"},
     *     nullable=false)
     * @Serializer\Exclude()
     * @TimeString(allowRelative=false)
     */
    private string $starttimeString = '';

    /**
     * @ORM\Column(type="string", length=64, name="freezetime_string",
     *     options={"comment"="Authoritative absolute or relative string representation of freezetime"},
     *     nullable=true)
     * @Serializer\Exclude()
     * @TimeString()
     */
    private ?string $freezetimeString = null;

    /**
     * @ORM\Column(type="string", length=64, name="endtime_string",
     *     options={"comment"="Authoritative absolute or relative string representation of endtime"},
     *     nullable=false)
     * @Serializer\Exclude()
     * @TimeString()
     */
    private string $endtimeString = '';

    /**
     * @ORM\Column(type="string", length=64, name="unfreezetime_string",
     *     options={"comment"="Authoritative absolute or relative string representation of unfreezetime"},
     *     nullable=true)
     * @Serializer\Exclude()
     * @TimeString()
     */
    private ?string $unfreezetimeString = null;

    /**
     * @ORM\Column(type="string", length=64, name="deactivatetime_string",
     *     options={"comment"="Authoritative absolute or relative string representation of deactivatetime"},
     *     nullable=true)
     * @Serializer\Exclude()
     * @TimeString()
     */
    private ?string $deactivatetimeString = null;

    /**
     * @ORM\Column(type="boolean", name="enabled",
     *     options={"comment"="Whether this contest can be active","default"=1},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private bool $enabled = true;

    /**
     * @ORM\Column(type="boolean", name="allow_submit",
     *     options={"comment"="Are submissions accepted in this contest?","default"="1"},
     *     nullable=false)
     * @Serializer\Groups({"Nonstrict"})
     */
    private bool $allowSubmit = true;

    /**
     * @ORM\Column(type="boolean", name="process_balloons",
     *     options={"comment"="Will balloons be processed for this contest?","default"=1},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private bool $processBalloons = true;

    /**
     * @ORM\Column(type="boolean", name="public",
     *     options={"comment"="Is this contest visible for the public?",
     *              "default"=1},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private bool $public = true;

    /**
     * @Assert\File(mimeTypes={"image/png","image/jpeg","image/svg+xml"}, mimeTypesMessage="Only PNG's, JPG's and SVG's are allowed")
     * @Serializer\Exclude()
     */
    private ?UploadedFile $bannerFile = null;

    /**
     * @Serializer\Exclude()
     */
    private bool $clearBanner = false;

    /**
     * @ORM\Column(type="boolean", name="open_to_all_teams",
     *     options={"comment"="Is this contest open to all teams?",
     *              "default"=1},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private bool $openToAllTeams = true;

    /**
     * @ORM\Column(type="text", length=65535, name="warning_message",
     *     options={"comment"="Warning message for this contest shown on the scoreboards"},
     *                          nullable=true)
     * @Serializer\Groups({"Nonstrict"})
     * @OA\Property(nullable=true)
     */
    private ?string $warningMessage = null;

    /**
     * @ORM\Column(type="boolean", name="is_locked",
     *     options={"comment"="Is this contest locked for modifications?",
     *              "default"=0},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private bool $isLocked = false;

    /**
     * @ORM\ManyToMany(targetEntity="Team", inversedBy="contests")
     * @ORM\JoinTable(name="contestteam",
     *                joinColumns={@ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="CASCADE")},
     *                inverseJoinColumns={@ORM\JoinColumn(name="teamid", referencedColumnName="teamid", onDelete="CASCADE")}
     *               )
     * @Serializer\Exclude()
     */
    private Collection $teams;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\TeamCategory", inversedBy="contests")
     * @ORM\JoinTable(name="contestteamcategory",
     *                joinColumns={@ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="CASCADE")},
     *                inverseJoinColumns={@ORM\JoinColumn(name="categoryid", referencedColumnName="categoryid", onDelete="CASCADE")}
     *               )
     * @Serializer\Exclude()
     */
    private Collection $team_categories;

    /**
     * @ORM\OneToMany(targetEntity="Clarification", mappedBy="contest")
     * @Serializer\Exclude()
     */
    private Collection $clarifications;

    /**
     * @ORM\OneToMany(targetEntity="Submission", mappedBy="contest")
     * @Serializer\Exclude()
     */
    private Collection $submissions;

    /**
     * @ORM\OneToMany(targetEntity="ContestProblem", mappedBy="contest", orphanRemoval=true, cascade={"persist"})
     * @ORM\OrderBy({"shortname" = "ASC"})
     * @Serializer\Exclude()
     * @Assert\Valid()
     */
    private Collection $problems;

    /**
     * @ORM\OneToMany(targetEntity="InternalError", mappedBy="contest")
     * @Serializer\Exclude()
     */
    private Collection $internal_errors;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="App\Entity\RemovedInterval", mappedBy="contest")
     * @Serializer\Exclude()
     * @Assert\Valid()
     */
    private Collection $removedIntervals;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="App\Entity\ExternalContestSource", mappedBy="contest")
     * @Serializer\Exclude()
     * @Assert\Valid()
     */
    private Collection $externalContestSources;

    public function __construct()
    {
        $this->problems               = new ArrayCollection();
        $this->teams                  = new ArrayCollection();
        $this->removedIntervals       = new ArrayCollection();
        $this->clarifications         = new ArrayCollection();
        $this->submissions            = new ArrayCollection();
        $this->internal_errors        = new ArrayCollection();
        $this->team_categories        = new ArrayCollection();
        $this->medal_categories       = new ArrayCollection();
        $this->externalContestSources = new ArrayCollection();
    }

    public function getCid(): ?int
    {
        return $this->cid;
    }

    public function setExternalid(?string $externalid): Contest
    {
        $this->externalid = $externalid;
        return $this;
    }

    public function getExternalid(): ?string
    {
        return $this->externalid;
    }

    public function setName(string $name): Contest
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setShortname(string $shortname): Contest
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

    public function getActivatetime(): ?float
    {
        return $this->activatetime === null ? null : (float)$this->activatetime;
    }

    /** @param string|float $starttime */
    public function setStarttime($starttime): Contest
    {
        $this->starttime = $starttime;
        return $this;
    }

    /**
     * Get starttime, or NULL if disabled.
     *
     * @param bool $nullWhenDisabled If true, return null if the start time is disabled, defaults to true.
     */
    public function getStarttime(bool $nullWhenDisabled = true): ?float
    {
        if ($nullWhenDisabled && !$this->getStarttimeEnabled()) {
            return null;
        }

        return $this->starttime === null ? null : (float)$this->starttime;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("start_time")
     * @Serializer\Type("DateTime")
     */
    public function getStartTimeObject(): ?DateTime
    {
        return $this->getStarttime() ? new DateTime(Utils::absTime($this->getStarttime())) : null;
    }

    public function setStarttimeEnabled(bool $starttimeEnabled): Contest
    {
        $this->starttimeEnabled = $starttimeEnabled;
        return $this;
    }

    public function getStarttimeEnabled(): bool
    {
        return $this->starttimeEnabled;
    }

    public function getFreezetime(): ?float
    {
        return $this->freezetime === null ? null : (float)$this->freezetime;
    }

    public function getEndtime(): ?float
    {
        return $this->endtime === null ? null : (float)$this->endtime;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("end_time")
     * @Serializer\Type("DateTime")
     * @Serializer\Groups({"Nonstrict"})
     */
    public function getEndTimeObject(): ?DateTime
    {
        return $this->getEndtime() ? new DateTime(Utils::absTime($this->getEndtime())) : null;
    }

    public function getUnfreezetime(): ?float
    {
        return $this->unfreezetime === null ? null : (float)$this->unfreezetime;
    }

    public function getFinalizetime(): ?float
    {
        return $this->finalizetime === null ? null : (float)$this->finalizetime;
    }

    /** @param string|float $finalizetimeString */
    public function setFinalizetime($finalizetimeString): Contest
    {
        $this->finalizetime = $finalizetimeString;
        return $this;
    }

    public function getFinalizecomment(): ?string
    {
        return $this->finalizecomment;
    }

    public function setFinalizecomment(?string $finalizecomment): Contest
    {
        $this->finalizecomment = $finalizecomment;
        return $this;
    }

    public function getB(): ?int
    {
        return $this->b;
    }

    public function setB(?int $b)
    {
        $this->b = $b;
    }

    public function getDeactivatetime(): ?float
    {
        return $this->deactivatetime === null ? null : (float)$this->deactivatetime;
    }

    public function setActivatetimeString(?string $activatetimeString): Contest
    {
        $this->activatetimeString = $activatetimeString;
        $this->activatetime       = $this->getAbsoluteTime($activatetimeString);
        return $this;
    }

    public function getActivatetimeString(): ?string
    {
        return $this->activatetimeString;
    }

    public function setStarttimeString(string $starttimeString): Contest
    {
        $this->starttimeString = $starttimeString;

        $this->setActivatetimeString($this->getActivatetimeString());
        $this->setFreezetimeString($this->getFreezetimeString());
        $this->setEndtimeString($this->getEndtimeString());
        $this->setUnfreezetimeString($this->getUnfreezetimeString());
        $this->setDeactivatetimeString($this->getDeactivatetimeString());

        return $this;
    }

    public function getStarttimeString(): string
    {
        return $this->starttimeString;
    }

    public function setFreezetimeString(?string $freezetimeString): Contest
    {
        $this->freezetimeString = $freezetimeString;
        $this->freezetime       = $this->getAbsoluteTime($freezetimeString);
        return $this;
    }

    public function getFreezetimeString(): ?string
    {
        return $this->freezetimeString;
    }

    public function setEndtimeString(?string $endtimeString): Contest
    {
        $this->endtimeString = $endtimeString;
        $this->endtime       = $this->getAbsoluteTime($endtimeString);
        return $this;
    }

    public function getEndtimeString(): ?string
    {
        return $this->endtimeString;
    }

    public function setUnfreezetimeString(?string $unfreezetimeString): Contest
    {
        $this->unfreezetimeString = $unfreezetimeString;
        $this->unfreezetime       = $this->getAbsoluteTime($unfreezetimeString);
        return $this;
    }

    public function getUnfreezetimeString(): ?string
    {
        return $this->unfreezetimeString;
    }

    public function setDeactivatetimeString(?string $deactivatetimeString): Contest
    {
        $this->deactivatetimeString = $deactivatetimeString;
        $this->deactivatetime       = $this->getAbsoluteTime($deactivatetimeString);
        return $this;
    }

    public function getDeactivatetimeString(): ?string
    {
        return $this->deactivatetimeString;
    }

    public function setActivatetime(string $activatetime): Contest
    {
        $this->activatetime = $activatetime;
        return $this;
    }

    public function setFreezetime(string $freezetime): Contest
    {
        $this->freezetime = $freezetime;
        return $this;
    }

    public function setEndtime(string $endtime): Contest
    {
        $this->endtime = $endtime;
        return $this;
    }

    public function setUnfreezetime(string $unfreezetime): Contest
    {
        $this->unfreezetime = $unfreezetime;
        return $this;
    }

    public function setDeactivatetime(string $deactivatetime): Contest
    {
        $this->deactivatetime = $deactivatetime;
        return $this;
    }

    public function setEnabled(bool $enabled): Contest
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    public function setAllowSubmit(bool $allowSubmit): Contest
    {
        $this->allowSubmit = $allowSubmit;
        return $this;
    }

    public function getAllowSubmit(): bool
    {
        return $this->allowSubmit;
    }

    public function getWarningMessage(): ?string
    {
        return $this->warningMessage;
    }

    public function setWarningMessage(?string $warningMessage): Contest
    {
        $this->warningMessage = (empty($warningMessage) ? null : $warningMessage);
        return $this;
    }

    public function setProcessBalloons(bool $processBalloons): Contest
    {
        $this->processBalloons = $processBalloons;
        return $this;
    }

    public function getProcessBalloons(): bool
    {
        return $this->processBalloons;
    }

    public function setMedalsEnabled(bool $medalsEnabled): Contest
    {
        $this->medalsEnabled = $medalsEnabled;
        return $this;
    }

    public function getMedalsEnabled(): bool
    {
        return $this->medalsEnabled;
    }

    /**
     * @return Collection|TeamCategory[]
     */
    public function getMedalCategories(): Collection
    {
        return $this->medal_categories;
    }

    public function addMedalCategory(TeamCategory $medalCategory): Contest
    {
        if (!$this->medal_categories->contains($medalCategory)) {
            $this->medal_categories[] = $medalCategory;
        }

        return $this;
    }

    public function removeMedalCategories(TeamCategory $medalCategory): Contest
    {
        if ($this->medal_categories->contains($medalCategory)) {
            $this->medal_categories->removeElement($medalCategory);
        }

        return $this;
    }

    public function setGoldMedals(int $goldMedals): Contest
    {
        $this->goldMedals = $goldMedals;
        return $this;
    }

    public function getGoldMedals(): int
    {
        return $this->goldMedals;
    }

    public function setSilverMedals(int $silverMedals): Contest
    {
        $this->silverMedals = $silverMedals;
        return $this;
    }

    public function getSilverMedals(): int
    {
        return $this->silverMedals;
    }

    public function setBronzeMedals(int $bronzeMedals): Contest
    {
        $this->bronzeMedals = $bronzeMedals;
        return $this;
    }

    public function getBronzeMedals(): int
    {
        return $this->bronzeMedals;
    }

    public function setPublic(bool $public): Contest
    {
        $this->public = $public;
        return $this;
    }

    public function getPublic(): bool
    {
        return $this->public;
    }

    public function setOpenToAllTeams(bool $openToAllTeams): Contest
    {
        $this->openToAllTeams = $openToAllTeams;
        if ($this->openToAllTeams) {
            $this->teams->clear();
            $this->team_categories->clear();
        }

        return $this;
    }

    public function isOpenToAllTeams(): bool
    {
        return $this->openToAllTeams;
    }

    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    public function setIsLocked(bool $isLocked): Contest
    {
        $this->isLocked = $isLocked;
        return $this;
    }

    public function addTeam(Team $team): Contest
    {
        $this->teams[] = $team;
        return $this;
    }

    public function removeTeam(Team $team): void
    {
        $this->teams->removeElement($team);
    }

    public function getTeams(): Collection
    {
        return $this->teams;
    }

    public function addProblem(ContestProblem $problem): Contest
    {
        $this->problems[] = $problem;
        return $this;
    }

    public function removeProblem(ContestProblem $problem): void
    {
        $this->problems->removeElement($problem);
    }

    public function getProblems(): Collection
    {
        return $this->problems;
    }

    public function addClarification(Clarification $clarification): Contest
    {
        $this->clarifications[] = $clarification;
        return $this;
    }

    public function removeClarification(Clarification $clarification): void
    {
        $this->clarifications->removeElement($clarification);
    }

    public function getClarifications(): Collection
    {
        return $this->clarifications;
    }

    public function addSubmission(Submission $submission): Contest
    {
        $this->submissions[] = $submission;
        return $this;
    }

    public function removeSubmission(Submission $submission): void
    {
        $this->submissions->removeElement($submission);
    }

    public function getSubmissions(): Collection
    {
        return $this->submissions;
    }

    public function addInternalError(InternalError $internalError): Contest
    {
        $this->internal_errors[] = $internalError;
        return $this;
    }

    public function removeInternalError(InternalError $internalError): void
    {
        $this->internal_errors->removeElement($internalError);
    }

    public function getInternalErrors(): Collection
    {
        return $this->internal_errors;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Type("string")
     */
    public function getDuration(): string
    {
        return Utils::relTime($this->getEndtime() - $this->starttime);
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Type("string")
     * @OA\Property(nullable=true)
     */
    public function getScoreboardFreezeDuration(): ?string
    {
        if (!empty($this->getFreezetime())) {
            return Utils::relTime($this->getEndtime() - $this->getFreezetime());
        } else {
            return null;
        }
    }

    /**
     * Returns true iff the contest is already and still active, and not disabled.
     */
    public function isActive(): bool
    {
        return $this->getEnabled() &&
            $this->getPublic() &&
            ($this->activatetime <= time()) &&
            ($this->deactivatetime == null || $this->deactivatetime > time());
    }

    /**
     * @return float|int|null
     */
    public function getAbsoluteTime(?string $time_string)
    {
        if ($time_string === null) {
            return null;
        } elseif (preg_match('/^[+-][0-9]+:[0-9]{2}(:[0-9]{2}(\.[0-9]{0,6})?)?$/', $time_string)) {
            $sign           = ($time_string[0] == '-' ? -1 : +1);
            $time_string[0] = 0;
            $times          = explode(':', $time_string, 3);
            $hours          = (int)$times[0];
            $minutes        = (int)$times[1];
            if (count($times) == 2) {
                $seconds = 0;
            } else {
                $seconds = (float)$times[2];
            }
            $seconds      = $seconds + 60 * ($minutes + 60 * $hours);
            $seconds      *= $sign;
            $absoluteTime = $this->starttime + $seconds;

            // Take into account the removed intervals.
            /** @var RemovedInterval[] $removedIntervals */
            $removedIntervals = $this->getRemovedIntervals()->toArray();
            usort($removedIntervals, function (RemovedInterval $a, RemovedInterval $b) {
                return Utils::difftime((float)$a->getStarttime(), (float)$b->getStarttime());
            });
            foreach ($removedIntervals as $removedInterval) {
                if (Utils::difftime((float)$removedInterval->getStarttime(), (float)$absoluteTime) <= 0) {
                    $absoluteTime += Utils::difftime((float)$removedInterval->getEndtime(),
                                                     (float)$removedInterval->getStarttime());
                }
            }

            return $absoluteTime;
        } else {
            try {
                $date = new DateTime($time_string);
            } catch (Exception $e) {
                return null;
            }
            return $date->format('U.v');
        }
    }

    public function addRemovedInterval(RemovedInterval $removedInterval): Contest
    {
        $this->removedIntervals->add($removedInterval);
        return $this;
    }

    public function removeRemovedInterval(RemovedInterval $removedInterval): void
    {
        $this->removedIntervals->removeElement($removedInterval);
    }

    public function getRemovedIntervals(): Collection
    {
        return $this->removedIntervals;
    }

    public function getContestTime(float $wallTime): float
    {
        $contestTime = Utils::difftime($wallTime, (float)$this->getStarttime(false));
        /** @var RemovedInterval $removedInterval */
        foreach ($this->getRemovedIntervals() as $removedInterval) {
            if (Utils::difftime((float)$removedInterval->getStarttime(), $wallTime) < 0) {
                $contestTime -= min(
                    Utils::difftime($wallTime, (float)$removedInterval->getStarttime()),
                    Utils::difftime((float)$removedInterval->getEndtime(), (float)$removedInterval->getStarttime())
                );
            }
        }

        return $contestTime;
    }

    public function getDataForJuryInterface(): array
    {
        $now         = Utils::now();
        $times       = ['activate', 'start', 'freeze', 'end', 'unfreeze', 'finalize', 'deactivate'];
        $prevchecked = false;
        $isactivated = Utils::difftime((float)$this->getActivatetime(), $now) <= 0;
        $hasstarted  = Utils::difftime((float)$this->getStarttime(), $now) <= 0;
        $hasended    = Utils::difftime((float)$this->getEndtime(), $now) <= 0;
        $hasfrozen   = !empty($this->getFreezetime()) &&
            Utils::difftime((float)$this->getFreezetime(), $now) <= 0;
        $hasunfrozen = !empty($this->getUnfreezetime()) &&
            Utils::difftime((float)$this->getUnfreezetime(), $now) <= 0;
        $isfinal     = !empty($this->getFinalizetime());

        if (!$this->getStarttimeEnabled()) {
            $hasstarted = $hasended = $hasfrozen = $hasunfrozen = false;
        }

        $result = [];
        foreach ($times as $time) {
            $resultItem = [];
            $method     = sprintf('get%stime', ucfirst($time));
            $timeValue  = $this->{$method}();
            if ($time === 'start' && !$this->getStarttimeEnabled()) {
                $resultItem['icon'] = 'ellipsis-h';
                $timeValue          = $this->getStarttime(false);
                $prevchecked        = false;
            } elseif (empty($timeValue)) {
                $resultItem['icon'] = null;
            } elseif (Utils::difftime((float)$timeValue, $now) <= 0) {
                // This event has passed, mark as such.
                $resultItem['icon'] = 'check';
                $prevchecked        = true;
            } elseif ($prevchecked) {
                $resultItem['icon'] = 'ellipsis-h';
                $prevchecked        = false;
            }

            $resultItem['label'] = sprintf('%s time', ucfirst($time));
            $resultItem['time']  = Utils::printtime($timeValue, 'Y-m-d H:i:s (T)');
            if ($time === 'start' && !$this->getStarttimeEnabled()) {
                $resultItem['class'] = 'ignore';
            }

            $showButton = true;
            switch ($time) {
                case 'activate':
                    $showButton = !$isactivated;
                    break;
                case 'start':
                    $showButton = !$hasstarted;
                    break;
                case 'end':
                    $showButton = $hasstarted && !$hasended && (empty($this->getFreezetime()) || $hasfrozen);
                    break;
                case 'deactivate':
                    $showButton = $hasended && (empty($this->getUnfreezetime()) || $hasunfrozen);
                    break;
                case 'freeze':
                    $showButton = $hasstarted && !$hasended && !$hasfrozen;
                    break;
                case 'unfreeze':
                    $showButton = $hasfrozen && !$hasunfrozen && $hasended;
                    break;
                case 'finalize':
                    $showButton = $hasended && !$isfinal;
                    break;
            }

            $resultItem['show_button'] = $showButton;

            $closeToStart = Utils::difftime((float)$this->starttime,
                                            $now) <= self::STARTTIME_UPDATE_MIN_SECONDS_BEFORE;
            if ($time === 'start' && !$closeToStart) {
                $type                       = $this->getStarttimeEnabled() ? 'delay' : 'resume';
                $resultItem['extra_button'] = [
                    'type' => $type . '_start',
                    'label' => $type . ' start',
                ];
            }

            $result[$time] = $resultItem;
        }

        return $result;
    }

    public function getState(): ?array
    {
        $time_or_null             = function ($time, $extra_cond = true) {
            if (!$extra_cond || $time === null || Utils::now() < $time) {
                return null;
            }
            return Utils::absTime($time);
        };
        $result                   = [];
        $result['started']        = $time_or_null($this->getStarttime());
        $result['ended']          = $time_or_null($this->getEndtime(), $result['started'] !== null);
        $result['frozen']         = $time_or_null($this->getFreezetime(), $result['started'] !== null);
        $result['thawed']         = $time_or_null($this->getUnfreezetime(), $result['frozen'] !== null);
        $result['finalized']      = $time_or_null($this->getFinalizetime(), $result['ended'] !== null);
        $result['end_of_updates'] = null;
        if ($result['finalized'] !== null &&
            ($result['thawed'] !== null || $result['frozen'] === null)) {
            if ($result['thawed'] !== null &&
                $this->getFreezetime() > $this->getFinalizetime()) {
                $result['end_of_updates'] = $result['thawed'];
            } else {
                $result['end_of_updates'] = $result['finalized'];
            }
        }
        return $result;
    }

    public function getMinutesRemaining(): int
    {
        return (int)floor(($this->getEndtime() - $this->getFreezetime()) / 60);
    }

    public function getFreezeData(): FreezeData
    {
        return new FreezeData($this);
    }

    /**
     * @ORM\PrePersist()
     * @ORM\PreUpdate()
     */
    public function updateTimes(): void
    {
        // Update the start times, as this will update all other fields.
        $this->setStarttime((float)strtotime($this->getStarttimeString()));
        $this->setStarttimeString($this->getStarttimeString());
    }

    /**
     * @Assert\Callback()
     */
    public function validate(ExecutionContextInterface $context): void
    {
        $this->updateTimes();
        if (Utils::difftime((float)$this->getEndtime(), (float)$this->getStarttime(true)) <= 0) {
            $context
                ->buildViolation('Contest ends before it even starts')
                ->atPath('endtimeString')
                ->addViolation();
        }
        if (!empty($this->getFreezetime())) {
            if (Utils::difftime((float)$this->getFreezetime(), (float)$this->getEndtime()) > 0 ||
                Utils::difftime((float)$this->getFreezetime(), (float)$this->getStarttime()) < 0) {
                $context
                    ->buildViolation('Freezetime is out of start/endtime range')
                    ->atPath('freezetimeString')
                    ->addViolation();
            }
        }
        if (Utils::difftime((float)$this->getActivatetime(), (float)$this->getStarttime(false)) > 0) {
            $context
                ->buildViolation('Activate time is later than starttime')
                ->atPath('activatetimeString')
                ->addViolation();
        }
        if (!empty($this->getUnfreezetime())) {
            if (empty($this->getFreezetime())) {
                $context
                    ->buildViolation('Unfreezetime set but no freeze time. That makes no sense.')
                    ->atPath('unfreezetimeString')
                    ->addViolation();
            }
            if (Utils::difftime((float)$this->getUnfreezetime(), (float)$this->getEndtime()) < 0) {
                $context
                    ->buildViolation('Unfreezetime must be larger than endtime.')
                    ->atPath('unfreezetimeString')
                    ->addViolation();
            }
            if (!empty($this->getDeactivatetime()) &&
                Utils::difftime((float)$this->getDeactivatetime(), (float)$this->getUnfreezetime()) < 0) {
                $context
                    ->buildViolation('Deactivatetime must be larger than unfreezetime.')
                    ->atPath('deactivatetimeString')
                    ->addViolation();
            }
        } else {
            if (!empty($this->getDeactivatetime()) &&
                Utils::difftime((float)$this->getDeactivatetime(), (float)$this->getEndtime()) < 0) {
                $context
                    ->buildViolation('Deactivatetime must be larger than endtime.')
                    ->atPath('deactivatetimeString')
                    ->addViolation();
            }
        }

        if ($this->medalsEnabled) {
            foreach (['goldMedals', 'silverMedals', 'bronzeMedals'] as $field) {
                if ($this->$field === null) {
                    $context
                        ->buildViolation('This field is required when \'Enable medals\' is set.')
                        ->atPath($field)
                        ->addViolation();
                }
            }
            if ($this->medal_categories === null || $this->medal_categories->isEmpty()) {
                $context
                    ->buildViolation('This field is required when \'Process medals\' is set.')
                    ->atPath('medalCategories')
                    ->addViolation();
            }
        }

        /** @var ContestProblem $problem */
        foreach ($this->problems as $idx => $problem) {
            // Check if the problem ID is unique.
            $otherProblemIds = $this->problems
                ->filter(fn(ContestProblem $otherProblem) => $otherProblem !== $problem)
                ->map(fn(ContestProblem $problem) => $problem->getProblem()->getProbid())
                ->toArray();
            $problemId       = $problem->getProblem()->getProbid();
            if (in_array($problemId, $otherProblemIds)) {
                $context
                    ->buildViolation('Each problem can only be added to a contest once')
                    ->atPath(sprintf('problems[%d].problem', $idx))
                    ->addViolation();
            }

            // Check if the problem shortname is unique.
            $otherShortNames = $this->problems
                ->filter(fn(ContestProblem $otherProblem) => $otherProblem !== $problem)
                ->map(fn(ContestProblem $problem) => strtolower($problem->getShortname()))
                ->toArray();
            $shortname = strtolower($problem->getShortname());
            if (in_array($shortname, $otherShortNames)) {
                $context
                    ->buildViolation('Each shortname should be unique within a contest')
                    ->atPath(sprintf('problems[%d].shortname', $idx))
                    ->addViolation();
            }
        }
    }

    /**
     * Return whether a (wall clock) time falls within the contest.
     */
    public function isTimeInContest(float $time): bool
    {
        return Utils::difftime((float)$this->getStarttime(), $time) <= 0 &&
               Utils::difftime((float)$this->getEndtime(), $time) > 0;
    }

    public function getCountdownString(): string
    {
        $now = Utils::now();
        if (Utils::difftime((float)$this->getActivatetime(), $now) <= 0) {
            if (!$this->getStarttimeEnabled()) {
                return 'start delayed';
            }
            if ($this->isTimeInContest($now)) {
                return Utils::printtimediff($now, (float)$this->getEndtime());
            } elseif (Utils::difftime((float)$this->getStarttime(), $now) >= 0) {
                return 'time to start: ' . Utils::printtimediff($now, (float)$this->getStarttime());
            }
        }

        return '';
    }

    public function getOpenToAllTeams(): ?bool
    {
        return $this->openToAllTeams;
    }

    /**
     * @return Collection|TeamCategory[]
     */
    public function getTeamCategories(): Collection
    {
        return $this->team_categories;
    }

    public function addTeamCategory(TeamCategory $teamCategory): self
    {
        if (!$this->team_categories->contains($teamCategory)) {
            $this->team_categories[] = $teamCategory;
        }

        return $this;
    }

    public function removeTeamCategory(TeamCategory $teamCategory): self
    {
        if ($this->team_categories->contains($teamCategory)) {
            $this->team_categories->removeElement($teamCategory);
        }

        return $this;
    }

    /**
     * @return Collection|ExternalContestSource[]
     */
    public function getExternalContestSources(): Collection
    {
        return $this->externalContestSources;
    }

    public function addExternalContestSource(ExternalContestSource $externalContestSource): self
    {
        if (!$this->externalContestSources->contains($externalContestSource)) {
            $this->externalContestSources[] = $externalContestSource;
        }

        return $this;
    }

    public function removeExternalContestSource(ExternalContestSource $externalContestSource): self
    {
        if ($this->externalContestSources->contains($externalContestSource)) {
            $this->externalContestSources->removeElement($externalContestSource);
        }

        return $this;
    }

    public function getBannerFile(): ?UploadedFile
    {
        return $this->bannerFile;
    }

    public function setBannerFile(?UploadedFile $bannerFile): Contest
    {
        $this->bannerFile = $bannerFile;
        return $this;
    }

    public function isClearBanner(): bool
    {
        return $this->clearBanner;
    }

    public function setClearBanner(bool $clearBanner): Contest
    {
        $this->clearBanner = $clearBanner;
        return $this;
    }

    public function getAssetProperties(): array
    {
        return ['banner'];
    }

    public function getAssetFile(string $property): ?UploadedFile
    {
        switch ($property) {
            case 'banner':
                return $this->getBannerFile();
        }

        return null;
    }

    public function isClearAsset(string $property): ?bool
    {
        switch ($property) {
            case 'banner':
                return $this->isClearBanner();
        }

        return null;
    }
}
