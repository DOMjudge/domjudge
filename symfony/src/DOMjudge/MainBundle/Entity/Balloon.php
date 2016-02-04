<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Balloon
 *
 * @ORM\Table(name="balloon", indexes={@ORM\Index(name="submitid", columns={"submitid"})})
 * @ORM\Entity
 */
class Balloon
{
	/**
	 * @var integer
	 *
	 * @ORM\Column(name="balloonid", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 */
	private $balloonid;

	/**
	 * @var boolean
	 *
	 * @ORM\Column(name="done", type="boolean", nullable=false)
	 */
	private $done;

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
	 * Get balloonid
	 *
	 * @return integer
	 */
	public function getBalloonid()
	{
		return $this->balloonid;
	}

	/**
	 * Set done
	 *
	 * @param boolean $done
	 * @return Balloon
	 */
	public function setDone($done)
	{
		$this->done = $done;

		return $this;
	}

	/**
	 * Get done
	 *
	 * @return boolean
	 */
	public function getDone()
	{
		return $this->done;
	}

	/**
	 * Set submission
	 *
	 * @param \DOMjudge\MainBundle\Entity\Submission $submission
	 * @return Balloon
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
}
