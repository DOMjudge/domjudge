<?php declare(strict_types=1);

namespace DOMJudgeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * All teams participating in the contest
 * @ORM\Entity()
 * @ORM\Table(name="team", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 * @Serializer\VirtualProperty(
 *     "externalid_nonstrict",
 *     exp="object.getExternalId()",
 *     options={@Serializer\SerializedName("externalid"), @Serializer\Type("string"), @Serializer\Groups({"Nonstrict"})}
 * )
 * @UniqueEntity("externalid")
 */
class Team extends BaseApiEntity implements ExternalRelationshipEntityInterface
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="teamid", options={"comment"="Unique ID"}, nullable=false)
     * @Serializer\SerializedName("id")
     * @Serializer\Type("string")
     */
    protected $teamid;

    /**
     * @var string
     * TODO: ORM\Unique on first 190 characters
     * @ORM\Column(type="string", name="externalid", length=255, options={"comment"="Team ID in an external system",
     *                            "collation"="utf8mb4_bin"}, nullable=true)
     * @Serializer\SerializedName("icpc_id")
     */
    protected $externalid;

    /**
     * @var string
     * @ORM\Column(type="string", name="name", length=255, options={"comment"="Team Name", "collation"="utf8mb4_bin"},
     *                            nullable=false)
     */
    private $name;

    /**
     * @var int
     * @ORM\Column(type="integer", name="categoryid", options={"comment"="Team category ID"}, nullable=false)
     * @Serializer\Exclude()
     */
    private $categoryid = 0;

    /**
     * @var int
     * @ORM\Column(type="integer", name="affilid", options={"comment"="Team affiliation ID"}, nullable=true)
     * @Serializer\SerializedName("organization_id")
     * @Serializer\Type("string")
     */
    private $affilid;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="enabled", options={"comment"="Whether the team is visible and operational"},
     *                             nullable=true)
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
     * @ORM\Column(type="decimal", precision=32, scale=9, name="judging_last_started", options={"comment"="Start time
     *                             of last judging for priorization", "unsigned"=true}, nullable=true)
     * @Serializer\Exclude()
     */
    private $judging_last_started;

    /**
     * @var int
     * @ORM\Column(type="integer", name="penalty", options={"comment"="Additional penalty time in minutes"},
     *                             nullable=false)
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
     *                joinColumns={@ORM\JoinColumn(name="teamid", referencedColumnName="teamid")},
     *                inverseJoinColumns={@ORM\JoinColumn(name="mesgid", referencedColumnName="clarid")}
     * )
     * @Serializer\Exclude()
     */
    private $unread_clarifications;

    /**
     * @ORM\OneToMany(targetEntity="ScoreCache", mappedBy="team")
     * @Serializer\Exclude()
     */
    private $scorecache;

    /**
     * @ORM\OneToMany(targetEntity="RankCache", mappedBy="team")
     * @Serializer\Exclude()
     */
    private $rankcache;

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
     * Set externalid
     *
     * @param string $externalid
     *
     * @return Team
     */
    public function setExternalid($externalid)
    {
        $this->externalid = $externalid;

        return $this;
    }

    /**
     * Get externalid
     *
     * @return string
     */
    public function getExternalid()
    {
        return $this->externalid;
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
     * @param \DOMJudgeBundle\Entity\TeamAffiliation $affiliation
     *
     * @return Team
     */
    public function setAffiliation(\DOMJudgeBundle\Entity\TeamAffiliation $affiliation = null)
    {
        $this->affiliation = $affiliation;

        return $this;
    }

    /**
     * Get affiliation
     *
     * @return \DOMJudgeBundle\Entity\TeamAffiliation
     */
    public function getAffiliation()
    {
        return $this->affiliation;
    }

    /**
     * Set category
     *
     * @param \DOMJudgeBundle\Entity\TeamCategory $category
     *
     * @return Team
     */
    public function setCategory(\DOMJudgeBundle\Entity\TeamCategory $category = null)
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Get category
     *
     * @return \DOMJudgeBundle\Entity\TeamCategory
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
    }

    /**
     * Add contest
     *
     * @param \DOMJudgeBundle\Entity\Contest $contest
     *
     * @return Team
     */
    public function addContest(\DOMJudgeBundle\Entity\Contest $contest)
    {
        $this->contests[] = $contest;

        $contest->addTeam($this);

        return $this;
    }

    /**
     * Remove contest
     *
     * @param \DOMJudgeBundle\Entity\Contest $contest
     */
    public function removeContest(\DOMJudgeBundle\Entity\Contest $contest)
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
     * @param \DOMJudgeBundle\Entity\User $user
     *
     * @return Team
     */
    public function addUser(\DOMJudgeBundle\Entity\User $user)
    {
        $this->users[] = $user;

        return $this;
    }

    /**
     * Remove user
     *
     * @param \DOMJudgeBundle\Entity\User $user
     */
    public function removeUser(\DOMJudgeBundle\Entity\User $user)
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
     * @param \DOMJudgeBundle\Entity\Submission $submission
     *
     * @return Team
     */
    public function addSubmission(\DOMJudgeBundle\Entity\Submission $submission)
    {
        $this->submissions[] = $submission;

        return $this;
    }

    /**
     * Remove submission
     *
     * @param \DOMJudgeBundle\Entity\Submission $submission
     */
    public function removeSubmission(\DOMJudgeBundle\Entity\Submission $submission)
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
     * @param \DOMJudgeBundle\Entity\Clarification $sentClarification
     *
     * @return Team
     */
    public function addSentClarification(\DOMJudgeBundle\Entity\Clarification $sentClarification)
    {
        $this->sent_clarifications[] = $sentClarification;

        return $this;
    }

    /**
     * Remove sentClarification
     *
     * @param \DOMJudgeBundle\Entity\Clarification $sentClarification
     */
    public function removeSentClarification(\DOMJudgeBundle\Entity\Clarification $sentClarification)
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
     * @param \DOMJudgeBundle\Entity\Clarification $receivedClarification
     *
     * @return Team
     */
    public function addReceivedClarification(\DOMJudgeBundle\Entity\Clarification $receivedClarification)
    {
        $this->received_clarifications[] = $receivedClarification;

        return $this;
    }

    /**
     * Remove receivedClarification
     *
     * @param \DOMJudgeBundle\Entity\Clarification $receivedClarification
     */
    public function removeReceivedClarification(\DOMJudgeBundle\Entity\Clarification $receivedClarification)
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
     * @param \DOMJudgeBundle\Entity\Clarification $unreadClarification
     *
     * @return Team
     */
    public function addUnreadClarification(\DOMJudgeBundle\Entity\Clarification $unreadClarification)
    {
        $this->unread_clarifications[] = $unreadClarification;

        return $this;
    }

    /**
     * Remove unreadClarification
     *
     * @param \DOMJudgeBundle\Entity\Clarification $unreadClarification
     */
    public function removeUnreadClarification(\DOMJudgeBundle\Entity\Clarification $unreadClarification)
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
     * Add scorecache
     *
     * @param \DOMJudgeBundle\Entity\ScoreCache $scorecache
     *
     * @return Team
     */
    public function addScorecache(\DOMJudgeBundle\Entity\ScoreCache $scorecache)
    {
        $this->scorecache[] = $scorecache;

        return $this;
    }

    /**
     * Remove scorecache
     *
     * @param \DOMJudgeBundle\Entity\ScoreCache $scorecache
     */
    public function removeScorecache(\DOMJudgeBundle\Entity\ScoreCache $scorecache)
    {
        $this->scorecache->removeElement($scorecache);
    }

    /**
     * Get scorecache
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getScorecache()
    {
        return $this->scorecache;
    }

    /**
     * Add rankcache
     *
     * @param \DOMJudgeBundle\Entity\RankCache $rankcache
     *
     * @return Team
     */
    public function addRankcache(\DOMJudgeBundle\Entity\RankCache $rankcache)
    {
        $this->rankcache[] = $rankcache;

        return $this;
    }

    /**
     * Remove rankcache
     *
     * @param \DOMJudgeBundle\Entity\RankCache $rankcache
     */
    public function removeRankcache(\DOMJudgeBundle\Entity\RankCache $rankcache)
    {
        $this->rankcache->removeElement($rankcache);
    }

    /**
     * Get rankcache
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getRankcache()
    {
        return $this->rankcache;
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
     * @Serializer\Expose(if="context.getAttribute('domjudge_service').dbconfig_get('show_flags', true)")
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
}
