<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * All teams participating in the contest
 * @ORM\Entity()
 * @ORM\Table(
 *     name="team",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"},
 *     indexes={
 *         @ORM\Index(name="affilid", columns={"affilid"}),
 *         @ORM\Index(name="categoryid", columns={"categoryid"})
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="icpcid", columns={"icpcid"}, options={"lengths": {"190"}}),
 *     })
 * @UniqueEntity("icpcid")
 */
class Team extends BaseApiEntity implements ExternalRelationshipEntityInterface
{
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
     *                            "collation"="utf8mb4_bin","default"="NULL"}, nullable=true)
     * @Serializer\SerializedName("icpc_id")
     */
    protected $icpcid;

    /**
     * @var string
     * @ORM\Column(type="string", name="name", length=255, options={"comment"="Team name", "collation"="utf8mb4_bin"},
     *                            nullable=false)
     */
    private $name;

    /**
     * @var string|null
     * @ORM\Column(type="string", name="display_name", length=255, options={"comment"="Team display name", "collation"="utf8mb4_bin"},
     *                            nullable=true)
     */
    private $display_name;

    /**
     * @var int
     * @ORM\Column(type="integer", name="categoryid", options={"comment"="Team category ID","unsigned"="true","default"=0}, nullable=false)
     * @Serializer\Exclude()
     */
    private $categoryid = 0;

    /**
     * @var int
     * @ORM\Column(type="integer", name="affilid", options={"comment"="Team affiliation ID","unsigned"="true","default"="NULL"}, nullable=true)
     * @Serializer\SerializedName("organization_id")
     * @Serializer\Type("string")
     */
    private $affilid;

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
     * @ORM\Column(type="text", length=4294967295, name="members", options={"comment"="Team member names (freeform)","default"="NULL"},
     *                          nullable=true)
     * @Serializer\Groups({"Nonstrict"})
     */
    private $members;

    /**
     * @var string
     * @ORM\Column(type="string", length=255, name="room", options={"comment"="Physical location of team","default"="NULL"},
     *                            nullable=true)
     * @Serializer\Exclude()
     */
    private $room;

    /**
     * @var string
     * @ORM\Column(type="text", length=4294967295, name="comments", options={"comment"="Comments about this team","default"="NULL"},
     *                          nullable=true)
     * @Serializer\Exclude()
     */
    private $comments;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="judging_last_started",
     *     options={"comment"="Start time of last judging for priorization",
     *              "unsigned"=true,"default"="NULL"}, nullable=true)
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
     * @var bool
     * @Serializer\Exclude()
     */
    private $addUserForTeam = false;

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
     * @ORM\OneToMany(targetEntity="User", mappedBy="team")
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

    /**
     * Set teamid
     *
     * @param int $teamid
     *
     * @return Team
     */
    public function setTeamid($teamid)
    {
        $this->teamid = $teamid;

        return $this;
    }

    /**
     * Get teamid
     *
     * @return integer
     */
    public function getTeamid()
    {
        return $this->teamid;
    }

    /**
     * Set icpcid
     *
     * @param string $icpcid
     *
     * @return Team
     */
    public function setIcpcid($icpcid)
    {
        $this->icpcid = $icpcid;

        return $this;
    }

    /**
     * Get icpcid
     *
     * @return string
     */
    public function getIcpcid()
    {
        return $this->icpcid;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return Team
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set display name
     * @param string|null $display_name
     *
     * @return $this
     */
    public function setDisplayName(?string $display_name): self
    {
        $this->display_name = $display_name;

        return $this;
    }

    /**
     * Get display name
     * @return string|null
     */
    public function getDisplayName(): ?string
    {
        return $this->display_name;
    }

    /**
     * Get the effective name of this team
     * @return string
     */
    public function getEffectiveName(): string
    {
        return $this->getDisplayName() ?? $this->getName();
    }

    /**
     * Set categoryid
     *
     * @param integer $categoryid
     *
     * @return Team
     */
    public function setCategoryid($categoryid)
    {
        $this->categoryid = $categoryid;

        return $this;
    }

    /**
     * Get categoryid
     *
     * @return integer
     */
    public function getCategoryid()
    {
        return $this->categoryid;
    }

    /**
     * Set affilid
     *
     * @param integer $affilid
     *
     * @return Team
     */
    public function setAffilid($affilid)
    {
        $this->affilid = $affilid;

        return $this;
    }

    /**
     * Get affilid
     *
     * @return integer
     */
    public function getAffilid()
    {
        return $this->affilid;
    }

    /**
     * Set enabled
     *
     * @param boolean $enabled
     *
     * @return Team
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * Get enabled
     *
     * @return boolean
     */
    public function getEnabled()
    {
        return $this->enabled;
    }

    /**
     * Set members
     *
     * @param string $members
     *
     * @return Team
     */
    public function setMembers($members)
    {
        $this->members = $members;

        return $this;
    }

    /**
     * Get members
     *
     * @return string
     */
    public function getMembers()
    {
        return $this->members;
    }

    /**
     * Set room
     *
     * @param string $room
     *
     * @return Team
     */
    public function setRoom($room)
    {
        $this->room = $room;

        return $this;
    }

    /**
     * Get room
     *
     * @return string
     */
    public function getRoom()
    {
        return $this->room;
    }

    /**
     * Set comments
     *
     * @param string $comments
     *
     * @return Team
     */
    public function setComments($comments)
    {
        $this->comments = $comments;

        return $this;
    }

    /**
     * Get comments
     *
     * @return string
     */
    public function getComments()
    {
        return $this->comments;
    }

    /**
     * Set judgingLastStarted
     *
     * @param string $judgingLastStarted
     *
     * @return Team
     */
    public function setJudgingLastStarted($judgingLastStarted)
    {
        $this->judging_last_started = $judgingLastStarted;

        return $this;
    }

    /**
     * Get judgingLastStarted
     *
     * @return string
     */
    public function getJudgingLastStarted()
    {
        return $this->judging_last_started;
    }

    /**
     * Set penalty
     *
     * @param integer $penalty
     *
     * @return Team
     */
    public function setPenalty($penalty)
    {
        $this->penalty = $penalty;

        return $this;
    }

    /**
     * Set whether to add a user for this team. Will not be stored, but is used in validation
     *
     * @param bool $addUserForTeam
     */
    public function setAddUserForTeam(bool $addUserForTeam)
    {
        $this->addUserForTeam = $addUserForTeam;
    }

    /**
     * Get penalty
     *
     * @return integer
     */
    public function getPenalty()
    {
        return $this->penalty;
    }

    /**
     * Whether to add a user for this team. Will not be stored, but is used in validation
     *
     * @return bool
     */
    public function getAddUserForTeam(): bool
    {
        return $this->addUserForTeam;
    }

    /**
     * Set affiliation
     *
     * @param \App\Entity\TeamAffiliation $affiliation
     *
     * @return Team
     */
    public function setAffiliation(\App\Entity\TeamAffiliation $affiliation = null)
    {
        $this->affiliation = $affiliation;

        return $this;
    }

    /**
     * Get affiliation
     *
     * @return \App\Entity\TeamAffiliation
     */
    public function getAffiliation()
    {
        return $this->affiliation;
    }

    /**
     * Set category
     *
     * @param \App\Entity\TeamCategory $category
     *
     * @return Team
     */
    public function setCategory(\App\Entity\TeamCategory $category = null)
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Get category
     *
     * @return \App\Entity\TeamCategory
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->contests = new \Doctrine\Common\Collections\ArrayCollection();
        $this->users = new \Doctrine\Common\Collections\ArrayCollection();
        $this->submissions = new ArrayCollection();
        $this->sent_clarifications = new ArrayCollection();
        $this->received_clarifications = new ArrayCollection();
        $this->unread_clarifications = new ArrayCollection();
    }

    /**
     * Add contest
     *
     * @param \App\Entity\Contest $contest
     *
     * @return Team
     */
    public function addContest(\App\Entity\Contest $contest)
    {
        $this->contests[] = $contest;

        $contest->addTeam($this);

        return $this;
    }

    /**
     * Remove contest
     *
     * @param \App\Entity\Contest $contest
     */
    public function removeContest(\App\Entity\Contest $contest)
    {
        $this->contests->removeElement($contest);

        $contest->removeTeam($this);
    }

    /**
     * Get contests
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getContests()
    {
        return $this->contests;
    }

    /**
     * Add user
     *
     * @param \App\Entity\User $user
     *
     * @return Team
     */
    public function addUser(\App\Entity\User $user)
    {
        $this->users[] = $user;

        return $this;
    }

    /**
     * Remove user
     *
     * @param \App\Entity\User $user
     */
    public function removeUser(\App\Entity\User $user)
    {
        $this->users->removeElement($user);
    }

    /**
     * Get users
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getUsers()
    {
        return $this->users;
    }

    /**
     * Add submission
     *
     * @param \App\Entity\Submission $submission
     *
     * @return Team
     */
    public function addSubmission(\App\Entity\Submission $submission)
    {
        $this->submissions[] = $submission;

        return $this;
    }

    /**
     * Remove submission
     *
     * @param \App\Entity\Submission $submission
     */
    public function removeSubmission(\App\Entity\Submission $submission)
    {
        $this->submissions->removeElement($submission);
    }

    /**
     * Get submissions
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getSubmissions()
    {
        return $this->submissions;
    }

    /**
     * Add sentClarification
     *
     * @param \App\Entity\Clarification $sentClarification
     *
     * @return Team
     */
    public function addSentClarification(
        \App\Entity\Clarification $sentClarification)
    {
        $this->sent_clarifications[] = $sentClarification;

        return $this;
    }

    /**
     * Remove sentClarification
     *
     * @param \App\Entity\Clarification $sentClarification
     */
    public function removeSentClarification(
        \App\Entity\Clarification $sentClarification)
    {
        $this->sent_clarifications->removeElement($sentClarification);
    }

    /**
     * Get sentClarifications
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getSentClarifications()
    {
        return $this->sent_clarifications;
    }

    /**
     * Add receivedClarification
     *
     * @param \App\Entity\Clarification $receivedClarification
     *
     * @return Team
     */
    public function addReceivedClarification(
        \App\Entity\Clarification $receivedClarification)
    {
        $this->received_clarifications[] = $receivedClarification;

        return $this;
    }

    /**
     * Remove receivedClarification
     *
     * @param \App\Entity\Clarification $receivedClarification
     */
    public function removeReceivedClarification(
        \App\Entity\Clarification $receivedClarification)
    {
        $this->received_clarifications->removeElement($receivedClarification);
    }

    /**
     * Get receivedClarifications
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getReceivedClarifications()
    {
        return $this->received_clarifications;
    }

    /**
     * Add unreadClarification
     *
     * @param \App\Entity\Clarification $unreadClarification
     *
     * @return Team
     */
    public function addUnreadClarification(
        \App\Entity\Clarification $unreadClarification)
    {
        $this->unread_clarifications[] = $unreadClarification;

        return $this;
    }

    /**
     * Remove unreadClarification
     *
     * @param \App\Entity\Clarification $unreadClarification
     */
    public function removeUnreadClarification(
        \App\Entity\Clarification $unreadClarification)
    {
        $this->unread_clarifications->removeElement($unreadClarification);
    }

    /**
     * Get unreadClarifications
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getUnreadClarifications()
    {
        return $this->unread_clarifications;
    }

    /**
     * Get the group ID's for this team
     * @return string[]
     * @Serializer\VirtualProperty()
     * @Serializer\Type("array<string>")
     */
    public function getGroupIds(): array
    {
        return $this->getCategoryid() ? [$this->getCategoryid()] : [];
    }

    /**
     * Get the affiliation name of this team
     * @return string|null
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("affiliation")
     * @Serializer\Type("string")
     * @Serializer\Groups({"Nonstrict"})
     */
    public function getAffiliationName()
    {
        return $this->getAffiliation() ? $this->getAffiliation()->getName() : null;
    }

    /**
     * Get the nationality of this team
     * @return string|null
     * @Serializer\VirtualProperty()
     * @Serializer\Type("string")
     * @Serializer\Groups({"Nonstrict"})
     * @Serializer\Expose(if="context.getAttribute('config_service').get('show_flags')")
     */
    public function getNationality()
    {
        return $this->getAffiliation() ? $this->getAffiliation()->getCountry() : null;
    }

    /**
     * Return whether this team can view the given clarification
     * @param Clarification $clarification
     * @return bool
     */
    public function canViewClarification(Clarification $clarification)
    {
        return ($clarification->getSenderId() === $this->getTeamid() ||
            $clarification->getRecipientId() === $this->getTeamid() ||
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
     * @param ExecutionContextInterface $context
     * @Assert\Callback()
     */
    public function validate(ExecutionContextInterface $context)
    {
        if ($this->getAddUserForTeam()) {
            if (empty($this->getUsers()->first()->getUsername())) {
                $context
                    ->buildViolation('Required when adding a user')
                    ->atPath('users[0].username')
                    ->addViolation();
            } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $this->getUsers()->first()->getUsername())) {
                $context
                    ->buildViolation('May only contain [a-zA-Z0-9_-].')
                    ->atPath('users[0].username')
                    ->addViolation();
            }
        }
    }

    /**
     * Check if this team belongs to the given contest
     *
     * @param Contest $contest
     *
     * @return bool
     */
    public function inContest(Contest $contest): bool
    {
        return $contest->isOpenToAllTeams() ||
            $this->getContests()->contains($contest) ||
            ($this->getCategory() !== null && $this->getCategory()->inContest($contest));
    }
}
