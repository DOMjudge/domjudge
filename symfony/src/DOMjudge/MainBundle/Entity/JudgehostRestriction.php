<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * JudgehostRestriction
 *
 * @ORM\Table(name="judgehost_restriction")
 * @ORM\Entity
 */
class JudgehostRestriction
{
	/**
	 * @var integer
	 *
	 * @ORM\Column(name="restrictionid", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 */
	private $restrictionid;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="name", type="string", length=255, nullable=false)
	 */
	private $name;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="restrictions", type="text", nullable=true)
	 */
	private $restrictions;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 *
	 * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\judgehost", mappedBy="restriction")
	 */
	private $judgehosts;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->judgehosts = new \Doctrine\Common\Collections\ArrayCollection();
	}


	/**
	 * Get restrictionid
	 *
	 * @return integer
	 */
	public function getRestrictionid()
	{
		return $this->restrictionid;
	}

	/**
	 * Set name
	 *
	 * @param string $name
	 * @return JudgehostRestriction
	 */
	public function setName($name)
	{
		$this->name = $name;

		return $this;
	}

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Set restrictions
	 *
	 * @param string $restrictions
	 * @return JudgehostRestriction
	 */
	public function setRestrictions($restrictions)
	{
		$this->restrictions = $restrictions;

		return $this;
	}

	/**
	 * Get restrictions
	 *
	 * @return string
	 */
	public function getRestrictions()
	{
		return $this->restrictions;
	}

	/**
	 * Add judgehosts
	 *
	 * @param \DOMjudge\MainBundle\Entity\judgehost $judgehosts
	 * @return JudgehostRestriction
	 */
	public function addJudgehost(\DOMjudge\MainBundle\Entity\judgehost $judgehosts)
	{
		$this->judgehosts[] = $judgehosts;

		return $this;
	}

	/**
	 * Remove judgehosts
	 *
	 * @param \DOMjudge\MainBundle\Entity\judgehost $judgehosts
	 */
	public function removeJudgehost(\DOMjudge\MainBundle\Entity\judgehost $judgehosts)
	{
		$this->judgehosts->removeElement($judgehosts);
	}

	/**
	 * Get judgehosts
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getJudgehosts()
	{
		return $this->judgehosts;
	}
}
