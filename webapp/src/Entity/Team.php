<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
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
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"},
 *     indexes={
 *         @ORM\Index(name="affilid", columns={"affilid"}),
 *         @ORM\Index(name="categoryid", columns={"categoryid"})
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="icpcid", columns={"icpcid"}, options={"lengths": {190}}),
 *     })
 * @UniqueEntity("icpcid")
 */
class Team extends BaseApiEntity implements ExternalRelationshipEntityInterface, AssetEntityInterface
{
    const DONT_ADD_USER = 'dont-add-user';
    const CREATE_NEW_USER = 'create-new-user';
    const ADD_EXISTING_USER = 'add-existing-user';

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="teamid", length=4, options={"comment"="Team ID", "unsigned"=true}, nullable=false)
     * @Serializer\SerializedName("id")
     * @Serializer\Type("string")
     */
    protected $teamid;

    /**
     * @var string
     * @ORM\Column(type="string", name="icpcid", length=255, options={"comment"="Team ID in the ICPC system",
     *                            "collation"="utf8mb4_bin"}, nullable=true)
     * @Serializer\SerializedName("icpc_id")
     */
    protected $icpcid;

    /**
     * @var string
     * @ORM\Column(type="string", name="name", length=255, options={"comment"="Team name", "collation"="utf8mb4_bin"},
     *                            nullable=false)
     */
    private $name = '';

    /**
     * @var string|null
     * @ORM\Column(type="string", name="display_name", length=255, options={"comment"="Team display name", "collation"="utf8mb4_bin"},
     *                            nullable=true)
     */
    private $display_name = null;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="enabled",
     *     options={"comment"="Whether the team is visible and operational",
     *              "default"=1},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $enabled = true;

    /**
     * @var string
     * @ORM\Column(type="text", length=4294967295, name="members", options={"comment"="Team member names (freeform)"},
     *                          nullable=true)
     * @Serializer\Groups({"Nonstrict"})
     */
    private $members;

    /**
     * @var string
     * @ORM\Column(type="string", length=255, name="room", options={"comment"="Physical location of team"},
     *                            nullable=true)
     * @Serializer\Exclude()
     */
    private $room;

    /**
     * @var string
     * @ORM\Column(type="text", length=4294967295, name="comments", options={"comment"="Comments about this team"},
     *                          nullable=true)
     * @Serializer\Exclude()
     */
    private $comments;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="judging_last_started",
     *     options={"comment"="Start time of last judging for priorization",
     *              "unsigned"=true}, nullable=true)
     * @Serializer\Exclude()
     */
    private $judging_last_started;

    /**
     * @var int
     * @ORM\Column(type="integer", name="penalty",
     *     options={"comment"="Additional penalty time in minutes","default"=0},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $penalty = 0;

    /**
     * @var string
     * @Serializer\Exclude()
     */
    private $addUserForTeam = self::DONT_ADD_USER;

    /**
     * @var string|null
     * @Assert\Regex("/^[a-z0-9@._-]+$/i", message="Only alphanumeric characters and _-@. are allowed")
     * @Serializer\Exclude
     */
    private $newUsername;

    /**
     * @var User|null
     * @Serializer\Exclude
     */
    private $existingUser;

    /**
     * @var UploadedFile|null
     * @Assert\File(mimeTypes={"image/png","image/jpeg","image/svg+xml"}, mimeTypesMessage="Only PNG's, JPG's and SVG's are allowed")
     * @Serializer\Exclude()
     */
    private $photoFile;

    /**
     * @var bool
     * @Serializer\Exclude()
     */
    private $clearPhoto = false;

    /**
     * @ORM\ManyToOne(targetEntity="TeamAffiliation", inversedBy="teams")
     * @ORM\JoinColumn(name="affilid", referencedColumnName="affilid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private $affiliation;

    /**
     * @ORM\ManyToOne(targetEntity="TeamCategory", inversedBy="teams")
     * @ORM\JoinColumn(name="categoryid", referencedColumnName="categoryid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private $category;

    /**
     * @ORM\ManyToMany(targetEntity="Contest", mappedBy="teams")
     * @Serializer\Exclude()
     */
    private $contests;

    /**
     * @ORM\OneToMany(targetEntity="User", mappedBy="team", cascade={"persist"})
     * @Serializer\Exclude()
     * @Assert\Valid()
     */
    private $users;

    /**
     * @ORM\OneToMany(targetEntity="Submission", mappedBy="team")
     * @Serializer\Exclude()
     */
    private $submissions;

    /**
     * @ORM\OneToMany(targetEntity="Clarification", mappedBy="sender")
     * @Serializer\Exclude()
     */
    private $sent_clarifications;

    /**
     * @ORM\OneToMany(targetEntity="Clarification", mappedBy="recipient")
     * @Serializer\Exclude()
     */
    private $received_clarifications;

    /**
     * @ORM\ManyToMany(targetEntity="Clarification")
     * @ORM\JoinTable(name="team_unread",
     *                joinColumns={@ORM\JoinColumn(name="teamid", referencedColumnName="teamid", onDelete="CASCADE")},
     *                inverseJoinColumns={@ORM\JoinColumn(name="mesgid", referencedColumnName="clarid", onDelete="CASCADE")}
     * )
     * @Serializer\Exclude()
     */
    private $unread_clarifications;

    public function setTeamid(int $teamid): Team
    {
        $this->teamid = $teamid;
        return $this;
    }

    public function getTeamid(): ?int
    {
        return $this->teamid;
    }

    public function setIcpcid(?string $icpcid): Team
    {
        $this->icpcid = $icpcid;

        return $this;
    }

    public function getIcpcid(): ?string
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

    public function setMembers(?string $members): Team
    {
        $this->members = $members;
        return $this;
    }

    public function getMembers(): ?string
    {
        return $this->members;
    }

    public function setRoom(string $room): Team
    {
        $this->room = $room;
        return $this;
    }

    public function getRoom(): ?string
    {
        return $this->room;
    }

    public function setComments(string $comments): Team
    {
        $this->comments = $comments;
        return $this;
    }

    public function getComments(): ?string
    {
        return $this->comments;
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
    public function setAddUserForTeam(string $addUserForTeam)
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

    public function removeContest(Contest $contest)
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

    public function removeUser(User $user)
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

    public function removeSubmission(Submission $submission)
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

    public function removeReceivedClarification(Clarification $receivedClarification)
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

    public function removeUnreadClarification(Clarification $unreadClarification)
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
        return ['organization_id' => $this->getAffiliation()];
    }

    /**
     * @Assert\Callback()
     */
    public function validate(ExecutionContextInterface $context)
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
