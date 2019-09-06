<?php declare(strict_types=1);
namespace App\Entity;

use App\Utils\Utils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Clarification requests by teams and responses by the jury
 * @ORM\Entity()
 * @ORM\Table(
 *     name="clarification",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Clarification requests by teams and responses by the jury"},
 *     indexes={
 *         @ORM\Index(name="respid", columns={"respid"}),
 *         @ORM\Index(name="probid", columns={"probid"}),
 *         @ORM\Index(name="cid", columns={"cid"}),
 *         @ORM\Index(name="cid_2", columns={"cid","answered","submittime"}),
 *         @ORM\Index(name="sender", columns={"sender"}),
 *         @ORM\Index(name="recipient", columns={"recipient"})
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="externalid", columns={"cid", "externalid"}, options={"lengths": {null, "190"}})
 *     })
 * @UniqueEntity("externalid")
 */
class Clarification extends BaseApiEntity implements ExternalRelationshipEntityInterface
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", length=4, name="clarid",
     *     options={"comment"="Clarification ID","unsigned"=true},
     *     nullable=false)
     * @Serializer\SerializedName("id")
     * @Serializer\Type("string")
     */
    protected $clarid;

    /**
     * @var string
     * @ORM\Column(type="string", name="externalid", length=255,
     *     options={"comment"="Clarification ID in an external system, should be unique inside a single contest",
     *              "collation"="utf8mb4_bin","default"="NULL"},
     *     nullable=true)
     */
    protected $externalid;

    /**
     * @var int
     * @ORM\Column(type="integer", name="cid",
     *     options={"comment"="Contest ID","unsigned"=true},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $cid;

    /**
     * @var int
     * @ORM\Column(type="integer", name="respid",
     *     options={"comment"="In reply to clarification ID","unsigned"=true,"default"="NULL"},
     *     nullable=true)
     * @Serializer\SerializedName("reply_to_id")
     * @Serializer\Type("string")
     */
    private $respid;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="submittime", options={"comment"="Time sent", "unsigned"=true}, nullable=false)
     * @Serializer\Exclude()
     */
    private $submittime;

    /**
     * @var int
     * @ORM\Column(type="integer", name="sender",
     *     options={"comment"="Team ID, null means jury","unsigned"=true,"default"="NULL"},
     *     nullable=true)
     * @Serializer\SerializedName("from_team_id")
     * @Serializer\Type("string")
     */
    private $sender_id;

    /**
     * @var int
     * @ORM\Column(type="integer", name="recipient",
     *     options={"comment"="Team ID, null means to jury or to all","unsigned"=true,"default"="NULL"},
     *     nullable=true)
     * @Serializer\SerializedName("to_team_id")
     * @Serializer\Type("string")
     */
    private $recipient_id;

    /**
     * @var string
     * @ORM\Column(type="string", name="jury_member", length=255,
     *     options={"comment"="Name of jury member who answered this","default"="NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $jury_member;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="probid",
     *     options={"comment"="Problem associated to this clarification","unsigned"=true,"default"="NULL"},
     *     nullable=true)
     * @Serializer\SerializedName("problem_id")
     * @Serializer\Type("string")
     */
    private $probid;

    /**
     * @var string
     * @ORM\Column(type="string", name="category", length=255,
     *     options={"comment"="Category associated to this clarification; only set for non problem clars",
     *              "default"="NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $category;

    /**
     * @var string
     * @ORM\Column(type="string", name="queue", length=255,
     *     options={"comment"="Queue associated to this clarification","default"="NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $queue;

    /**
     * @var string
     * @ORM\Column(type="text", length=4294967295, name="body",
     *     options={"comment"="Clarification text"},
     *     nullable=false)
     * @Serializer\SerializedName("text")
     */
    private $body;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="answered",
     *     options={"comment"="Has been answered by jury?","default":"0"},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $answered = false;

    /**
     * @ORM\ManyToOne(targetEntity="Problem", inversedBy="clarifications")
     * @ORM\JoinColumn(name="probid", referencedColumnName="probid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private $problem;

    /**
     * @ORM\ManyToOne(targetEntity="Contest", inversedBy="clarifications")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private $contest;

    /**
     * @ORM\ManyToOne(targetEntity="Clarification", inversedBy="replies")
     * @ORM\JoinColumn(name="respid", referencedColumnName="clarid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private $in_reply_to;

    /**
     * @ORM\OneToMany(targetEntity="Clarification", mappedBy="in_reply_to")
     * @Serializer\Exclude()
     */
    private $replies;

    /**
     * @ORM\ManyToOne(targetEntity="Team", inversedBy="sent_clarifications")
     * @ORM\JoinColumn(name="sender", referencedColumnName="teamid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private $sender;

    /**
     * @ORM\ManyToOne(targetEntity="Team", inversedBy="received_clarifications")
     * @ORM\JoinColumn(name="recipient", referencedColumnName="teamid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private $recipient;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->replies = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Set clarid
     *
     * @param integer $clarid
     *
     * @return Clarification
     */
    public function setClarid($clarid)
    {
        $this->clarid = $clarid;

        return $this;
    }

    /**
     * Get clarid
     *
     * @return integer
     */
    public function getClarid()
    {
        return $this->clarid;
    }

    /**
     * Set externalid
     *
     * @param string $externalid
     *
     * @return Clarification
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
     * Set cid
     *
     * @param integer $cid
     *
     * @return Clarification
     */
    public function setCid($cid)
    {
        $this->cid = $cid;

        return $this;
    }

    /**
     * Get cid
     *
     * @return integer
     */
    public function getCid()
    {
        return $this->cid;
    }

    /**
     * Set respid
     *
     * @param integer $respid
     *
     * @return Clarification
     */
    public function setRespid($respid)
    {
        $this->respid = $respid;

        return $this;
    }

    /**
     * Get respid
     *
     * @return integer
     */
    public function getRespid()
    {
        return $this->respid;
    }

    /**
     * Set submittime
     *
     * @param double $submittime
     *
     * @return Clarification
     */
    public function setSubmittime($submittime)
    {
        $this->submittime = $submittime;

        return $this;
    }

    /**
     * Get submittime
     *
     * @return double
     */
    public function getSubmittime()
    {
        return $this->submittime;
    }

    /**
     * Get the absolute submit time for this clarification
     *
     * @return string
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("time")
     * @Serializer\Type("string")
     */
    public function getAbsoluteSubmitTime()
    {
        return Utils::absTime($this->getSubmittime());
    }

    /**
     * Get the relative submit time for this clarification
     *
     * @return string
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("contest_time")
     * @Serializer\Type("string")
     */
    public function getRelativeSubmitTime()
    {
        return Utils::relTime($this->getSubmittime() - $this->getContest()->getStarttime());
    }

    /**
     * Set senderId
     *
     * @param integer $senderId
     *
     * @return Clarification
     */
    public function setSenderId($senderId)
    {
        $this->sender_id = $senderId;

        return $this;
    }

    /**
     * Get senderId
     *
     * @return integer
     */
    public function getSenderId()
    {
        return $this->sender_id;
    }

    /**
     * Set recipientId
     *
     * @param integer $recipientId
     *
     * @return Clarification
     */
    public function setRecipientId($recipientId)
    {
        $this->recipient_id = $recipientId;

        return $this;
    }

    /**
     * Get recipientId
     *
     * @return integer
     */
    public function getRecipientId()
    {
        return $this->recipient_id;
    }

    /**
     * Set juryMember
     *
     * @param string $juryMember
     *
     * @return Clarification
     */
    public function setJuryMember($juryMember)
    {
        $this->jury_member = $juryMember;

        return $this;
    }

    /**
     * Get juryMember
     *
     * @return string
     */
    public function getJuryMember()
    {
        return $this->jury_member;
    }

    /**
     * Set probid
     *
     * @param integer $probid
     *
     * @return Clarification
     */
    public function setProbid($probid)
    {
        $this->probid = $probid;

        return $this;
    }

    /**
     * Get probid
     *
     * @return integer
     */
    public function getProbid()
    {
        return $this->probid;
    }

    /**
     * Set category
     *
     * @param string $category
     *
     * @return Clarification
     */
    public function setCategory($category)
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Get category
     *
     * @return string
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Set queue
     *
     * @param string $queue
     *
     * @return Clarification
     */
    public function setQueue($queue)
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * Get queue
     *
     * @return string
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * Set body
     *
     * @param string $body
     *
     * @return Clarification
     */
    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Get body
     *
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Set answered
     *
     * @param boolean $answered
     *
     * @return Clarification
     */
    public function setAnswered($answered)
    {
        $this->answered = $answered;

        return $this;
    }

    /**
     * Get answered
     *
     * @return boolean
     */
    public function getAnswered()
    {
        return $this->answered;
    }

    /**
     * Set problem
     *
     * @param \App\Entity\Problem $problem
     *
     * @return Clarification
     */
    public function setProblem(\App\Entity\Problem $problem = null)
    {
        $this->problem = $problem;

        return $this;
    }

    /**
     * Get problem
     *
     * @return \App\Entity\Problem
     */
    public function getProblem()
    {
        return $this->problem;
    }

    /**
     * Set contest
     *
     * @param \App\Entity\Contest $contest
     *
     * @return Clarification
     */
    public function setContest(\App\Entity\Contest $contest = null)
    {
        $this->contest = $contest;

        return $this;
    }

    /**
     * Get contest
     *
     * @return \App\Entity\Contest
     */
    public function getContest()
    {
        return $this->contest;
    }

    /**
     * Set inReplyTo
     *
     * @param \App\Entity\Clarification $inReplyTo
     *
     * @return Clarification
     */
    public function setInReplyTo(\App\Entity\Clarification $inReplyTo = null)
    {
        $this->in_reply_to = $inReplyTo;

        return $this;
    }

    /**
     * Get inReplyTo
     *
     * @return \App\Entity\Clarification
     */
    public function getInReplyTo()
    {
        return $this->in_reply_to;
    }

    /**
     * Add reply
     *
     * @param \App\Entity\Clarification $reply
     *
     * @return Clarification
     */
    public function addReply(\App\Entity\Clarification $reply)
    {
        $this->replies[] = $reply;

        return $this;
    }

    /**
     * Remove reply
     *
     * @param \App\Entity\Clarification $reply
     */
    public function removeReply(\App\Entity\Clarification $reply)
    {
        $this->replies->removeElement($reply);
    }

    /**
     * Get replies
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getReplies()
    {
        return $this->replies;
    }

    /**
     * Set sender
     *
     * @param \App\Entity\Team $sender
     *
     * @return Clarification
     */
    public function setSender(\App\Entity\Team $sender = null)
    {
        $this->sender = $sender;

        return $this;
    }

    /**
     * Get sender
     *
     * @return \App\Entity\Team
     */
    public function getSender()
    {
        return $this->sender;
    }

    /**
     * Set recipient
     *
     * @param \App\Entity\Team $recipient
     *
     * @return Clarification
     */
    public function setRecipient(\App\Entity\Team $recipient = null)
    {
        $this->recipient = $recipient;

        return $this;
    }

    /**
     * Get recipient
     *
     * @return \App\Entity\Team
     */
    public function getRecipient()
    {
        return $this->recipient;
    }

    /**
     * Get the entities to check for external ID's while serializing.
     *
     * This method should return an array with as keys the JSON field names and as values the actual entity
     * objects that the SetExternalIdVisitor should check for applicable external ID's
     * @return array
     */
    public function getExternalRelationships(): array
    {
        return [
            'from_team_id' => $this->getSender(),
            'to_team_id' => $this->getRecipient(),
            'problem_id' => $this->getProblem(),
            'reply_to_id' => $this->getInReplyTo()
        ];
    }

    /**
     * Get the summary for this clarification
     * @return string
     */
    public function getSummary(): string
    {
        // when making a summary, try to ignore the quoted text, and replace newlines by spaces.
        $split = explode("\n", $this->getBody());
        $newBody = '';
        foreach ($split as $line) {
            if (strlen($line) > 0 && $line{0} != '>') {
                $newBody .= $line . ' ';
            }
        }
        return Utils::cutString((empty($newBody) ? $this->getBody() : $newBody), 80);
    }
}
