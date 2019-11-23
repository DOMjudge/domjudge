<?php declare(strict_types=1);

namespace App\Entity;

use App\Utils\Utils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Stores testcases per problem
 * @ORM\Entity()
 * @ORM\Table(
 *     name="problem",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4","comment"="Problems the teams can submit solutions for"},
 *     indexes={
 *         @ORM\Index(name="externalid", columns={"externalid"}, options={"lengths": {"190"}}),
 *         @ORM\Index(name="special_run", columns={"special_run"}),
 *         @ORM\Index(name="special_compare", columns={"special_compare"})
 *     })
 * @ORM\HasLifecycleCallbacks()
 * @UniqueEntity("externalid", message="A problem with the same `externalid` already exists - is this a duplicate?")
 */
class Problem extends BaseApiEntity
{

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="probid", options={"comment"="Problem ID","unsigned"="true"}, nullable=false)
     * @Serializer\Exclude()
     */
    protected $probid;

    /**
     * @var string
     * @ORM\Column(type="string", name="externalid", length=255,
     *     options={"comment"="Problem ID in an external system, should be unique inside a single contest",
     *              "collation"="utf8mb4_bin","default":"NULL"},
     *     nullable=true)
     */
    protected $externalid;

    /**
     * @var string
     * @ORM\Column(type="string", name="name", length=255, options={"comment"="Descriptive name"}, nullable=false)
     */
    private $name;

    /**
     * @var double
     * @ORM\Column(type="float", name="timelimit",
     *     options={"comment"="Maximum run time (in seconds) for this problem",
     *              "default"="0","unsigned"="true"},
     *     nullable=false)
     * @Serializer\Exclude()
     * @Assert\GreaterThan(0)
     */
    private $timelimit = 0;

    /**
     * @var int
     * @ORM\Column(type="integer", name="memlimit",
     *     options={"comment"="Maximum memory available (in kB) for this problem",
     *              "unsigned"=true,"default":"NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     * @Assert\GreaterThan(0)
     */
    private $memlimit;

    /**
     * @var int
     * @ORM\Column(type="integer", name="outputlimit",
     *     options={"comment"="Maximum output size (in kB) for this problem",
     *              "unsigned"=true,"default":"NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     * @Assert\GreaterThan(0)
     */
    private $outputlimit;


    /**
     * @var string
     * @ORM\Column(type="string", name="special_run", length=32, options={"comment"="Script to run submissions for this problem","default"="NULL"}, nullable=true)
     * @Serializer\Exclude()
     */
    private $special_run;

    /**
     * @var string
     * @ORM\Column(type="string", name="special_compare", length=32, options={"comment"="Script to compare problem and jury output for this problem","default"="NULL"}, nullable=true)
     * @Serializer\Exclude()
     */
    private $special_compare;

    /**
     * @var string
     * @ORM\Column(type="string", name="special_compare_args", length=255,
     *     options={"comment"="Optional arguments to special_compare script","default"="NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $special_compare_args;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="combined_run_compare",
     *     options={"comment"="Use the exit code of the run script to compute the verdict",
     *              "default":"0"},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $combined_run_compare = false;

    /**
     * @var resource
     * @ORM\Column(type="blob", name="problemtext",
     *     options={"comment"="Problem text in HTML/PDF/ASCII","default":"NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $problemtext;

    /**
     * @var UploadedFile|null
     * @Assert\File()
     * @Serializer\Exclude()
     */
    private $problemtextFile;

    /**
     * @var bool
     * @Serializer\Exclude()
     */
    private $clearProblemtext = false;

    /**
     * @var string
     * @ORM\Column(type="string", length=4, name="problemtext_type",
     *     options={"comment"="File type of problem text","default"="NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $problemtext_type;

    /**
     * @ORM\OneToMany(targetEntity="Submission", mappedBy="problem")
     * @Serializer\Exclude()
     */
    private $submissions;

    /**
     * @ORM\OneToMany(targetEntity="Clarification", mappedBy="problem")
     * @Serializer\Exclude()
     */
    private $clarifications;

    /**
     * @ORM\OneToMany(targetEntity="ContestProblem", mappedBy="problem")
     * @Serializer\Exclude()
     */
    private $contest_problems;

    /**
     * @ORM\ManyToOne(targetEntity="Executable", inversedBy="problems_compare")
     * @ORM\JoinColumn(name="special_compare", referencedColumnName="execid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private $compare_executable;

    /**
     * @ORM\ManyToOne(targetEntity="Executable", inversedBy="problems_run")
     * @ORM\JoinColumn(name="special_run", referencedColumnName="execid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private $run_executable;

    /**
     * @ORM\OneToMany(targetEntity="Testcase", mappedBy="problem")
     * @Serializer\Exclude()
     */
    private $testcases;

    /**
     * Set probid
     *
     * @param integer $probId
     *
     * @return Problem
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
     * Set externalid
     *
     * @param string $externalid
     *
     * @return Problem
     */
    public function setExternalid($externalid)
    {
        $this->externalid = $externalid;

        return $this;
    }

    /**
     * Get externalid
     *
     * @return string
     */
    public function getExternalid()
    {
        return $this->externalid;
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
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("time_limit")
     * @Serializer\Type("float")
     */
    public function getTimelimit()
    {
        return Utils::roundedFloat($this->timelimit);
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
     * Set combinedRunCompare
     *
     * @param boolean $combinedRunCompare
     *
     * @return Problem
     */
    public function setCombinedRunCompare($combinedRunCompare)
    {
        $this->combined_run_compare = $combinedRunCompare;

        return $this;
    }

    /**
     * Get combinedRunCompare
     *
     * @return boolean
     */
    public function getCombinedRunCompare()
    {
        return $this->combined_run_compare;
    }

    /**
     * Set problemtext
     *
     * @param resource|string $problemtext
     *
     * @return Problem
     */
    public function setProblemtext($problemtext)
    {
        $this->problemtext = $problemtext;

        return $this;
    }

    /**
     * @param UploadedFile|null $problemtextFile
     * @return Problem
     */
    public function setProblemtextFile($problemtextFile)
    {
        $this->problemtextFile = $problemtextFile;
        // Clear the problem text to make sure the entity is modified
        $this->problemtext = '';

        return $this;
    }

    /**
     * @param bool $clearProblemtext
     * @return Problem
     */
    public function setClearProblemtext(bool $clearProblemtext)
    {
        $this->clearProblemtext = $clearProblemtext;
        $this->problemtext = null;

        return $this;
    }

    /**
     * Get problemtext
     *
     * @return resource|string
     */
    public function getProblemtext()
    {
        return $this->problemtext;
    }

    /**
     * @return UploadedFile|null
     */
    public function getProblemtextFile()
    {
        return $this->problemtextFile;
    }

    /**
     * @return bool
     */
    public function isClearProblemtext(): bool
    {
        return $this->clearProblemtext;
    }

    /**
     * Get whether this problem has a problem text
     * @return bool
     */
    public function hasProblemtext()
    {
        if (is_string($this->problemtext)) {
            return !empty($this->problemtext);
        } elseif (is_resource($this->problemtext)) {
            return fstat($this->problemtext)['size'] > 0;
        } else {
            return false;
        }
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
     * @param \App\Entity\Executable $compareExecutable
     *
     * @return Problem
     */
    public function setCompareExecutable(\App\Entity\Executable $compareExecutable = null)
    {
        $this->compare_executable = $compareExecutable;

        return $this;
    }

    /**
     * Get compareExecutable
     *
     * @return \App\Entity\Executable
     */
    public function getCompareExecutable()
    {
        return $this->compare_executable;
    }

    /**
     * Set runExecutable
     *
     * @param \App\Entity\Executable $runExecutable
     *
     * @return Problem
     */
    public function setRunExecutable(\App\Entity\Executable $runExecutable = null)
    {
        $this->run_executable = $runExecutable;

        return $this;
    }

    /**
     * Get runExecutable
     *
     * @return \App\Entity\Executable
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
        $this->submissions = new ArrayCollection();
        $this->clarifications = new ArrayCollection();
        $this->contest_problems = new ArrayCollection();
    }

    /**
     * Add testcase
     *
     * @param \App\Entity\Testcase $testcase
     *
     * @return Problem
     */
    public function addTestcase(\App\Entity\Testcase $testcase)
    {
        $this->testcases[] = $testcase;

        return $this;
    }

    /**
     * Remove testcase
     *
     * @param \App\Entity\Testcase $testcase
     */
    public function removeTestcase(\App\Entity\Testcase $testcase)
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
     * @param \App\Entity\ContestProblem $contestProblem
     *
     * @return Problem
     */
    public function addContestProblem(\App\Entity\ContestProblem $contestProblem)
    {
        $this->contest_problems[] = $contestProblem;

        return $this;
    }

    /**
     * Remove contestProblem
     *
     * @param \App\Entity\ContestProblem $contestProblem
     */
    public function removeContestProblem(
        \App\Entity\ContestProblem $contestProblem)
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
     * @param \App\Entity\Submission $submission
     *
     * @return Problem
     */
    public function addSubmission(\App\Entity\Submission $submission)
    {
        $this->submissions[] = $submission;

        return $this;
    }

    /**
     * Remove submission
     *
     * @param \App\Entity\Submission $submission
     */
    public function removeSubmission(\App\Entity\Submission $submission)
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
     * @param \App\Entity\Clarification $clarification
     *
     * @return Problem
     */
    public function addClarification(\App\Entity\Clarification $clarification)
    {
        $this->clarifications[] = $clarification;

        return $this;
    }

    /**
     * Remove clarification
     *
     * @param \App\Entity\Clarification $clarification
     */
    public function removeClarification(\App\Entity\Clarification $clarification)
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
     * @ORM\PrePersist()
     * @ORM\PreUpdate()
     * @throws \Exception
     */
    public function processProblemText()
    {
        if ($this->isClearProblemtext()) {
            $this
                ->setProblemtext(null)
                ->setProblemtextType(null);
        } elseif ($this->getProblemtextFile()) {
            $content         = file_get_contents($this->getProblemtextFile()->getRealPath());
            $clientName      = $this->getProblemtextFile()->getClientOriginalName();
            $problemTextType = null;

            if (strrpos($clientName, '.') !== false) {
                $ext = substr($clientName, strrpos($clientName, '.') + 1);
                if (in_array($ext, ['txt', 'html', 'pdf'])) {
                    $problemTextType = $ext;
                }
            }
            if (!isset($problemTextType)) {
                $finfo = finfo_open(FILEINFO_MIME);

                list($type) = explode('; ', finfo_file($finfo, $this->getProblemtextFile()->getRealPath()));

                finfo_close($finfo);

                switch ($type) {
                    case 'application/pdf':
                        $problemTextType = 'pdf';
                        break;
                    case 'text/html':
                        $problemTextType = 'html';
                        break;
                    case 'text/plain':
                        $problemTextType = 'txt';
                        break;
                }
            }

            if (!isset($problemTextType)) {
                throw new \Exception('Problem statement has unknown file type.');
            }

            $this
                ->setProblemtext($content)
                ->setProblemtextType($problemTextType);
        }
    }
}
