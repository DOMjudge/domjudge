<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Problem
 *
 * @ORM\Table(name="problem")
 * @ORM\Entity
 */
class Problem
{
	/**
	 * @var integer
	 *
	 * @ORM\Column(name="probid", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 */
	private $probid;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="name", type="string", length=255, nullable=false)
	 */
	private $name;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="timelimit", type="integer", nullable=false)
	 */
	private $timeLimit;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="memlimit", type="integer", nullable=true)
	 */
	private $memLimit;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="outputlimit", type="integer", nullable=true)
	 */
	private $outputLimit;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="special_run", type="string", length=32, nullable=true)
	 */
	private $specialRun;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="special_compare", type="string", length=32, nullable=true)
	 */
	private $specialCompare;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="special_compare_args", type="string", length=255, nullable=true)
	 */
	private $specialCompareArgs;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="problemtext", type="blob", nullable=true)
	 */
	private $problemText;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="problemtext_type", type="string", length=4, nullable=true)
	 */
	private $problemTextType;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 *
	 * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Clarification", mappedBy="problem")
	 */
	private $clarifications;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 *
	 * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Event", mappedBy="problem")
	 */
	private $events;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 *
	 * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Submission", mappedBy="problem")
	 */
	private $submissions;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 *
	 * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\TestCase", mappedBy="problem")
	 */
	private $testcases;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 *
	 * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\ContestProblem", mappedBy="problem")
	 */
	private $contestProblems;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->clarifications = new \Doctrine\Common\Collections\ArrayCollection();
		$this->events = new \Doctrine\Common\Collections\ArrayCollection();
		$this->submissions = new \Doctrine\Common\Collections\ArrayCollection();
		$this->testcases = new \Doctrine\Common\Collections\ArrayCollection();
		$this->contestProblems = new \Doctrine\Common\Collections\ArrayCollection();
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
	 * Set name
	 *
	 * @param string $name
	 * @return Problem
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
	 * Set timeLimit
	 *
	 * @param integer $timeLimit
	 * @return Problem
	 */
	public function setTimeLimit($timeLimit)
	{
		$this->timeLimit = $timeLimit;

		return $this;
	}

	/**
	 * Get timeLimit
	 *
	 * @return integer
	 */
	public function getTimeLimit()
	{
		return $this->timeLimit;
	}

	/**
	 * Set memLimit
	 *
	 * @param integer $memLimit
	 * @return Problem
	 */
	public function setMemLimit($memLimit)
	{
		$this->memLimit = $memLimit;

		return $this;
	}

	/**
	 * Get memLimit
	 *
	 * @return integer
	 */
	public function getMemLimit()
	{
		return $this->memLimit;
	}

	/**
	 * Set outputLimit
	 *
	 * @param integer $outputLimit
	 * @return Problem
	 */
	public function setOutputLimit($outputLimit)
	{
		$this->outputLimit = $outputLimit;

		return $this;
	}

	/**
	 * Get outputLimit
	 *
	 * @return integer
	 */
	public function getOutputLimit()
	{
		return $this->outputLimit;
	}

	/**
	 * Set specialRun
	 *
	 * @param string $specialRun
	 * @return Problem
	 */
	public function setSpecialRun($specialRun)
	{
		$this->specialRun = $specialRun;

		return $this;
	}

	/**
	 * Get specialRun
	 *
	 * @return string
	 */
	public function getSpecialRun()
	{
		return $this->specialRun;
	}

	/**
	 * Set specialCompare
	 *
	 * @param string $specialCompare
	 * @return Problem
	 */
	public function setSpecialCompare($specialCompare)
	{
		$this->specialCompare = $specialCompare;

		return $this;
	}

	/**
	 * Get specialCompare
	 *
	 * @return string
	 */
	public function getSpecialCompare()
	{
		return $this->specialCompare;
	}

	/**
	 * Set specialCompareArgs
	 *
	 * @param string $specialCompareArgs
	 * @return Problem
	 */
	public function setSpecialCompareArgs($specialCompareArgs)
	{
		$this->specialCompareArgs = $specialCompareArgs;

		return $this;
	}

	/**
	 * Get specialCompareArgs
	 *
	 * @return string
	 */
	public function getSpecialCompareArgs()
	{
		return $this->specialCompareArgs;
	}

	/**
	 * Set problemText
	 *
	 * @param string $problemText
	 * @return Problem
	 */
	public function setProblemText($problemText)
	{
		$this->problemText = $problemText;

		return $this;
	}

	/**
	 * Get problemText
	 *
	 * @return string
	 */
	public function getProblemText()
	{
		return $this->problemText;
	}

	/**
	 * Set problemTextType
	 *
	 * @param string $problemTextType
	 * @return Problem
	 */
	public function setProblemTextType($problemTextType)
	{
		$this->problemTextType = $problemTextType;

		return $this;
	}

	/**
	 * Get problemTextType
	 *
	 * @return string
	 */
	public function getProblemTextType()
	{
		return $this->problemTextType;
	}

	/**
	 * Add clarifications
	 *
	 * @param \DOMjudge\MainBundle\Entity\Clarification $clarifications
	 * @return Problem
	 */
	public function addClarification(\DOMjudge\MainBundle\Entity\Clarification $clarifications)
	{
		$this->clarifications[] = $clarifications;

		return $this;
	}

	/**
	 * Remove clarifications
	 *
	 * @param \DOMjudge\MainBundle\Entity\Clarification $clarifications
	 */
	public function removeClarification(\DOMjudge\MainBundle\Entity\Clarification $clarifications)
	{
		$this->clarifications->removeElement($clarifications);
	}

	/**
	 * Get clarifications
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getClarifications()
	{
		return $this->clarifications;
	}

	/**
	 * Add events
	 *
	 * @param \DOMjudge\MainBundle\Entity\Event $events
	 * @return Problem
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
	 * Add submissions
	 *
	 * @param \DOMjudge\MainBundle\Entity\Submission $submissions
	 * @return Problem
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
	 * Add testcases
	 *
	 * @param \DOMjudge\MainBundle\Entity\TestCase $testcases
	 * @return Problem
	 */
	public function addTestcase(\DOMjudge\MainBundle\Entity\TestCase $testcases)
	{
		$this->testcases[] = $testcases;

		return $this;
	}

	/**
	 * Remove testcases
	 *
	 * @param \DOMjudge\MainBundle\Entity\TestCase $testcases
	 */
	public function removeTestcase(\DOMjudge\MainBundle\Entity\TestCase $testcases)
	{
		$this->testcases->removeElement($testcases);
	}

	/**
	 * Get testcases
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getTestcases()
	{
		return $this->testcases;
	}

    /**
     * Add contestProblems
     *
     * @param \DOMjudge\MainBundle\Entity\ContestProblem $contestProblems
     * @return Problem
     */
    public function addContestProblem(\DOMjudge\MainBundle\Entity\ContestProblem $contestProblems)
    {
        $this->contestProblems[] = $contestProblems;

        return $this;
    }

    /**
     * Remove contestProblems
     *
     * @param \DOMjudge\MainBundle\Entity\ContestProblem $contestProblems
     */
    public function removeContestProblem(\DOMjudge\MainBundle\Entity\ContestProblem $contestProblems)
    {
        $this->contestProblems->removeElement($contestProblems);
    }

    /**
     * Get contestProblems
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getContestProblems()
    {
        return $this->contestProblems;
    }
}
