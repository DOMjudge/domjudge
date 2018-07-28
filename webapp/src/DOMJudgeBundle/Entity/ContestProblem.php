<?php
namespace DOMJudgeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;

/**
 * Many-to-Many mapping of contests and problems
 * @ORM\Entity()
 * @ORM\Table(name="contestproblem", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class ContestProblem
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(type="integer", name="cid", options={"comment"="Unique ID"}, nullable=false)
     */
    private $cid;

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(type="integer", name="probid", options={"comment"="Problem ID"}, nullable=false)
     */
    private $probid;

    /**
     * @var string
     * @ORM\Column(type="string", name="shortname", length=255, options={"comment"="Unique problem ID within contest (string)"}, nullable=false)
     */
    private $shortname;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="points", options={"comment"="Number of points earened by solving this problem"}, nullable=false)
     */
    private $points = 1;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="allow_submit", options={"comment"="Are submissions accepted for this problem?"}, nullable=false)
     */
    private $allow_submit = true;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="allow_judge", options={"comment"="Are submissions for this problem judged?"}, nullable=false)
     */
    private $allow_judge = true;

    /**
     * @var string
     * @ORM\Column(type="string", name="color", length=32, options={"comment"="Balloon colour to display on the scoreboard"}, nullable=true)
     */
    private $color;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="lazy_eval_results", options={"comment"="Whether to do lazy evaluation for this problem; if set this overrides the global configuration setting"}, nullable=true)
     */
    private $lazy_eval_results = true;

    /**
     * @ORM\ManyToOne(targetEntity="Problem", inversedBy="contest_problems")
     * @ORM\JoinColumn(name="probid", referencedColumnName="probid")
     * @Groups({"problems"})
     */
    private $problem;

    /**
     * @ORM\ManyToOne(targetEntity="Contest", inversedBy="problems")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid")
     */
    private $contest;


    /**
     * Set cid
     *
     * @param integer $cid
     *
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
     *
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
     * Set shortname
     *
     * @param string $shortname
     *
     * @return ContestProblem
     */
    public function setShortname($shortname)
    {
        $this->shortname = $shortname;

        return $this;
    }

    /**
     * Get shortname
     *
     * @return string
     */
    public function getShortname()
    {
        return $this->shortname;
    }

    /**
     * Set points
     *
     * @param integer $points
     *
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
     *
     * @return ContestProblem
     */
    public function setAllowSubmit($allowSubmit)
    {
        $this->allow_submit = $allowSubmit;

        return $this;
    }

    /**
     * Get allowSubmit
     *
     * @return boolean
     */
    public function getAllowSubmit()
    {
        return $this->allow_submit;
    }

    /**
     * Set allowJudge
     *
     * @param boolean $allowJudge
     *
     * @return ContestProblem
     */
    public function setAllowJudge($allowJudge)
    {
        $this->allow_judge = $allowJudge;

        return $this;
    }

    /**
     * Get allowJudge
     *
     * @return boolean
     */
    public function getAllowJudge()
    {
        return $this->allow_judge;
    }

    /**
     * Set color
     *
     * @param string $color
     *
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
     *
     * @return ContestProblem
     */
    public function setLazyEvalResults($lazyEvalResults)
    {
        $this->lazy_eval_results = $lazyEvalResults;

        return $this;
    }

    /**
     * Get lazyEvalResults
     *
     * @return boolean
     */
    public function getLazyEvalResults()
    {
        return $this->lazy_eval_results;
    }

    /**
     * Set problem
     *
     * @param \DOMJudgeBundle\Entity\Problem $problem
     *
     * @return ContestProblem
     */
    public function setProblem(\DOMJudgeBundle\Entity\Problem $problem = null)
    {
        $this->problem = $problem;

        return $this;
    }

    /**
     * Get problem
     *
     * @return \DOMJudgeBundle\Entity\Problem
     */
    public function getProblem()
    {
        return $this->problem;
    }

    /**
     * Set contest
     *
     * @param \DOMJudgeBundle\Entity\Contest $contest
     *
     * @return ContestProblem
     */
    public function setContest(\DOMJudgeBundle\Entity\Contest $contest = null)
    {
        $this->contest = $contest;

        return $this;
    }

    /**
     * Get contest
     *
     * @return \DOMJudgeBundle\Entity\Contest
     */
    public function getContest()
    {
        return $this->contest;
    }
}
