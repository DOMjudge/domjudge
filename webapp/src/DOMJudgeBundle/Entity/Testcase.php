<?php
namespace DOMJudgeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Stores testcases per problem
 * @ORM\Entity()
 * @ORM\Table(name="testcase", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class Testcase
{

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="testcaseid", options={"comment"="Unique ID"}, nullable=false)
     */
    private $testcaseid;

    /**
     * @var string
     * @ORM\Column(type="string", name="md5sum_input", length=32, options={"comment"="Checksum of input data"}, nullable=true)
     */
    private $md5sum_input;

    /**
     * @var string
     * @ORM\Column(type="string", name="md5sum_output", length=32, options={"comment"="Checksum of output data"}, nullable=true)
     */
    private $md5sum_output;

    /**
     * @var string
     * @ORM\Column(type="blob", name="input", options={"comment"="Input data"}, nullable=false)
     */
    private $input;

    /**
     * @var string
     * @ORM\Column(type="blob", name="output", options={"comment"="Output data"}, nullable=false)
     */
    private $output;

    /**
     * @var int
     * @ORM\Column(type="integer", name="probid", options={"comment"="Corresponding problem ID", "unsigned"=true}, nullable=false)
     */
    private $probid;

    /**
     * @var int
     * @ORM\Column(type="integer", name="rank", options={"comment"="Determines order of the testcases in judging", "unsigned"=true}, nullable=false)
     */
    private $rank;

    /**
     * @var string
     * @ORM\Column(type="blob", name="description", options={"comment"="Description of this testcase"}, nullable=true)
     */
    private $description;


    /**
     * @var string
     * @ORM\Column(type="blob", name="image", options={"comment"="A graphical representation of this testcase"}, nullable=true)
     */
    private $image;

    /**
     * @var string
     * @ORM\Column(type="blob", name="image_thumb", options={"comment"="Automatically created thumbnail of the image"}, nullable=true)
     */
    private $image_thumb;

    /**
     * @var string
     * @ORM\Column(type="string", name="image_type", length=32, options={"comment"="File type of the image and thumbnail"}, nullable=true)
     */
    private $image_type;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="sample", options={"comment"="Sample testcases that can be shared with teams"}, nullable=false)
     */
    private $sample = false;

    /**
     * @ORM\OneToMany(targetEntity="JudgingRun", mappedBy="testcase")
     */
    private $judging_runs;

    /**
     * @ORM\ManyToOne(targetEntity="Problem", inversedBy="testcases")
     * @ORM\JoinColumn(name="probid", referencedColumnName="probid")
     */
    private $problem;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->judging_runs = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Get testcaseid
     *
     * @return integer
     */
    public function getTestcaseid()
    {
        return $this->testcaseid;
    }

    /**
     * Set md5sumInput
     *
     * @param string $md5sumInput
     *
     * @return Testcase
     */
    public function setMd5sumInput($md5sumInput)
    {
        $this->md5sum_input = $md5sumInput;

        return $this;
    }

    /**
     * Get md5sumInput
     *
     * @return string
     */
    public function getMd5sumInput()
    {
        return $this->md5sum_input;
    }

    /**
     * Set md5sumOutput
     *
     * @param string $md5sumOutput
     *
     * @return Testcase
     */
    public function setMd5sumOutput($md5sumOutput)
    {
        $this->md5sum_output = $md5sumOutput;

        return $this;
    }

    /**
     * Get md5sumOutput
     *
     * @return string
     */
    public function getMd5sumOutput()
    {
        return $this->md5sum_output;
    }

    /**
     * Set input
     *
     * @param string $input
     *
     * @return Testcase
     */
    public function setInput($input)
    {
        $this->input = $input;

        return $this;
    }

    /**
     * Get input
     *
     * @return string
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * Set output
     *
     * @param string $output
     *
     * @return Testcase
     */
    public function setOutput($output)
    {
        $this->output = $output;

        return $this;
    }

    /**
     * Get output
     *
     * @return string
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Set probid
     *
     * @param integer $probid
     *
     * @return Testcase
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
     * Set rank
     *
     * @param integer $rank
     *
     * @return Testcase
     */
    public function setRank($rank)
    {
        $this->rank = $rank;

        return $this;
    }

    /**
     * Get rank
     *
     * @return integer
     */
    public function getRank()
    {
        return $this->rank;
    }

    /**
     * Set description
     *
     * @param string $description
     *
     * @return Testcase
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set image
     *
     * @param string $image
     *
     * @return Testcase
     */
    public function setImage($image)
    {
        $this->image = $image;

        return $this;
    }

    /**
     * Get image
     *
     * @return string
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * Set imageThumb
     *
     * @param string $imageThumb
     *
     * @return Testcase
     */
    public function setImageThumb($imageThumb)
    {
        $this->image_thumb = $imageThumb;

        return $this;
    }

    /**
     * Get imageThumb
     *
     * @return string
     */
    public function getImageThumb()
    {
        return $this->image_thumb;
    }

    /**
     * Set imageType
     *
     * @param string $imageType
     *
     * @return Testcase
     */
    public function setImageType($imageType)
    {
        $this->image_type = $imageType;

        return $this;
    }

    /**
     * Get imageType
     *
     * @return string
     */
    public function getImageType()
    {
        return $this->image_type;
    }

    /**
     * Set sample
     *
     * @param boolean $sample
     *
     * @return Testcase
     */
    public function setSample($sample)
    {
        $this->sample = $sample;

        return $this;
    }

    /**
     * Get sample
     *
     * @return boolean
     */
    public function getSample()
    {
        return $this->sample;
    }

    /**
     * Add judgingRun
     *
     * @param \DOMJudgeBundle\Entity\JudgingRun $judgingRun
     *
     * @return Testcase
     */
    public function addJudgingRun(\DOMJudgeBundle\Entity\JudgingRun $judgingRun)
    {
        $this->judging_runs[] = $judgingRun;

        return $this;
    }

    /**
     * Remove judgingRun
     *
     * @param \DOMJudgeBundle\Entity\JudgingRun $judgingRun
     */
    public function removeJudgingRun(\DOMJudgeBundle\Entity\JudgingRun $judgingRun)
    {
        $this->judging_runs->removeElement($judgingRun);
    }

    /**
     * Get judgingRuns
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getJudgingRuns()
    {
        return $this->judging_runs;
    }

    /**
     * Set problem
     *
     * @param \DOMJudgeBundle\Entity\Problem $problem
     *
     * @return Testcase
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
}
