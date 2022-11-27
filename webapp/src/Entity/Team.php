<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use OpenApi\Annotations as OA;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * All teams participating in the contest.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="team",
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4"},
 *     indexes={
 *         @ORM\Index(name="affilid", columns={"affilid"}),
 *         @ORM\Index(name="categoryid", columns={"categoryid"})
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="externalid", columns={"externalid"}, options={"lengths": {190}}),
 *     })
 * @UniqueEntity("externalid")
 */
class Team extends BaseApiEntity implements ExternalRelationshipEntityInterface, AssetEntityInterface
{
    const DONT_ADD_USER = 'dont-add-user';
    const CREATE_NEW_USER = 'create-new-user';
    const ADD_EXISTING_USER = 'add-existing-user';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="teamid", length=4, options={"comment"="Team ID", "unsigned"=true}, nullable=false)
     * @Serializer\SerializedName("id")
     * @Serializer\Type("string")
     */
    protected ?int $teamid = null;

    /**
     * @ORM\Column(type="string", name="externalid", length=255,
     *     options={"comment"="Team ID in an external system",
     *              "collation"="utf8mb4_bin"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    protected ?string $externalid = null;

    /**
     * @ORM\Column(type="string", name="icpcid", length=255, options={"comment"="Team ID in the ICPC system",
     *                            "collation"="utf8mb4_bin"}, nullable=true)
     * @Serializer\SerializedName("icpc_id")
     * @OA\Property(nullable=true)
     */
    protected ?string $icpcid;

    /**
     * @ORM\Column(type="string", name="name", length=255, options={"comment"="Team name", "collation"="utf8mb4_bin"},
     *                            nullable=false)
     */
    private string $name = '';

    /**
     * @ORM\Column(type="string", name="display_name", length=255,
     *     options={"comment"="Team display name", "collation"="utf8mb4_bin"},
     *                            nullable=true)
     * @OA\Property(nullable=true)
     */
    private ?string $display_name = null;

    /**
     * @ORM\Column(type="boolean", name="enabled",
     *     options={"comment"="Whether the team is visible and operational",
     *              "default"=1},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private bool $enabled = true;

    /**
     * @ORM\Column(type="text", length=4294967295, name="publicdescription",
     *     options={"comment"="Public team definition; for example: Team member names (freeform)"},
     *                          nullable=true)
     * @Serializer\Groups({"Nonstrict"})
     * @OA\Property(nullable=true)
     */
    private ?string $publicDescription;

    /**
     * @ORM\Column(type="string", length=255, name="room", options={"comment"="Physical location of team"},
     *                            nullable=true)
     * @Serializer\Exclude()
     */
    private ?string $room;

    /**
     * @ORM\Column(type="text", length=4294967295, name="internalcomments",
     *     options={"comment"="Internal comments about this team (jury only)"},
     *                          nullable=true)
     * @Serializer\Exclude()
     */
    private ?string $internalComments;

    /**
     * @var double|string|null
     * @ORM\Column(type="decimal", precision=32, scale=9, name="judging_last_started",
     *     options={"comment"="Start time of last judging for prioritization",
     *              "unsigned"=true}, nullable=true)
     * @Serializer\Exclude()
     */
    private $judging_last_started;

    /**
     * @ORM\Column(type="integer", name="penalty",
     *     options={"comment"="Additional penalty time in minutes","default"=0},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private int $penalty = 0;

    /**
     * @Serializer\Exclude()
     */
    private string $addUserForTeam = self::DONT_ADD_USER;

    /**
     * @Assert\Regex("/^[a-z0-9@._-]+$/i", message="Only alphanumeric characters and _-@. are allowed")
     * @Serializer\Exclude
     */
    private ?string $newUsername;

    /**
     * @Serializer\Exclude
     */
    private ?User $existingUser;

    /**
     * @Assert\File(mimeTypes={"image/png","image/jpeg","image/svg+xml"}, mimeTypesMessage="Only PNG's, JPG's and SVG's are allowed")
     * @Serializer\Exclude()
     */
    private ?UploadedFile $photoFile = null;

    /**
     * @Serializer\Exclude()
     */
    private bool $clearPhoto = false;

    /**
     * @ORM\ManyToOne(targetEntity="TeamAffiliation", inversedBy="teams")
     * @ORM\JoinColumn(name="affilid", referencedColumnName="affilid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private ?TeamAffiliation $affiliation;

    /**
     * @ORM\ManyToOne(targetEntity="TeamCategory", inversedBy="teams")
     * @ORM\JoinColumn(name="categoryid", referencedColumnName="categoryid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private ?TeamCategory $category;

    /**
     * @ORM\ManyToMany(targetEntity="Contest", mappedBy="teams")
     * @Serializer\Exclude()
     */
    private Collection $contests;

    /**
     * @ORM\OneToMany(targetEntity="User", mappedBy="team", cascade={"persist"})
     * @Serializer\Exclude()
     * @Assert\Valid()
     */
    private Collection $users;

    /**
     * @ORM\OneToMany(targetEntity="Submission", mappedBy="team")
     * @Serializer\Exclude()
     */
    private Collection $submissions;

    /**
     * @ORM\OneToMany(targetEntity="Clarification", mappedBy="sender")
     * @Serializer\Exclude()
     */
    private Collection $sent_clarifications;

    /**
     * @ORM\OneToMany(targetEntity="Clarification", mappedBy="recipient")
     * @Serializer\Exclude()
     */
    private Collection $received_clarifications;

    /**
     * @ORM\ManyToMany(targetEntity="Clarification")
     * @ORM\JoinTable(name="team_unread",
     *                joinColumns={@ORM\JoinColumn(name="teamid", referencedColumnName="teamid", onDelete="CASCADE")},
     *                inverseJoinColumns={@ORM\JoinColumn(name="mesgid", referencedColumnName="clarid", onDelete="CASCADE")}
     * )
     * @Serializer\Exclude()
     */
    private Collection $unread_clarifications;

    public function setTeamid(int $teamid): Team
    {
        $this->teamid = $teamid;
        return $this;
    }

    public function getTeamid(): ?int
    {
        return $this->teamid;
    }

    public function setExternalid(?string $externalid): Team
    {
        $this->externalid = $externalid;
        return $this;
    }

    public function getExternalid(): ?string
    {
        return $this->externalid;
    }

    public function setIcpcid(?string $icpcid): Team
    {
        $this->icpcid = $icpcid;

        return $this;
    }

    public function getIcpcId(): ?string
    {
        return $this->icpcid;
    }

    public function setName(string $name): Team
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setDisplayName(?string $display_name): self
    {
        $this->display_name = $display_name;
        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->display_name;
    }

    public function getEffectiveName(): string
    {
        return $this->getDisplayName() ?? $this->getName();
    }

    public function getShortDescription(): string
    {
        return $this->getEffectiveName();
    }

    public function setEnabled(bool $enabled): Team
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    public function setPublicDescription(?string $publicDescription): Team
    {
        $this->publicDescription = $publicDescription;
        return $this;
    }

    public function getPublicDescription(): ?string
    {
        return $this->publicDescription;
    }

    public function setRoom(?string $room): Team
    {
        $this->room = $room;
        return $this;
    }

    public function getRoom(): ?string
    {
        return $this->room;
    }

    public function setInternalComments(?string $comments): Team
    {
        $this->internalComments = $comments;
        return $this;
    }

    public function getInternalComments(): ?string
    {
        return $this->internalComments;
    }

    /** @param string|float $judgingLastStarted */
    public function setJudgingLastStarted($judgingLastStarted): Team
    {
        $this->judging_last_started = $judgingLastStarted;
        return $this;
    }

    /** @return string|float */
    public function getJudgingLastStarted()
    {
        return $this->judging_last_started;
    }

    public function setPenalty(int $penalty): Team
    {
        $this->penalty = $penalty;
        return $this;
    }

    /**
     * Set whether to add a new user for this team, link an existing one or do nothing.
     * Will not be stored, but is used in validation.
     */
    public function setAddUserForTeam(string $addUserForTeam): void
    {
        $this->addUserForTeam = $addUserForTeam;
    }

    /**
     * Set the username of a new user to add when $addUserForTeam is
     * static::CREATE_NEW_USER
     * Will not be stored, but is used in validation.
     */
    public function setNewUsername(?string $newUsername): Team
    {
        $this->newUsername = $newUsername;
        return $this;
    }

    /**
     * Set the user to link when $addUserForTeam is
     * static::ADD_EXISTING_USER
     * Will not be stored, but is used in validation.
     */
    public function setExistingUser(?User $existingUser): Team
    {
        $this->existingUser = $existingUser;
        return $this;
    }

    public function getPenalty(): int
    {
        return $this->penalty;
    }

    public function getAddUserForTeam(): string
    {
        return $this->addUserForTeam;
    }

    public function getNewUsername(): ?string
    {
        return $this->newUsername;
    }

    public function getExistingUser(): ?User
    {
        return $this->existingUser;
    }

    public function getPhotoFile(): ?UploadedFile
    {
        return $this->photoFile;
    }

    public function setPhotoFile(?UploadedFile $photoFile): Team
    {
        $this->photoFile = $photoFile;
        return $this;
    }

    public function isClearPhoto(): bool
    {
        return $this->clearPhoto;
    }

    public function setClearPhoto(bool $clearPhoto): Team
    {
        $this->clearPhoto = $clearPhoto;
        return $this;
    }

    public function setAffiliation(TeamAffiliation $affiliation = null): Team
    {
        $this->affiliation = $affiliation;
        return $this;
    }

    public function getAffiliation(): ?TeamAffiliation
    {
        return $this->affiliation;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("organization_id")
     * @Serializer\Type("string")
     * @OA\Property(nullable=true)
     */
    public function getAffiliationId(): ?int
    {
        return $this->getAffiliation() ? $this->getAffiliation()->getAffilid() : null;
    }

    public function setCategory(?TeamCategory $category = null): Team
    {
        $this->category = $category;
        return $this;
    }

    public function getCategory(): ?TeamCategory
    {
        return $this->category;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("hidden")
     * @Serializer\Type("bool")
     */
    public function getHidden(): bool
    {
        return !$this->getCategory() || !$this->getCategory()->getVisible();
    }

    public function __construct()
    {
        $this->contests                = new ArrayCollection();
        $this->users                   = new ArrayCollection();
        $this->submissions             = new ArrayCollection();
        $this->sent_clarifications     = new ArrayCollection();
        $this->received_clarifications = new ArrayCollection();
        $this->unread_clarifications   = new ArrayCollection();
    }

    public function addContest(Contest $contest): Team
    {
        $this->contests[] = $contest;
        $contest->addTeam($this);
        return $this;
    }

    public function removeContest(Contest $contest): void
    {
        $this->contests->removeElement($contest);
        $contest->removeTeam($this);
    }

    public function getContests(): Collection
    {
        return $this->contests;
    }

    public function addUser(User $user): Team
    {
        $this->users[] = $user;
        $user->setTeam($this);
        return $this;
    }

    public function removeUser(User $user): void
    {
        $this->users->removeElement($user);
    }

    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addSubmission(Submission $submission): Team
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

    public function addSentClarification(Clarification $sentClarification): Team
    {
        $this->sent_clarifications[] = $sentClarification;
        return $this;
    }

    public function removeSentClarification(Clarification $sentClarification)
    {
        $this->sent_clarifications->removeElement($sentClarification);
    }

    public function getSentClarifications(): Collection
    {
        return $this->sent_clarifications;
    }

    public function addReceivedClarification(Clarification $receivedClarification): Team
    {
        $this->received_clarifications[] = $receivedClarification;
        return $this;
    }

    public function removeReceivedClarification(Clarification $receivedClarification): void
    {
        $this->received_clarifications->removeElement($receivedClarification);
    }

    public function getReceivedClarifications(): Collection
    {
        return $this->received_clarifications;
    }

    public function addUnreadClarification(Clarification $unreadClarification): Team
    {
        $this->unread_clarifications[] = $unreadClarification;
        return $this;
    }

    public function removeUnreadClarification(Clarification $unreadClarification): void
    {
        $this->unread_clarifications->removeElement($unreadClarification);
    }

    public function getUnreadClarifications(): Collection
    {
        return $this->unread_clarifications;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Type("array<string>")
     */
    public function getGroupIds(): array
    {
        return $this->getCategory() ? [$this->getCategory()->getCategoryid()] : [];
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("affiliation")
     * @Serializer\Type("string")
     * @Serializer\Groups({"Nonstrict"})
     * @OA\Property(nullable=true)
     */
    public function getAffiliationName(): ?string
    {
        return $this->getAffiliation() ? $this->getAffiliation()->getName() : null;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Type("string")
     * @Serializer\Groups({"Nonstrict"})
     * @Serializer\Expose(if="context.getAttribute('config_service').get('show_flags')")
     * @OA\Property(nullable=true)
     */
    public function getNationality() : ?string
    {
        return $this->getAffiliation() ? $this->getAffiliation()->getCountry() : null;
    }

    public function canViewClarification(Clarification $clarification): bool
    {
        return (($clarification->getSender() && $clarification->getSender()->getTeamid() === $this->getTeamid()) ||
            ($clarification->getRecipient() && $clarification->getRecipient()->getTeamid() === $this->getTeamid()) ||
            ($clarification->getSender() === null && $clarification->getRecipient() === null));
    }

    /**
     * @inheritdoc
     */
    public function getExternalRelationships(): array
    {
        return [
            'organization_id' => $this->getAffiliation(),
            'group_ids'       => array_values(array_filter([$this->getCategory()])),
        ];
    }

    /**
     * @Assert\Callback()
     */
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->getAddUserForTeam() === static::CREATE_NEW_USER) {
            if (empty($this->getNewUsername())) {
                $context
                    ->buildViolation('Required when adding a user')
                    ->atPath('newUsername')
                    ->addViolation();
            } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $this->getNewUsername())) {
                $context
                    ->buildViolation('May only contain [a-zA-Z0-9_-].')
                    ->atPath('newUsername')
                    ->addViolation();
            }
        }
    }

    public function inContest(Contest $contest): bool
    {
        return $contest->isOpenToAllTeams() ||
            $this->getContests()->contains($contest) ||
            ($this->getCategory() !== null && $this->getCategory()->inContest($contest));
    }

    public function getAssetProperties(): array
    {
        return ['photo'];
    }

    public function getAssetFile(string $property): ?UploadedFile
    {
        switch ($property) {
            case 'photo':
                return $this->getPhotoFile();
        }

        return null;
    }

    public function isClearAsset(string $property): ?bool
    {
        switch ($property) {
            case 'photo':
                return $this->isClearPhoto();
        }

        return null;
    }
}
