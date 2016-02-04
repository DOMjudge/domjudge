<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Rejudging
 *
 * @ORM\Table(name="rejudging", indexes={@ORM\Index(name="userid_start", columns={"userid_start"}), @ORM\Index(name="userid_finish", columns={"userid_finish"})})
 * @ORM\Entity
 */
class Rejudging
{
	/**
	 * @var integer
	 *
	 * @ORM\Column(name="rejudgingid", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 */
	private $rejudgingid;

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
	 * @ORM\Column(name="reason", type="string", length=255, nullable=false)
	 */
	private $reason;

	/**
	 * @var boolean
	 *
	 * @ORM\Column(name="valid", type="boolean", nullable=false)
	 */
	private $valid;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 *
	 * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Judging", mappedBy="rejudging")
	 */
	private $judgings;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 *
	 * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Submission", mappedBy="rejudging")
	 */
	private $submissions;

	/**
	 * @var \DOMjudge\MainBundle\Entity\User
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\User", inversedBy="startedRejudgings")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="userid_start", referencedColumnName="userid")
	 * })
	 */
	private $startedByUser;

	/**
	 * @var \DOMjudge\MainBundle\Entity\User
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\User", inversedBy="finishedRejudgings")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="userid_finish", referencedColumnName="userid")
	 * })
	 */
	private $finishedByUser;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->judgings = new \Doctrine\Common\Collections\ArrayCollection();
		$this->submissions = new \Doctrine\Common\Collections\ArrayCollection();
	}


	/**
	 * Get rejudgingid
	 *
	 * @return integer
	 */
	public function getRejudgingid()
	{
		return $this->rejudgingid;
	}

	/**
	 * Set startTime
	 *
	 * @param string $startTime
	 * @return Rejudging
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
	 * @return Rejudging
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
	 * Set reason
	 *
	 * @param string $reason
	 * @return Rejudging
	 */
	public function setReason($reason)
	{
		$this->reason = $reason;

		return $this;
	}

	/**
	 * Get reason
	 *
	 * @return string
	 */
	public function getReason()
	{
		return $this->reason;
	}

	/**
	 * Set valid
	 *
	 * @param boolean $valid
	 * @return Rejudging
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
	 * Add judgings
	 *
	 * @param \DOMjudge\MainBundle\Entity\Judging $judgings
	 * @return Rejudging
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
	 * Add submissions
	 *
	 * @param \DOMjudge\MainBundle\Entity\Submission $submissions
	 * @return Rejudging
	 */
	public function addSubmission(\DOMjudge\MainBundle\Entity\Submission $submissions)
	{
		$this->submissions[] = $submissions;

		return $this;
	}

	/**
	 * Remove submissions
	 *
	 * @param \DOMjudge\MainBundle\Entity\Submission $submissions
	 */
	public function removeSubmission(\DOMjudge\MainBundle\Entity\Submission $submissions)
	{
		$this->submissions->removeElement($submissions);
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
	 * Set startedByUser
	 *
	 * @param \DOMjudge\MainBundle\Entity\User $startedByUser
	 * @return Rejudging
	 */
	public function setStartedByUser(\DOMjudge\MainBundle\Entity\User $startedByUser = null)
	{
		$this->startedByUser = $startedByUser;

		return $this;
	}

	/**
	 * Get startedByUser
	 *
	 * @return \DOMjudge\MainBundle\Entity\User
	 */
	public function getStartedByUser()
	{
		return $this->startedByUser;
	}

	/**
	 * Set finishedByUser
	 *
	 * @param \DOMjudge\MainBundle\Entity\User $finishedByUser
	 * @return Rejudging
	 */
	public function setFinishedByUser(\DOMjudge\MainBundle\Entity\User $finishedByUser = null)
	{
		$this->finishedByUser = $finishedByUser;

		return $this;
	}

	/**
	 * Get finishedByUser
	 *
	 * @return \DOMjudge\MainBundle\Entity\User
	 */
	public function getFinishedByUser()
	{
		return $this->finishedByUser;
	}
}
