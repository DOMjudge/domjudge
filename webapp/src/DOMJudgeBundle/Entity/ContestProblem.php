<?php declare(strict_types=1);
namespace DOMJudgeBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Many-to-Many mapping of contests and problems
 * @ORM\Entity()
 * @ORM\Table(name="contestproblem", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 * @Serializer\VirtualProperty(
 *     "short_name",
 *     exp="object.getShortname()",
 *     options={@Serializer\Groups("Nonstrict"), @Serializer\Type("string")}
 * )
 */
class ContestProblem
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(type="integer", name="cid", options={"comment"="Unique ID"}, nullable=false)
     * @Serializer\Exclude()
     */
    private $cid;

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(type="integer", name="probid", options={"comment"="Problem ID"}, nullable=false)
     * @Serializer\SerializedName("id")
     * @Serializer\Type("string")
     */
    private $probid;

    /**
     * @var string
     * @ORM\Column(type="string", name="shortname", length=255, options={"comment"="Unique problem ID within contest (string)"}, nullable=false)
     * @Serializer\SerializedName("label")
     */
    private $shortname;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="points", options={"comment"="Number of points earened by solving this problem"}, nullable=false)
     * @Serializer\Exclude()
     */
    private $points = 1;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="allow_submit", options={"comment"="Are submissions accepted for this problem?"}, nullable=false)
     * @Serializer\Exclude()
     */
    private $allow_submit = true;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="allow_judge", options={"comment"="Are submissions for this problem judged?"}, nullable=false)
     * @Serializer\Exclude()
     */
    private $allow_judge = true;

    /**
     * @var string
     * @ORM\Column(type="string", name="color", length=32, options={"comment"="Balloon colour to display on the scoreboard"}, nullable=true)
     * @Serializer\Exclude()
     */
    private $color;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="lazy_eval_results", options={"comment"="Whether to do lazy evaluation for this problem; if set this overrides the global configuration setting"}, nullable=true)
     * @Serializer\Exclude()
     */
    private $lazy_eval_results = true;

    /**
     * @ORM\ManyToOne(targetEntity="Problem", inversedBy="contest_problems", fetch="EAGER")
     * @ORM\JoinColumn(name="probid", referencedColumnName="probid")
     * @Serializer\Inline()
     */
    private $problem;

    /**
     * @ORM\ManyToOne(targetEntity="Contest", inversedBy="problems")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid")
     * @Serializer\Exclude()
     */
    private $contest;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="ScoreCache", mappedBy="contest_problem")
     * @Serializer\Exclude()
     */
    private $scorecache;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="DOMJudgeBundle\Entity\Submission", mappedBy="contest_problem")
     * @Serializer\Exclude()
     */
    private $submissions;

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

    /**
     * Add scorecache
     *
     * @param \DOMJudgeBundle\Entity\ScoreCache $scorecache
     *
     * @return ContestProblem
     */
    public function addScorecache(\DOMJudgeBundle\Entity\ScoreCache $scorecache)
    {
        $this->scorecache->add($scorecache);

        return $this;
    }

    /**
     * Remove scorecache
     *
     * @param \DOMJudgeBundle\Entity\ScoreCache $scorecache
     */
    public function removeScorecache(\DOMJudgeBundle\Entity\ScoreCache $scorecache)
    {
        $this->scorecache->removeElement($scorecache);
    }

    /**
     * Get scorecache
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getScorecache()
    {
        return $this->scorecache;
    }

    /**
     * Add submission
     *
     * @param Submission $submission
     *
     * @return ContestProblem
     */
    public function addSubmission(Submission $submission)
    {
        $this->submissions->add($submission);

        return $this;
    }

    /**
     * Remove submission
     *
     * @param Submission $submission
     */
    public function removeSubmission(Submission $submission)
    {
        $this->submissions->removeElement($submission);
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
     * Get externalid
     *
     * @return string
     */
    public function getExternalId()
    {
        return $this->getProblem()->getExternalid();
    }
}
