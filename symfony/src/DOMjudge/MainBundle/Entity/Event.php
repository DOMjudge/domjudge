<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Event
 *
 * @ORM\Table(name="event", indexes={@ORM\Index(name="cid", columns={"cid"}), @ORM\Index(name="clarid", columns={"clarid"}), @ORM\Index(name="langid", columns={"langid"}), @ORM\Index(name="probid", columns={"probid"}), @ORM\Index(name="submitid", columns={"submitid"}), @ORM\Index(name="judgingid", columns={"judgingid"}), @ORM\Index(name="teamid", columns={"teamid"})})
 * @ORM\Entity
 */
class Event
{
	/**
	 * @var integer
	 *
	 * @ORM\Column(name="eventid", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 */
	private $eventid;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="eventtime", type="decimal", precision=32, scale=9, nullable=false)
	 */
	private $eventTime;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="description", type="text", nullable=false)
	 */
	private $description;

	/**
	 * @var \DOMjudge\MainBundle\Entity\Contest
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Contest")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="cid", referencedColumnName="cid")
	 * })
	 */
	private $contest;

	/**
	 * @var \DOMjudge\MainBundle\Entity\Clarification
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Clarification")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="clarid", referencedColumnName="clarid")
	 * })
	 */
	private $clarification;

	/**
	 * @var \DOMjudge\MainBundle\Entity\Language
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Language")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="langid", referencedColumnName="langid")
	 * })
	 */
	private $language;

	/**
	 * @var \DOMjudge\MainBundle\Entity\Problem
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Problem")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="probid", referencedColumnName="probid")
	 * })
	 */
	private $problem;

	/**
	 * @var \DOMjudge\MainBundle\Entity\Submission
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Submission")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="submitid", referencedColumnName="submitid")
	 * })
	 */
	private $submission;

	/**
	 * @var \DOMjudge\MainBundle\Entity\Judging
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Judging")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="judgingid", referencedColumnName="judgingid")
	 * })
	 */
	private $judging;

	/**
	 * @var \DOMjudge\MainBundle\Entity\Team
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Team")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="teamid", referencedColumnName="teamid")
	 * })
	 */
	private $team;



	/**
	 * Get eventid
	 *
	 * @return integer
	 */
	public function getEventid()
	{
		return $this->eventid;
	}

	/**
	 * Set eventTime
	 *
	 * @param string $eventTime
	 * @return Event
	 */
	public function setEventTime($eventTime)
	{
		$this->eventTime = $eventTime;

		return $this;
	}

	/**
	 * Get eventTime
	 *
	 * @return string
	 */
	public function getEventTime()
	{
		return $this->eventTime;
	}

	/**
	 * Set description
	 *
	 * @param string $description
	 * @return Event
	 */
	public function setDescription($description)
	{
		$this->description = $description;

		return $this;
	}

	/**
	 * Get description
	 *
	 * @return string
	 */
	public function getDescription()
	{
		return $this->description;
	}

	/**
	 * Set contest
	 *
	 * @param \DOMjudge\MainBundle\Entity\Contest $contest
	 * @return Event
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
	 * Set clarification
	 *
	 * @param \DOMjudge\MainBundle\Entity\Clarification $clarification
	 * @return Event
	 */
	public function setClarification(\DOMjudge\MainBundle\Entity\Clarification $clarification = null)
	{
		$this->clarification = $clarification;

		return $this;
	}

	/**
	 * Get clarification
	 *
	 * @return \DOMjudge\MainBundle\Entity\Clarification
	 */
	public function getClarification()
	{
		return $this->clarification;
	}

	/**
	 * Set language
	 *
	 * @param \DOMjudge\MainBundle\Entity\Language $language
	 * @return Event
	 */
	public function setLanguage(\DOMjudge\MainBundle\Entity\Language $language = null)
	{
		$this->language = $language;

		return $this;
	}

	/**
	 * Get language
	 *
	 * @return \DOMjudge\MainBundle\Entity\Language
	 */
	public function getLanguage()
	{
		return $this->language;
	}

	/**
	 * Set problem
	 *
	 * @param \DOMjudge\MainBundle\Entity\Problem $problem
	 * @return Event
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
	 * Set submission
	 *
	 * @param \DOMjudge\MainBundle\Entity\Submission $submission
	 * @return Event
	 */
	public function setSubmission(\DOMjudge\MainBundle\Entity\Submission $submission = null)
	{
		$this->submission = $submission;

		return $this;
	}

	/**
	 * Get submission
	 *
	 * @return \DOMjudge\MainBundle\Entity\Submission
	 */
	public function getSubmission()
	{
		return $this->submission;
	}

	/**
	 * Set judging
	 *
	 * @param \DOMjudge\MainBundle\Entity\Judging $judging
	 * @return Event
	 */
	public function setJudging(\DOMjudge\MainBundle\Entity\Judging $judging = null)
	{
		$this->judging = $judging;

		return $this;
	}

	/**
	 * Get judging
	 *
	 * @return \DOMjudge\MainBundle\Entity\Judging
	 */
	public function getJudging()
	{
		return $this->judging;
	}

	/**
	 * Set team
	 *
	 * @param \DOMjudge\MainBundle\Entity\Team $team
	 * @return Event
	 */
	public function setTeam(\DOMjudge\MainBundle\Entity\Team $team = null)
	{
		$this->team = $team;

		return $this;
	}

	/**
	 * Get team
	 *
	 * @return \DOMjudge\MainBundle\Entity\Team
	 */
	public function getTeam()
	{
		return $this->team;
	}
}
