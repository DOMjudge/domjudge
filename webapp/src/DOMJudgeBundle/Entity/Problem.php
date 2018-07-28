<?php
namespace DOMJudgeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;

/**
 * Stores testcases per problem
 * @ORM\Entity()
 * @ORM\Table(name="problem", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class Problem
{

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="probid", options={"comment"="Unique ID"}, nullable=false)
     * @Groups({"details"})
     */
    private $probid;

    /**
     * @var string
     * @ORM\Column(type="string", name="name", length=255, options={"comment"="Descriptive name"}, nullable=false)
     * @Groups({"details"})
     */
    private $name;

    /**
     * @var double
     * @ORM\Column(type="float", name="timelimit", options={"comment"="Maximum run time (in seconds) for this problem"}, nullable=false)
     * @Groups({"details"})
     */
    private $timelimit = 0;

    /**
     * @var int
     * @ORM\Column(type="integer", name="memlimit", options={"comment"="Maximum memory available (in kB) for this problem", "unsigned"=true}, nullable=true)
     * @Groups({"details"})
     */
    private $memlimit;

    /**
     * @var int
     * @ORM\Column(type="integer", name="outputlimit", options={"comment"="Maximum output size (in kB) for this problem", "unsigned"=true}, nullable=true)
     * @Groups({"details"})
     */
    private $outputlimit;


    /**
     * @var string
     * @ORM\Column(type="string", name="special_run", length=32, options={"comment"="Script to run submissions for this problem"}, nullable=true)
     * @Groups({"details"})
     */
    private $special_run;

    /**
     * @var string
     * @ORM\Column(type="string", name="special_compare", length=32, options={"comment"="Script to compare problem and jury output for this problem"}, nullable=true)
     * @Groups({"details"})
     */
    private $special_compare;

    /**
     * @var string
     * @ORM\Column(type="string", name="special_compare_args", length=32, options={"comment"="Optional arguments to special_compare script"}, nullable=true)
     */
    private $special_compare_args;

    /**
     * @var string
     * @ORM\Column(type="blob", name="problemtext", options={"comment"="Problem text in HTML/PDF/ASCII"}, nullable=true)
     */
    private $problemtext;

    /**
     * @var string
     * @ORM\Column(type="blob", name="problemtext_type", options={"comment"="File type of problem text"}, nullable=true)
     */
    private $problemtext_type;

    /**
     * @ORM\OneToMany(targetEntity="Submission", mappedBy="problem")
     */
    private $submissions;

    /**
     * @ORM\OneToMany(targetEntity="Clarification", mappedBy="problem")
     */
    private $clarifications;

    /**
     * @ORM\OneToMany(targetEntity="ContestProblem", mappedBy="problem")
     */
    private $contest_problems;

    /**
     * @ORM\ManyToOne(targetEntity="Executable", inversedBy="problems_compare")
     * @ORM\JoinColumn(name="special_compare", referencedColumnName="execid")
     */
    private $compare_executable;

    /**
     * @ORM\ManyToOne(targetEntity="Executable", inversedBy="problems_run")
     * @ORM\JoinColumn(name="special_run", referencedColumnName="execid")
     */
    private $run_executable;

    /**
     * @ORM\OneToMany(targetEntity="Testcase", mappedBy="problem")
     */
    private $testcases;

    /**
     * @ORM\OneToMany(targetEntity="ScoreCache", mappedBy="problem")
     */
    private $scorecache;

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
     *
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
     * Set timelimit
     *
     * @param float $timelimit
     *
     * @return Problem
     */
    public function setTimelimit($timelimit)
    {
        $this->timelimit = $timelimit;

        return $this;
    }

    /**
     * Get timelimit
     *
     * @return float
     */
    public function getTimelimit()
    {
        return $this->timelimit;
    }

    /**
     * Set memlimit
     *
     * @param integer $memlimit
     *
     * @return Problem
     */
    public function setMemlimit($memlimit)
    {
        $this->memlimit = $memlimit;

        return $this;
    }

    /**
     * Get memlimit
     *
     * @return integer
     */
    public function getMemlimit()
    {
        return $this->memlimit;
    }

    /**
     * Set outputlimit
     *
     * @param integer $outputlimit
     *
     * @return Problem
     */
    public function setOutputlimit($outputlimit)
    {
        $this->outputlimit = $outputlimit;

        return $this;
    }

    /**
     * Get outputlimit
     *
     * @return integer
     */
    public function getOutputlimit()
    {
        return $this->outputlimit;
    }

    /**
     * Set specialRun
     *
     * @param string $specialRun
     *
     * @return Problem
     */
    public function setSpecialRun($specialRun)
    {
        $this->special_run = $specialRun;

        return $this;
    }

    /**
     * Get specialRun
     *
     * @return string
     */
    public function getSpecialRun()
    {
        return $this->special_run;
    }

    /**
     * Set specialCompare
     *
     * @param string $specialCompare
     *
     * @return Problem
     */
    public function setSpecialCompare($specialCompare)
    {
        $this->special_compare = $specialCompare;

        return $this;
    }

    /**
     * Get specialCompare
     *
     * @return string
     */
    public function getSpecialCompare()
    {
        return $this->special_compare;
    }

    /**
     * Set specialCompareArgs
     *
     * @param string $specialCompareArgs
     *
     * @return Problem
     */
    public function setSpecialCompareArgs($specialCompareArgs)
    {
        $this->special_compare_args = $specialCompareArgs;

        return $this;
    }

    /**
     * Get specialCompareArgs
     *
     * @return string
     */
    public function getSpecialCompareArgs()
    {
        return $this->special_compare_args;
    }

    /**
     * Set problemtext
     *
     * @param string $problemtext
     *
     * @return Problem
     */
    public function setProblemtext($problemtext)
    {
        $this->problemtext = $problemtext;

        return $this;
    }

    /**
     * Get problemtext
     *
     * @return string
     */
    public function getProblemtext()
    {
        return $this->problemtext;
    }

    /**
     * Set problemtextType
     *
     * @param string $problemtextType
     *
     * @return Problem
     */
    public function setProblemtextType($problemtextType)
    {
        $this->problemtext_type = $problemtextType;

        return $this;
    }

    /**
     * Get problemtextType
     *
     * @return string
     */
    public function getProblemtextType()
    {
        return $this->problemtext_type;
    }

    /**
     * Set compareExecutable
     *
     * @param \DOMJudgeBundle\Entity\Executable $compareExecutable
     *
     * @return Problem
     */
    public function setCompareExecutable(\DOMJudgeBundle\Entity\Executable $compareExecutable = null)
    {
        $this->compare_executable = $compareExecutable;

        return $this;
    }

    /**
     * Get compareExecutable
     *
     * @return \DOMJudgeBundle\Entity\Executable
     */
    public function getCompareExecutable()
    {
        return $this->compare_executable;
    }

    /**
     * Set runExecutable
     *
     * @param \DOMJudgeBundle\Entity\Executable $runExecutable
     *
     * @return Problem
     */
    public function setRunExecutable(\DOMJudgeBundle\Entity\Executable $runExecutable = null)
    {
        $this->run_executable = $runExecutable;

        return $this;
    }

    /**
     * Get runExecutable
     *
     * @return \DOMJudgeBundle\Entity\Executable
     */
    public function getRunExecutable()
    {
        return $this->run_executable;
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->testcases = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add testcase
     *
     * @param \DOMJudgeBundle\Entity\Testcase $testcase
     *
     * @return Problem
     */
    public function addTestcase(\DOMJudgeBundle\Entity\Testcase $testcase)
    {
        $this->testcases[] = $testcase;

        return $this;
    }

    /**
     * Remove testcase
     *
     * @param \DOMJudgeBundle\Entity\Testcase $testcase
     */
    public function removeTestcase(\DOMJudgeBundle\Entity\Testcase $testcase)
    {
        $this->testcases->removeElement($testcase);
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
     * Add contestProblem
     *
     * @param \DOMJudgeBundle\Entity\ContestProblem $contestProblem
     *
     * @return Problem
     */
    public function addContestProblem(\DOMJudgeBundle\Entity\ContestProblem $contestProblem)
    {
        $this->contest_problems[] = $contestProblem;

        return $this;
    }

    /**
     * Remove contestProblem
     *
     * @param \DOMJudgeBundle\Entity\ContestProblem $contestProblem
     */
    public function removeContestProblem(\DOMJudgeBundle\Entity\ContestProblem $contestProblem)
    {
        $this->contest_problems->removeElement($contestProblem);
    }

    /**
     * Get contestProblems
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getContestProblems()
    {
        return $this->contest_problems;
    }

    /**
     * Add submission
     *
     * @param \DOMJudgeBundle\Entity\Submission $submission
     *
     * @return Problem
     */
    public function addSubmission(\DOMJudgeBundle\Entity\Submission $submission)
    {
        $this->submissions[] = $submission;

        return $this;
    }

    /**
     * Remove submission
     *
     * @param \DOMJudgeBundle\Entity\Submission $submission
     */
    public function removeSubmission(\DOMJudgeBundle\Entity\Submission $submission)
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
     * Add clarification
     *
     * @param \DOMJudgeBundle\Entity\Clarification $clarification
     *
     * @return Problem
     */
    public function addClarification(\DOMJudgeBundle\Entity\Clarification $clarification)
    {
        $this->clarifications[] = $clarification;

        return $this;
    }

    /**
     * Remove clarification
     *
     * @param \DOMJudgeBundle\Entity\Clarification $clarification
     */
    public function removeClarification(\DOMJudgeBundle\Entity\Clarification $clarification)
    {
        $this->clarifications->removeElement($clarification);
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
     * Add scorecache
     *
     * @param \DOMJudgeBundle\Entity\ScoreCache $scorecache
     *
     * @return Problem
     */
    public function addScorecache(\DOMJudgeBundle\Entity\ScoreCache $scorecache)
    {
        $this->scorecache[] = $scorecache;

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
}
