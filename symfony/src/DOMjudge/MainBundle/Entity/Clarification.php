<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Clarification
 *
 * @ORM\Table(name="clarification", indexes={@ORM\Index(name="respid", columns={"respid"}), @ORM\Index(name="probid", columns={"probid"}), @ORM\Index(name="cid", columns={"cid"}), @ORM\Index(name="cid_2", columns={"cid", "answered", "submittime"})})
 * @ORM\Entity
 */
class Clarification
{
	/**
	 * @var integer
	 *
	 * @ORM\Column(name="clarid", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 */
	private $clarid;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="submittime", type="decimal", precision=32, scale=9, nullable=false)
	 */
	private $submitTime;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="sender", type="integer", nullable=true)
	 */
	private $sender;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="recipient", type="integer", nullable=true)
	 */
	private $recipient;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="jury_member", type="string", length=15, nullable=true)
	 */
	private $juryMember;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="category", type="string", length=128, nullable=true)
	 */
	private $category;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="body", type="text", nullable=false)
	 */
	private $body;

	/**
	 * @var boolean
	 *
	 * @ORM\Column(name="answered", type="boolean", nullable=false)
	 */
	private $answered;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 *
	 * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Clarification", mappedBy="inReplyTo")
	 */
	private $responses;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 *
	 * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Event", mappedBy="clarification")
	 */
	private $events;

	/**
	 * @var \DOMjudge\MainBundle\Entity\Contest
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Contest", inversedBy="clarifications")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="cid", referencedColumnName="cid")
	 * })
	 */
	private $contest;

	/**
	 * @var \DOMjudge\MainBundle\Entity\Clarification
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Clarification", inversedBy="responses")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="respid", referencedColumnName="clarid")
	 * })
	 */
	private $inReplyTo;

	/**
	 * @var \DOMjudge\MainBundle\Entity\Problem
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Problem", inversedBy="clarifications")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="probid", referencedColumnName="probid")
	 * })
	 */
	private $problem;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 *
	 * @ORM\ManyToMany(targetEntity="DOMjudge\MainBundle\Entity\Team", mappedBy="unreadClarifications")
	 */
	private $unreadTeams;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->responses = new \Doctrine\Common\Collections\ArrayCollection();
		$this->events = new \Doctrine\Common\Collections\ArrayCollection();
		$this->unreadTeams = new \Doctrine\Common\Collections\ArrayCollection();
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
	 * Set submitTime
	 *
	 * @param string $submitTime
	 * @return Clarification
	 */
	public function setSubmitTime($submitTime)
	{
		$this->submitTime = $submitTime;

		return $this;
	}

	/**
	 * Get submitTime
	 *
	 * @return string
	 */
	public function getSubmitTime()
	{
		return $this->submitTime;
	}

	/**
	 * Set sender
	 *
	 * @param integer $sender
	 * @return Clarification
	 */
	public function setSender($sender)
	{
		$this->sender = $sender;

		return $this;
	}

	/**
	 * Get sender
	 *
	 * @return integer
	 */
	public function getSender()
	{
		return $this->sender;
	}

	/**
	 * Set recipient
	 *
	 * @param integer $recipient
	 * @return Clarification
	 */
	public function setRecipient($recipient)
	{
		$this->recipient = $recipient;

		return $this;
	}

	/**
	 * Get recipient
	 *
	 * @return integer
	 */
	public function getRecipient()
	{
		return $this->recipient;
	}

	/**
	 * Set juryMember
	 *
	 * @param string $juryMember
	 * @return Clarification
	 */
	public function setJuryMember($juryMember)
	{
		$this->juryMember = $juryMember;

		return $this;
	}

	/**
	 * Get juryMember
	 *
	 * @return string
	 */
	public function getJuryMember()
	{
		return $this->juryMember;
	}

	/**
	 * Set category
	 *
	 * @param string $category
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
	 * Add responses
	 *
	 * @param \DOMjudge\MainBundle\Entity\Clarification $responses
	 * @return Clarification
	 */
	public function addResponse(\DOMjudge\MainBundle\Entity\Clarification $responses)
	{
		$this->responses[] = $responses;

		return $this;
	}

	/**
	 * Remove responses
	 *
	 * @param \DOMjudge\MainBundle\Entity\Clarification $responses
	 */
	public function removeResponse(\DOMjudge\MainBundle\Entity\Clarification $responses)
	{
		$this->responses->removeElement($responses);
	}

	/**
	 * Get responses
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getResponses()
	{
		return $this->responses;
	}

	/**
	 * Add events
	 *
	 * @param \DOMjudge\MainBundle\Entity\Event $events
	 * @return Clarification
	 */
	public function addEvent(\DOMjudge\MainBundle\Entity\Event $events)
	{
		$this->events[] = $events;

		return $this;
	}

	/**
	 * Remove events
	 *
	 * @param \DOMjudge\MainBundle\Entity\Event $events
	 */
	public function removeEvent(\DOMjudge\MainBundle\Entity\Event $events)
	{
		$this->events->removeElement($events);
	}

	/**
	 * Get events
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getEvents()
	{
		return $this->events;
	}

	/**
	 * Set contest
	 *
	 * @param \DOMjudge\MainBundle\Entity\Contest $contest
	 * @return Clarification
	 */
	public function setContest(\DOMjudge\MainBundle\Entity\Contest $contest = null)
	{
		$this->contest = $contest;

		return $this;
	}

	/**
	 * Get contest
	 *
	 * @return \DOMjudge\MainBundle\Entity\Contest
	 */
	public function getContest()
	{
		return $this->contest;
	}

	/**
	 * Set inReplyTo
	 *
	 * @param \DOMjudge\MainBundle\Entity\Clarification $inReplyTo
	 * @return Clarification
	 */
	public function setInReplyTo(\DOMjudge\MainBundle\Entity\Clarification $inReplyTo = null)
	{
		$this->inReplyTo = $inReplyTo;

		return $this;
	}

	/**
	 * Get inReplyTo
	 *
	 * @return \DOMjudge\MainBundle\Entity\Clarification
	 */
	public function getInReplyTo()
	{
		return $this->inReplyTo;
	}

	/**
	 * Set problem
	 *
	 * @param \DOMjudge\MainBundle\Entity\Problem $problem
	 * @return Clarification
	 */
	public function setProblem(\DOMjudge\MainBundle\Entity\Problem $problem = null)
	{
		$this->problem = $problem;

		return $this;
	}

	/**
	 * Get problem
	 *
	 * @return \DOMjudge\MainBundle\Entity\Problem
	 */
	public function getProblem()
	{
		return $this->problem;
	}

	/**
	 * Add unreadTeams
	 *
	 * @param \DOMjudge\MainBundle\Entity\Team $unreadTeams
	 * @return Clarification
	 */
	public function addUnreadTeam(\DOMjudge\MainBundle\Entity\Team $unreadTeams)
	{
		$this->unreadTeams[] = $unreadTeams;

		return $this;
	}

	/**
	 * Remove unreadTeams
	 *
	 * @param \DOMjudge\MainBundle\Entity\Team $unreadTeams
	 */
	public function removeUnreadTeam(\DOMjudge\MainBundle\Entity\Team $unreadTeams)
	{
		$this->unreadTeams->removeElement($unreadTeams);
	}

	/**
	 * Get unreadTeams
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getUnreadTeams()
	{
		return $this->unreadTeams;
	}
}
