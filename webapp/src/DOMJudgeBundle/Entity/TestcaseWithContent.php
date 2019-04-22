<?php declare(strict_types=1);

namespace DOMJudgeBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Contents of a testcase
 *
 * This is a seperate class with a OneToOne relationship with Testcase so we can load it separately
 * @ORM\Entity()
 * @ORM\Table(name="testcase", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class TestcaseWithContent
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
     * @ORM\Column(type="string", name="md5sum_input", length=32, options={"comment"="Checksum of input data"},
     *                            nullable=true)
     */
    private $md5sum_input;

    /**
     * @var string
     * @ORM\Column(type="string", name="md5sum_output", length=32, options={"comment"="Checksum of output data"},
     *                            nullable=true)
     */
    private $md5sum_output;

    /**
     * @var int
     * @ORM\Column(type="integer", name="probid", options={"comment"="Corresponding problem ID", "unsigned"=true},
     *                             nullable=false)
     */
    private $probid;

    /**
     * @var int
     * @ORM\Column(type="integer", name="rank", options={"comment"="Determines order of the testcases in judging",
     *                             "unsigned"=true}, nullable=false)
     */
    private $rank;

    /**
     * @var resource
     * @ORM\Column(type="blob", name="description", options={"comment"="Description of this testcase"}, nullable=true)
     * @Serializer\Exclude()
     */
    private $description;

    private $description_as_string = null;

    /**
     * @var string
     * @ORM\Column(type="string", name="image_type", length=32, options={"comment"="File type of the image and
     *                            thumbnail"}, nullable=true)
     * @Serializer\Exclude()
     */
    private $image_type;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="sample", options={"comment"="Sample testcases that can be shared with teams"},
     *                             nullable=false)
     * @Serializer\Exclude()
     */
    private $sample = false;

    /**
     * @var string
     * @ORM\Column(type="string", name="input", options={"comment"="Input data"}, nullable=false)
     */
    private $input;

    /**
     * @var string
     * @ORM\Column(type="string", name="output", options={"comment"="Output data"}, nullable=false)
     */
    private $output;

    /**
     * @var string
     * @ORM\Column(type="string", name="image", options={"comment"="A graphical representation of this testcase"},
     *                            nullable=true)
     */
    private $image;

    /**
     * @var string
     * @ORM\Column(type="string", name="image_thumb", options={"comment"="Automatically created thumbnail of the image"}, nullable=true)
     */
    private $image_thumb;

    /**
     * @ORM\OneToMany(targetEntity="JudgingRun", mappedBy="testcase")
     * @Serializer\Exclude()
     */
    private $judging_runs;

    /**
     * @ORM\OneToMany(targetEntity="ExternalRun", mappedBy="testcase")
     * @Serializer\Exclude()
     */
    private $external_runs;

    /**
     * @ORM\ManyToOne(targetEntity="Problem", inversedBy="testcases")
     * @ORM\JoinColumn(name="probid", referencedColumnName="probid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private $problem;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->judging_runs = new ArrayCollection();
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
     * @return TestcaseWithContent
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
     * @return TestcaseWithContent
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
     * Set probid
     *
     * @param integer $probid
     *
     * @return TestcaseWithContent
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
     * @return TestcaseWithContent
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
     * @param resource|string $description
     *
     * @return TestcaseWithContent
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @param bool $asString
     * @return resource|string|null
     */
    public function getDescription(bool $asString = false)
    {
        if ($asString && $this->description !== null) {
            if ($this->description_as_string === null) {
                $this->description_as_string = stream_get_contents($this->description);
            }
            return $this->description_as_string;
        }
        return $this->description;
    }

    /**
     * Set imageType
     *
     * @param string $imageType
     *
     * @return TestcaseWithContent
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
     * @return TestcaseWithContent
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
     * Set input
     *
     * @param string $input
     *
     * @return TestcaseWithContent
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
     * @return TestcaseWithContent
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
     * Set image
     *
     * @param string $image
     *
     * @return TestcaseWithContent
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
     * @return TestcaseWithContent
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
     * Add judgingRun
     *
     * @param JudgingRun $judgingRun
     *
     * @return TestcaseWithContent
     */
    public function addJudgingRun(JudgingRun $judgingRun)
    {
        $this->judging_runs[] = $judgingRun;

        return $this;
    }

    /**
     * Remove judgingRun
     *
     * @param JudgingRun $judgingRun
     */
    public function removeJudgingRun(JudgingRun $judgingRun)
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
     * Gets the first judging run for this testcase.
     *
     * This is useful when this testcase is joined to a single run to get code completion in Twig templates
     *
     * @return JudgingRun|null
     */
    public function getFirstJudgingRun()
    {
        return $this->judging_runs->first() ?: null;
    }

    /**
     * Set problem
     *
     * @param Problem $problem
     *
     * @return TestcaseWithContent
     */
    public function setProblem(Problem $problem = null)
    {
        $this->problem = $problem;

        return $this;
    }

    /**
     * Get problem
     *
     * @return Problem
     */
    public function getProblem()
    {
        return $this->problem;
    }

    /**
     * Add externalRun
     *
     * @param ExternalRun $externalRun
     *
     * @return Testcase
     */
    public function addExternalRun(ExternalRun $externalRun)
    {
        $this->external_runs[] = $externalRun;

        return $this;
    }

    /**
     * Remove externalRun
     *
     * @param ExternalRun $externalRun
     */
    public function removeExternalRun(ExternalRun $externalRun)
    {
        $this->external_runs->removeElement($externalRun);
    }

    /**
     * Get externalRuns
     *
     * @return Collection
     */
    public function getExternalRuns()
    {
        return $this->external_runs;
    }
}
