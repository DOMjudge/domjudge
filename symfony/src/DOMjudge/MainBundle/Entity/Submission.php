<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping as ORM;

/**
 * Submission
 *
 * @ORM\Table(name="submission", indexes={@ORM\Index(name="teamid", columns={"cid", "teamid"}), @ORM\Index(name="judgehost", columns={"cid", "judgehost"}), @ORM\Index(name="teamid_2", columns={"teamid"}), @ORM\Index(name="probid", columns={"probid"}), @ORM\Index(name="langid", columns={"langid"}), @ORM\Index(name="judgehost_2", columns={"judgehost"}), @ORM\Index(name="origsubmitid", columns={"origsubmitid"}), @ORM\Index(name="rejudgingid", columns={"rejudgingid"}), @ORM\Index(name="IDX_DB055AF34B30D9C4", columns={"cid"})})
 * @ORM\Entity(repositoryClass="SubmissionRepository")
 */
class Submission
{
	/**
	 * @var integer
	 *
	 * @ORM\Column(name="submitid", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 */
	private $submitid;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="submittime", type="decimal", precision=32, scale=9, nullable=false)
	 */
	private $submitTime;

	/**
	 * @var boolean
	 *
	 * @ORM\Column(name="valid", type="boolean", nullable=false)
	 */
	private $valid;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="expected_results", type="string", length=255, nullable=true)
	 */
	private $expectedResults;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 *
	 * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Balloon", mappedBy="submission")
	 */
	private $balloons;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 *
	 * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Event", mappedBy="submission")
	 */
	private $events;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 *
	 * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Judging", mappedBy="submission")
	 */
	private $judgings;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 *
	 * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Submission", mappedBy="originalSubmission")
	 */
	private $followUpSubmissions;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 *
	 * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\SubmissionFile", mappedBy="submission")
	 */
	private $submissionFiles;

	/**
	 * @var \DOMjudge\MainBundle\Entity\Contest
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Contest", inversedBy="submissions")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="cid", referencedColumnName="cid")
	 * })
	 */
	private $contest;

	/**
	 * @var \DOMjudge\MainBundle\Entity\Team
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Team", inversedBy="submissions")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="teamid", referencedColumnName="teamid")
	 * })
	 */
	private $team;

	/**
	 * @var \DOMjudge\MainBundle\Entity\Problem
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Problem", inversedBy="submissions")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="probid", referencedColumnName="probid")
	 * })
	 */
	private $problem;

	/**
	 * @var \DOMjudge\MainBundle\Entity\Language
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Language", inversedBy="submissions")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="langid", referencedColumnName="langid")
	 * })
	 */
	private $language;

	/**
	 * @var \DOMjudge\MainBundle\Entity\Judgehost
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Judgehost", inversedBy="submissions")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="judgehost", referencedColumnName="hostname")
	 * })
	 */
	private $judgehost;

	/**
	 * @var \DOMjudge\MainBundle\Entity\Submission
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Submission", inversedBy="followUpSubmissions")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="origsubmitid", referencedColumnName="submitid")
	 * })
	 */
	private $originalSubmission;

	/**
	 * @var \DOMjudge\MainBundle\Entity\Rejudging
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Rejudging", inversedBy="submissions")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="rejudgingid", referencedColumnName="rejudgingid")
	 * })
	 */
	private $rejudging;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->balloons = new \Doctrine\Common\Collections\ArrayCollection();
		$this->events = new \Doctrine\Common\Collections\ArrayCollection();
		$this->judgings = new \Doctrine\Common\Collections\ArrayCollection();
		$this->followUpSubmissions = new \Doctrine\Common\Collections\ArrayCollection();
		$this->submissionFiles = new \Doctrine\Common\Collections\ArrayCollection();
	}


	/**
	 * Get submitid
	 *
	 * @return integer
	 */
	public function getSubmitid()
	{
		return $this->submitid;
	}

	/**
	 * Set submitTime
	 *
	 * @param string $submitTime
	 * @return Submission
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
	 * Set valid
	 *
	 * @param boolean $valid
	 * @return Submission
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
	 * Set expectedResults
	 *
	 * @param string $expectedResults
	 * @return Submission
	 */
	public function setExpectedResults($expectedResults)
	{
		$this->expectedResults = $expectedResults;

		return $this;
	}

	/**
	 * Get expectedResults
	 *
	 * @return string
	 */
	public function getExpectedResults()
	{
		return $this->expectedResults;
	}

	/**
	 * Add balloons
	 *
	 * @param \DOMjudge\MainBundle\Entity\Balloon $balloons
	 * @return Submission
	 */
	public function addBalloon(\DOMjudge\MainBundle\Entity\Balloon $balloons)
	{
		$this->balloons[] = $balloons;

		return $this;
	}

	/**
	 * Remove balloons
	 *
	 * @param \DOMjudge\MainBundle\Entity\Balloon $balloons
	 */
	public function removeBalloon(\DOMjudge\MainBundle\Entity\Balloon $balloons)
	{
		$this->balloons->removeElement($balloons);
	}

	/**
	 * Get balloons
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getBalloons()
	{
		return $this->balloons;
	}

	/**
	 * Add events
	 *
	 * @param \DOMjudge\MainBundle\Entity\Event $events
	 * @return Submission
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
	 * Add judgings
	 *
	 * @param \DOMjudge\MainBundle\Entity\Judging $judgings
	 * @return Submission
	 */
	public function addJudging(\DOMjudge\MainBundle\Entity\Judging $judgings)
	{
		$this->judgings[] = $judgings;

		return $this;
	}

	/**
	 * Remove judgings
	 *
	 * @param \DOMjudge\MainBundle\Entity\Judging $judgings
	 */
	public function removeJudging(\DOMjudge\MainBundle\Entity\Judging $judgings)
	{
		$this->judgings->removeElement($judgings);
	}

	/**
	 * Get judgings
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getJudgings()
	{
		return $this->judgings;
	}

	/**
	 * Add followUpSubmissions
	 *
	 * @param \DOMjudge\MainBundle\Entity\Submission $followUpSubmissions
	 * @return Submission
	 */
	public function addFollowUpSubmission(\DOMjudge\MainBundle\Entity\Submission $followUpSubmissions)
	{
		$this->followUpSubmissions[] = $followUpSubmissions;

		return $this;
	}

	/**
	 * Remove followUpSubmissions
	 *
	 * @param \DOMjudge\MainBundle\Entity\Submission $followUpSubmissions
	 */
	public function removeFollowUpSubmission(\DOMjudge\MainBundle\Entity\Submission $followUpSubmissions)
	{
		$this->followUpSubmissions->removeElement($followUpSubmissions);
	}

	/**
	 * Get followUpSubmissions
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getFollowUpSubmissions()
	{
		return $this->followUpSubmissions;
	}

	/**
	 * Add submissionFiles
	 *
	 * @param \DOMjudge\MainBundle\Entity\SubmissionFile $submissionFiles
	 * @return Submission
	 */
	public function addSubmissionFile(\DOMjudge\MainBundle\Entity\SubmissionFile $submissionFiles)
	{
		$this->submissionFiles[] = $submissionFiles;

		return $this;
	}

	/**
	 * Remove submissionFiles
	 *
	 * @param \DOMjudge\MainBundle\Entity\SubmissionFile $submissionFiles
	 */
	public function removeSubmissionFile(\DOMjudge\MainBundle\Entity\SubmissionFile $submissionFiles)
	{
		$this->submissionFiles->removeElement($submissionFiles);
	}

	/**
	 * Get submissionFiles
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getSubmissionFiles()
	{
		return $this->submissionFiles;
	}

	/**
	 * Set contest
	 *
	 * @param \DOMjudge\MainBundle\Entity\Contest $contest
	 * @return Submission
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
	 * Set team
	 *
	 * @param \DOMjudge\MainBundle\Entity\Team $team
	 * @return Submission
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

	/**
	 * Set problem
	 *
	 * @param \DOMjudge\MainBundle\Entity\Problem $problem
	 * @return Submission
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
	 * Set language
	 *
	 * @param \DOMjudge\MainBundle\Entity\Language $language
	 * @return Submission
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
	 * Set judgehost
	 *
	 * @param \DOMjudge\MainBundle\Entity\Judgehost $judgehost
	 * @return Submission
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
	 * Set originalSubmission
	 *
	 * @param \DOMjudge\MainBundle\Entity\Submission $originalSubmission
	 * @return Submission
	 */
	public function setOriginalSubmission(\DOMjudge\MainBundle\Entity\Submission $originalSubmission = null)
	{
		$this->originalSubmission = $originalSubmission;

		return $this;
	}

	/**
	 * Get originalSubmission
	 *
	 * @return \DOMjudge\MainBundle\Entity\Submission
	 */
	public function getOriginalSubmission()
	{
		return $this->originalSubmission;
	}

	/**
	 * Set rejudging
	 *
	 * @param \DOMjudge\MainBundle\Entity\Rejudging $rejudging
	 * @return Submission
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
	 * Return the contest problem for this submission
	 * @return ContestProblem
	 * @throws \Exception
	 */
	public function getContestProblem()
	{
		/** @var ContestProblem[] $contest_problems */
		$contest_problems = $this->getProblem()->getContestProblems();
		if (empty($contest_problems) ||
			$contest_problems[0]->getContest()->getCid() !== $this->getContest()->getCid() ) {
			throw new \Exception("Contest problems not loaded or mismatch in contest information");
		}
		return $contest_problems[0];
	}
}
