<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ScoreCacheJury
 *
 * @ORM\Table(name="scorecache_jury")
 * @ORM\Entity
 */
class ScoreCacheJury
{
	/**
	 * @var integer
	 *
	 * @ORM\Column(name="cid", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="NONE")
	 */
	private $cid;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="teamid", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="NONE")
	 */
	private $teamid;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="probid", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="NONE")
	 */
	private $probid;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="submissions", type="integer", nullable=false)
	 */
	private $submissions;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="pending", type="integer", nullable=false)
	 */
	private $pending;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="totaltime", type="integer", nullable=false)
	 */
	private $totalTime;

	/**
	 * @var boolean
	 *
	 * @ORM\Column(name="is_correct", type="boolean", nullable=false)
	 */
	private $isCorrect;

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
	 * @var \DOMjudge\MainBundle\Entity\Team
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Team")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="teamid", referencedColumnName="teamid")
	 * })
	 */
	private $team;

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
	 * Set cid
	 *
	 * @param integer $cid
	 * @return ScoreCacheJury
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
	 * Set teamid
	 *
	 * @param integer $teamid
	 * @return ScoreCacheJury
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
	 * Set probid
	 *
	 * @param integer $probid
	 * @return ScoreCacheJury
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
	 * Set submissions
	 *
	 * @param integer $submissions
	 * @return ScoreCacheJury
	 */
	public function setSubmissions($submissions)
	{
		$this->submissions = $submissions;

		return $this;
	}

	/**
	 * Get submissions
	 *
	 * @return integer
	 */
	public function getSubmissions()
	{
		return $this->submissions;
	}

	/**
	 * Set pending
	 *
	 * @param integer $pending
	 * @return ScoreCacheJury
	 */
	public function setPending($pending)
	{
		$this->pending = $pending;

		return $this;
	}

	/**
	 * Get pending
	 *
	 * @return integer
	 */
	public function getPending()
	{
		return $this->pending;
	}

	/**
	 * Set totalTime
	 *
	 * @param integer $totalTime
	 * @return ScoreCacheJury
	 */
	public function setTotalTime($totalTime)
	{
		$this->totalTime = $totalTime;

		return $this;
	}

	/**
	 * Get totalTime
	 *
	 * @return integer
	 */
	public function getTotalTime()
	{
		return $this->totalTime;
	}

	/**
	 * Set isCorrect
	 *
	 * @param boolean $isCorrect
	 * @return ScoreCacheJury
	 */
	public function setIsCorrect($isCorrect)
	{
		$this->isCorrect = $isCorrect;

		return $this;
	}

	/**
	 * Get isCorrect
	 *
	 * @return boolean
	 */
	public function getIsCorrect()
	{
		return $this->isCorrect;
	}

	/**
	 * Set contest
	 *
	 * @param \DOMjudge\MainBundle\Entity\Contest $contest
	 * @return ScoreCacheJury
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
	 * @return ScoreCacheJury
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
	 * @return ScoreCacheJury
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
}
