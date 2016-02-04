<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Judging
 *
 * @ORM\Table(name="judging", indexes={@ORM\Index(name="submitid", columns={"submitid"}), @ORM\Index(name="judgehost", columns={"judgehost"}), @ORM\Index(name="cid", columns={"cid"}), @ORM\Index(name="rejudgingid", columns={"rejudgingid"}), @ORM\Index(name="prevjudgingid", columns={"prevjudgingid"})})
 * @ORM\Entity
 */
class Judging
{
	/**
	 * @var integer
	 *
	 * @ORM\Column(name="judgingid", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 */
	private $judgingid;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="starttime", type="decimal", precision=32, scale=9, nullable=false)
	 */
	private $startTime;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="endtime", type="decimal", precision=32, scale=9, nullable=true)
	 */
	private $endTime;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="result", type="string", length=25, nullable=true)
	 */
	private $result;

	/**
	 * @var boolean
	 *
	 * @ORM\Column(name="verified", type="boolean", nullable=false)
	 */
	private $verified;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="jury_member", type="string", length=25, nullable=true)
	 */
	private $juryMember;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="verify_comment", type="string", length=255, nullable=true)
	 */
	private $verifyComment;

	/**
	 * @var boolean
	 *
	 * @ORM\Column(name="valid", type="boolean", nullable=false)
	 */
	private $valid;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="output_compile", type="blob", nullable=true)
	 */
	private $outputCompile;

	/**
	 * @var boolean
	 *
	 * @ORM\Column(name="seen", type="boolean", nullable=false)
	 */
	private $seen;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 *
	 * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Event", mappedBy="judging")
	 */
	private $events;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 *
	 * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\judging", mappedBy="previousJudging")
	 */
	private $nextJudgings;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 *
	 * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\JudgingRun", mappedBy="judging")
	 */
	private $judgingRuns;

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
	 * @var \DOMjudge\MainBundle\Entity\Submission
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Submission")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="submitid", referencedColumnName="submitid")
	 * })
	 */
	private $submission;

	/**
	 * @var \DOMjudge\MainBundle\Entity\Judgehost
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Judgehost")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="judgehost", referencedColumnName="hostname")
	 * })
	 */
	private $judgehost;

	/**
	 * @var \DOMjudge\MainBundle\Entity\Rejudging
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Rejudging", inversedBy="judgings")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="rejudgingid", referencedColumnName="rejudgingid")
	 * })
	 */
	private $rejudging;

	/**
	 * @var \DOMjudge\MainBundle\Entity\Judging
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Judging")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="prevjudgingid", referencedColumnName="judgingid")
	 * })
	 */
	private $previousJudging;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->events = new \Doctrine\Common\Collections\ArrayCollection();
		$this->nextJudgings = new \Doctrine\Common\Collections\ArrayCollection();
		$this->judgingRuns = new \Doctrine\Common\Collections\ArrayCollection();
	}


	/**
	 * Get judgingid
	 *
	 * @return integer
	 */
	public function getJudgingid()
	{
		return $this->judgingid;
	}

	/**
	 * Set startTime
	 *
	 * @param string $startTime
	 * @return Judging
	 */
	public function setStartTime($startTime)
	{
		$this->startTime = $startTime;

		return $this;
	}

	/**
	 * Get startTime
	 *
	 * @return string
	 */
	public function getStartTime()
	{
		return $this->startTime;
	}

	/**
	 * Set endTime
	 *
	 * @param string $endTime
	 * @return Judging
	 */
	public function setEndTime($endTime)
	{
		$this->endTime = $endTime;

		return $this;
	}

	/**
	 * Get endTime
	 *
	 * @return string
	 */
	public function getEndTime()
	{
		return $this->endTime;
	}

	/**
	 * Set result
	 *
	 * @param string $result
	 * @return Judging
	 */
	public function setResult($result)
	{
		$this->result = $result;

		return $this;
	}

	/**
	 * Get result
	 *
	 * @return string
	 */
	public function getResult()
	{
		return $this->result;
	}

	/**
	 * Set verified
	 *
	 * @param boolean $verified
	 * @return Judging
	 */
	public function setVerified($verified)
	{
		$this->verified = $verified;

		return $this;
	}

	/**
	 * Get verified
	 *
	 * @return boolean
	 */
	public function getVerified()
	{
		return $this->verified;
	}

	/**
	 * Set juryMember
	 *
	 * @param string $juryMember
	 * @return Judging
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
	 * Set verifyComment
	 *
	 * @param string $verifyComment
	 * @return Judging
	 */
	public function setVerifyComment($verifyComment)
	{
		$this->verifyComment = $verifyComment;

		return $this;
	}

	/**
	 * Get verifyComment
	 *
	 * @return string
	 */
	public function getVerifyComment()
	{
		return $this->verifyComment;
	}

	/**
	 * Set valid
	 *
	 * @param boolean $valid
	 * @return Judging
	 */
	public function setValid($valid)
	{
		$this->valid = $valid;

		return $this;
	}

	/**
	 * Get valid
	 *
	 * @return boolean
	 */
	public function getValid()
	{
		return $this->valid;
	}

	/**
	 * Set outputCompile
	 *
	 * @param string $outputCompile
	 * @return Judging
	 */
	public function setOutputCompile($outputCompile)
	{
		$this->outputCompile = $outputCompile;

		return $this;
	}

	/**
	 * Get outputCompile
	 *
	 * @return string
	 */
	public function getOutputCompile()
	{
		return $this->outputCompile;
	}

	/**
	 * Set seen
	 *
	 * @param boolean $seen
	 * @return Judging
	 */
	public function setSeen($seen)
	{
		$this->seen = $seen;

		return $this;
	}

	/**
	 * Get seen
	 *
	 * @return boolean
	 */
	public function getSeen()
	{
		return $this->seen;
	}

	/**
	 * Add events
	 *
	 * @param \DOMjudge\MainBundle\Entity\Event $events
	 * @return Judging
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
	 * Add nextJudgings
	 *
	 * @param \DOMjudge\MainBundle\Entity\judging $nextJudgings
	 * @return Judging
	 */
	public function addNextJudging(\DOMjudge\MainBundle\Entity\judging $nextJudgings)
	{
		$this->nextJudgings[] = $nextJudgings;

		return $this;
	}

	/**
	 * Remove nextJudgings
	 *
	 * @param \DOMjudge\MainBundle\Entity\judging $nextJudgings
	 */
	public function removeNextJudging(\DOMjudge\MainBundle\Entity\judging $nextJudgings)
	{
		$this->nextJudgings->removeElement($nextJudgings);
	}

	/**
	 * Get nextJudgings
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getNextJudgings()
	{
		return $this->nextJudgings;
	}

	/**
	 * Add JudgingRuns
	 *
	 * @param \DOMjudge\MainBundle\Entity\JudgingRun $judgingRuns
	 * @return Judging
	 */
	public function addJudgingRun(\DOMjudge\MainBundle\Entity\judgingRun $judgingRuns)
	{
		$this->judgingRuns[] = $judgingRuns;

		return $this;
	}

	/**
	 * Remove JudgingRuns
	 *
	 * @param \DOMjudge\MainBundle\Entity\JudgingRun $judgingRuns
	 */
	public function removeJudgingRun(\DOMjudge\MainBundle\Entity\judgingRun $judgingRuns)
	{
		$this->judgingRuns->removeElement($judgingRuns);
	}

	/**
	 * Get JudgingRuns
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getJudgingRuns()
	{
		return $this->judgingRuns;
	}

	/**
	 * Set contest
	 *
	 * @param \DOMjudge\MainBundle\Entity\Contest $contest
	 * @return Judging
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
	 * Set submission
	 *
	 * @param \DOMjudge\MainBundle\Entity\Submission $submission
	 * @return Judging
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
	 * Set judgehost
	 *
	 * @param \DOMjudge\MainBundle\Entity\Judgehost $judgehost
	 * @return Judging
	 */
	public function setJudgehost(\DOMjudge\MainBundle\Entity\Judgehost $judgehost = null)
	{
		$this->judgehost = $judgehost;

		return $this;
	}

	/**
	 * Get judgehost
	 *
	 * @return \DOMjudge\MainBundle\Entity\Judgehost
	 */
	public function getJudgehost()
	{
		return $this->judgehost;
	}

	/**
	 * Set rejudging
	 *
	 * @param \DOMjudge\MainBundle\Entity\Rejudging $rejudging
	 * @return Judging
	 */
	public function setRejudging(\DOMjudge\MainBundle\Entity\Rejudging $rejudging = null)
	{
		$this->rejudging = $rejudging;

		return $this;
	}

	/**
	 * Get rejudging
	 *
	 * @return \DOMjudge\MainBundle\Entity\Rejudging
	 */
	public function getRejudging()
	{
		return $this->rejudging;
	}

	/**
	 * Set previousJudging
	 *
	 * @param \DOMjudge\MainBundle\Entity\Judging $previousJudging
	 * @return Judging
	 */
	public function setPreviousJudging(\DOMjudge\MainBundle\Entity\Judging $previousJudging = null)
	{
		$this->previousJudging = $previousJudging;

		return $this;
	}

	/**
	 * Get previousJudging
	 *
	 * @return \DOMjudge\MainBundle\Entity\Judging
	 */
	public function getPreviousJudging()
	{
		return $this->previousJudging;
	}
}
