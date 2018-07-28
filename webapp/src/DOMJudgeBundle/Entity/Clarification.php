<?php
namespace DOMJudgeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Clarification requests by teams and responses by the jury
 * @ORM\Entity()
 * @ORM\Table(name="clarification", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class Clarification
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\Column(type="integer", name="clarid", options={"comment"="Unique ID"}, nullable=false)
     */
    private $clarid;

    /**
     * @var int
     * @ORM\Column(type="integer", name="cid", options={"comment"="Contest ID"}, nullable=false)
     */
    private $cid;

    /**
     * @var int
     * @ORM\Column(type="integer", name="respid", options={"comment"="In reply to clarification ID"}, nullable=true)
     */
    private $respid;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="submittime", options={"comment"="Time sent", "unsigned"=true}, nullable=false)
     */
    private $submittime;

    /**
     * @var int
     * @ORM\Column(type="integer", name="sender", options={"comment"="Team ID, null means jury"}, nullable=true)
     */
    private $sender_id;

    /**
     * @var int
     * @ORM\Column(type="integer", name="recipient", options={"comment"="Team ID, null means to jury or to all"}, nullable=true)
     */
    private $recipient_id;

    /**
     * @var string
     * @ORM\Column(type="string", name="jury_member", length=255, options={"comment"="Name of jury member who answered this"}, nullable=true)
     */
    private $jury_member;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="probid", options={"comment"="Problem associated to this clarification"}, nullable=true)
     */
    private $probid;

    /**
     * @var string
     * @ORM\Column(type="string", name="category", length=255, options={"comment"="Category associated to this clarification; only set for non-problem clars"}, nullable=true)
     */
    private $category;

    /**
     * @var string
     * @ORM\Column(type="text", length=4294967295, name="body", options={"comment"="Team member names (freeform)"}, nullable=true)
     */
    private $body;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="answered", options={"comment"="Has been answered by jury?"}, nullable=false)
     */
    private $answered = false;

    /**
     * @ORM\ManyToOne(targetEntity="Problem", inversedBy="clarifications")
     * @ORM\JoinColumn(name="probid", referencedColumnName="probid")
     */
    private $problem;

    /**
     * @ORM\ManyToOne(targetEntity="Contest", inversedBy="clarifications")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid")
     */
    private $contest;

    /**
     * @ORM\ManyToOne(targetEntity="Clarification", inversedBy="replies")
     * @ORM\JoinColumn(name="respid", referencedColumnName="clarid")
     */
    private $in_reply_to;

    /**
     * @ORM\OneToMany(targetEntity="Clarification", mappedBy="in_reply_to")
     */
    private $replies;

    /**
     * @ORM\ManyToOne(targetEntity="Team", inversedBy="sent_clarifications")
     * @ORM\JoinColumn(name="sender", referencedColumnName="teamid")
     */
    private $sender;

    /**
     * @ORM\ManyToOne(targetEntity="Team", inversedBy="received_clarifications")
     * @ORM\JoinColumn(name="recipient", referencedColumnName="teamid")
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
     * @param string $submittime
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
     * @return string
     */
    public function getSubmittime()
    {
        return $this->submittime;
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
     * @param \DOMJudgeBundle\Entity\Problem $problem
     *
     * @return Clarification
     */
    public function setProblem(\DOMJudgeBundle\Entity\Problem $problem = null)
    {
        $this->problem = $problem;

        return $this;
    }

    /**
     * Get problem
     *
     * @return \DOMJudgeBundle\Entity\Problem
     */
    public function getProblem()
    {
        return $this->problem;
    }

    /**
     * Set contest
     *
     * @param \DOMJudgeBundle\Entity\Contest $contest
     *
     * @return Clarification
     */
    public function setContest(\DOMJudgeBundle\Entity\Contest $contest = null)
    {
        $this->contest = $contest;

        return $this;
    }

    /**
     * Get contest
     *
     * @return \DOMJudgeBundle\Entity\Contest
     */
    public function getContest()
    {
        return $this->contest;
    }

    /**
     * Set inReplyTo
     *
     * @param \DOMJudgeBundle\Entity\Clarification $inReplyTo
     *
     * @return Clarification
     */
    public function setInReplyTo(\DOMJudgeBundle\Entity\Clarification $inReplyTo = null)
    {
        $this->in_reply_to = $inReplyTo;

        return $this;
    }

    /**
     * Get inReplyTo
     *
     * @return \DOMJudgeBundle\Entity\Clarification
     */
    public function getInReplyTo()
    {
        return $this->in_reply_to;
    }

    /**
     * Add reply
     *
     * @param \DOMJudgeBundle\Entity\Clarification $reply
     *
     * @return Clarification
     */
    public function addReply(\DOMJudgeBundle\Entity\Clarification $reply)
    {
        $this->replies[] = $reply;

        return $this;
    }

    /**
     * Remove reply
     *
     * @param \DOMJudgeBundle\Entity\Clarification $reply
     */
    public function removeReply(\DOMJudgeBundle\Entity\Clarification $reply)
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
     * @param \DOMJudgeBundle\Entity\Team $sender
     *
     * @return Clarification
     */
    public function setSender(\DOMJudgeBundle\Entity\Team $sender = null)
    {
        $this->sender = $sender;

        return $this;
    }

    /**
     * Get sender
     *
     * @return \DOMJudgeBundle\Entity\Team
     */
    public function getSender()
    {
        return $this->sender;
    }

    /**
     * Set recipient
     *
     * @param \DOMJudgeBundle\Entity\Team $recipient
     *
     * @return Clarification
     */
    public function setRecipient(\DOMJudgeBundle\Entity\Team $recipient = null)
    {
        $this->recipient = $recipient;

        return $this;
    }

    /**
     * Get recipient
     *
     * @return \DOMJudgeBundle\Entity\Team
     */
    public function getRecipient()
    {
        return $this->recipient;
    }
}
