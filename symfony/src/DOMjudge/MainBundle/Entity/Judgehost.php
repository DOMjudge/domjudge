<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Judgehost
 *
 * @ORM\Table(name="judgehost", indexes={@ORM\Index(name="restrictionid", columns={"restrictionid"})})
 * @ORM\Entity
 */
class Judgehost
{
	/**
	 * @var string
	 *
	 * @ORM\Column(name="hostname", type="string", length=50)
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 */
	private $hostname;

	/**
	 * @var boolean
	 *
	 * @ORM\Column(name="active", type="boolean", nullable=false)
	 */
	private $active;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="polltime", type="decimal", precision=32, scale=9, nullable=true)
	 */
	private $pollTime;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 *
	 * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Judging", mappedBy="judgehost")
	 */
	private $judgings;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 *
	 * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Submission", mappedBy="judgehost")
	 */
	private $submissions;

	/**
	 * @var \DOMjudge\MainBundle\Entity\JudgehostRestriction
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\JudgehostRestriction", inversedBy="judgehosts")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="restrictionid", referencedColumnName="restrictionid")
	 * })
	 */
	private $restriction;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->judgings = new \Doctrine\Common\Collections\ArrayCollection();
		$this->submissions = new \Doctrine\Common\Collections\ArrayCollection();
	}


	/**
	 * Get hostname
	 *
	 * @return string
	 */
	public function getHostname()
	{
		return $this->hostname;
	}

	/**
	 * Set active
	 *
	 * @param boolean $active
	 * @return Judgehost
	 */
	public function setActive($active)
	{
		$this->active = $active;

		return $this;
	}

	/**
	 * Get active
	 *
	 * @return boolean
	 */
	public function getActive()
	{
		return $this->active;
	}

	/**
	 * Set pollTime
	 *
	 * @param string $pollTime
	 * @return Judgehost
	 */
	public function setPollTime($pollTime)
	{
		$this->pollTime = $pollTime;

		return $this;
	}

	/**
	 * Get pollTime
	 *
	 * @return string
	 */
	public function getPollTime()
	{
		return $this->pollTime;
	}

	/**
	 * Add judgings
	 *
	 * @param \DOMjudge\MainBundle\Entity\Judging $judgings
	 * @return Judgehost
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
	 * @return Judgehost
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
	 * Set restriction
	 *
	 * @param \DOMjudge\MainBundle\Entity\JudgehostRestriction $restriction
	 * @return Judgehost
	 */
	public function setRestriction(\DOMjudge\MainBundle\Entity\JudgehostRestriction $restriction = null)
	{
		$this->restriction = $restriction;

		return $this;
	}

	/**
	 * Get restriction
	 *
	 * @return \DOMjudge\MainBundle\Entity\JudgehostRestriction
	 */
	public function getRestriction()
	{
		return $this->restriction;
	}
}
