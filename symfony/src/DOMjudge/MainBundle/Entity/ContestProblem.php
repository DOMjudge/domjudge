<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ContestProblem
 *
 * @ORM\Table(name="contestproblem", indexes={
 *     @ORM\Index(name="cid", columns={"cid"}),
 *     @ORM\Index(name="probid", columns={"probid"})
 * }, uniqueConstraints={@ORM\UniqueConstraint(name="shortname", columns={"cid", "shortname"})})
 * @ORM\Entity
 */
class ContestProblem
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
	 * @ORM\Column(name="probid", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="NONE")
	 */
	private $probid;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="shortname", type="string", length=255, nullable=false)
	 */
	private $shortName;

	/**
	 * @var int
	 *
	 * @ORM\Column(name="points", type="integer", nullable=false)
	 */
	private $points;

	/**
	 * @var bool
	 *
	 * @ORM\Column(name="allow_submit", type="boolean", nullable=false)
	 */
	private $allowSubmit;

	/**
	 * @var bool
	 *
	 * @ORM\Column(name="allow_judge", type="boolean", nullable=false)
	 */
	private $allowJudge;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="color", type="string", length=25, nullable=true)
	 */
	private $color;

	/**
	 * @var bool
	 *
	 * @ORM\Column(name="lazy_eval_results", type="boolean", nullable=false)
	 */
	private $lazyEvalResults;

	/**
	 * @ORM\Id()
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Contest", inversedBy="contestProblems")
	 * @ORM\JoinColumn(name="cid", referencedColumnName="cid", nullable=false)
	 */
	protected $contest;

	/**
	 * @ORM\Id()
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Problem", inversedBy="contestProblems")
	 * @ORM\JoinColumn(name="probid", referencedColumnName="probid", nullable=false)
	 */
	protected $problem;

    /**
     * Set cid
     *
     * @param integer $cid
     * @return ContestProblem
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
     * Set probid
     *
     * @param integer $probid
     * @return ContestProblem
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
     * Set shortName
     *
     * @param string $shortName
     * @return ContestProblem
     */
    public function setShortName($shortName)
    {
        $this->shortName = $shortName;

        return $this;
    }

    /**
     * Get shortName
     *
     * @return string 
     */
    public function getShortName()
    {
        return $this->shortName;
    }

    /**
     * Set points
     *
     * @param integer $points
     * @return ContestProblem
     */
    public function setPoints($points)
    {
        $this->points = $points;

        return $this;
    }

    /**
     * Get points
     *
     * @return integer 
     */
    public function getPoints()
    {
        return $this->points;
    }

    /**
     * Set allowSubmit
     *
     * @param boolean $allowSubmit
     * @return ContestProblem
     */
    public function setAllowSubmit($allowSubmit)
    {
        $this->allowSubmit = $allowSubmit;

        return $this;
    }

    /**
     * Get allowSubmit
     *
     * @return boolean 
     */
    public function getAllowSubmit()
    {
        return $this->allowSubmit;
    }

    /**
     * Set allowJudge
     *
     * @param boolean $allowJudge
     * @return ContestProblem
     */
    public function setAllowJudge($allowJudge)
    {
        $this->allowJudge = $allowJudge;

        return $this;
    }

    /**
     * Get allowJudge
     *
     * @return boolean 
     */
    public function getAllowJudge()
    {
        return $this->allowJudge;
    }

    /**
     * Set color
     *
     * @param string $color
     * @return ContestProblem
     */
    public function setColor($color)
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Get color
     *
     * @return string 
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * Set lazyEvalResults
     *
     * @param boolean $lazyEvalResults
     * @return ContestProblem
     */
    public function setLazyEvalResults($lazyEvalResults)
    {
        $this->lazyEvalResults = $lazyEvalResults;

        return $this;
    }

    /**
     * Get lazyEvalResults
     *
     * @return boolean 
     */
    public function getLazyEvalResults()
    {
        return $this->lazyEvalResults;
    }

    /**
     * Set contest
     *
     * @param \DOMjudge\MainBundle\Entity\Contest $contest
     * @return ContestProblem
     */
    public function setContest(\DOMjudge\MainBundle\Entity\Contest $contest)
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
     * Set problem
     *
     * @param \DOMjudge\MainBundle\Entity\Problem $problem
     * @return ContestProblem
     */
    public function setProblem(\DOMjudge\MainBundle\Entity\Problem $problem)
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
