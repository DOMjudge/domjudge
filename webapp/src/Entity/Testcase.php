<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Stores testcases per problem
 * @ORM\Entity()
 * @ORM\Table(
 *     name="testcase",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Stores testcases per problem"},
 *     indexes={
 *         @ORM\Index(name="probid", columns={"probid"}),
 *         @ORM\Index(name="sample", columns={"sample"})
 *     },
 *     uniqueConstraints={@ORM\UniqueConstraint(name="rank", columns={"probid","rank"})})
 */
class Testcase
{

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="testcaseid", length=4,
     *     options={"comment"="Testcase ID","unsigned"=true},
     *     nullable=false)
     */
    private $testcaseid;

    /**
     * @var string
     * @ORM\Column(type="string", name="md5sum_input", length=32,
     *     options={"comment"="Checksum of input data","default"="NULL","fixed"=true},
     *     nullable=true)
     */
    private $md5sum_input;

    /**
     * @var string
     * @ORM\Column(type="string", name="md5sum_output", length=32,
     *     options={"comment"="Checksum of output data","default"="NULL","fixed"=true},
     *     nullable=true)
     */
    private $md5sum_output;

    /**
     * @var int
     * @ORM\Column(type="integer", name="probid", length=4,
     *     options={"comment"="Corresponding problem ID", "unsigned"=true},
     *     nullable=false)
     */
    private $probid;

    /**
     * @var int
     * @ORM\Column(type="integer", name="rank", length=4,
     *     options={"comment"="Determines order of the testcases in judging",
     *              "unsigned"=true},
     *     nullable=false)
     */
    private $rank;

    /**
     * @var resource
     * @ORM\Column(type="blob", length=4294967295, name="description",
     *     options={"comment"="Description of this testcase","default"="NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $description;

    private $description_as_string = null;

    /**
     * @var string
     * @ORM\Column(type="string", name="orig_input_filename", length=255,
     *     options={"comment"="Original basename of the input file.","default"=NULL},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $orig_input_filename;

    /**
     * @var string
     * @ORM\Column(type="string", name="image_type", length=4,
     *     options={"comment"="File type of the image and thumbnail","default"="NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $image_type;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="sample",
     *     options={"comment"="Sample testcases that can be shared with teams",
     *              "default"="0"},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $sample = false;

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
     * We use a OneToMany instead of a OneToOne here, because otherwise this
     * relation will always be loaded. See the commit message of commit
     * 9e421f96691ec67ed62767fe465a6d8751edd884 for a more elaborate explanation
     *
     * @var TestcaseContent[]|ArrayCollection
     * @ORM\OneToMany(targetEntity="TestcaseContent", mappedBy="testcase", cascade={"persist"}, orphanRemoval=true)
     * @Serializer\Exclude()
     */
    private $content;

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
        $this->external_runs = new ArrayCollection();
        $this->content = new ArrayCollection();
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
     * @param resource|string $description
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
     * Set original input filename
     *
     * @param string $origInputFilename
     *
     * @return Testcase
     */
    public function setOrigInputFilename($origInputFilename)
    {
        $this->orig_input_filename = $origInputFilename;

        return $this;
    }

    /**
     * Get original input filename
     *
     * @return string
     */
    public function getOrigInputFilename()
    {
        return $this->orig_input_filename;
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
     * @param \App\Entity\JudgingRun $judgingRun
     *
     * @return Testcase
     */
    public function addJudgingRun(\App\Entity\JudgingRun $judgingRun)
    {
        $this->judging_runs[] = $judgingRun;

        return $this;
    }

    /**
     * Remove judgingRun
     *
     * @param \App\Entity\JudgingRun $judgingRun
     */
    public function removeJudgingRun(\App\Entity\JudgingRun $judgingRun)
    {
        $this->judging_runs->removeElement($judgingRun);
    }

    /**
     * Get judgingRuns
     *
     * @return Collection
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
     * Gets the first external run for this testcase.
     *
     * This is useful when this testcase is joined to a single external run to get code completion in Twig templates
     *
     * @return ExternalRun|null
     */
    public function getFirstExternalRun()
    {
        return $this->external_runs->first() ?: null;
    }

    /**
     * Set content
     *
     * @param TestcaseContent $content
     *
     * @return Testcase
     */
    public function setContent(?TestcaseContent $content)
    {
        $this->content->clear();
        $this->content->add($content);
        $content->setTestcase($this);

        return $this;
    }

    /**
     * Get content
     *
     * @return TestcaseContent
     */
    public function getContent(): ?TestcaseContent
    {
        return $this->content->first() ?: null;
    }

    /**
     * Set problem
     *
     * @param \App\Entity\Problem $problem
     *
     * @return Testcase
     */
    public function setProblem(\App\Entity\Problem $problem = null)
    {
        $this->problem = $problem;

        return $this;
    }

    /**
     * Get problem
     *
     * @return \App\Entity\Problem
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
