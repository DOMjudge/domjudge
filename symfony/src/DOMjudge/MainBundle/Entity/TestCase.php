<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * TestCase
 *
 * @ORM\Table(name="testcase", uniqueConstraints={@ORM\UniqueConstraint(name="rank", columns={"probid", "rank"})}, indexes={@ORM\Index(name="probid", columns={"probid"})})
 * @ORM\Entity
 */
class TestCase
{
	/**
	 * @var integer
	 *
	 * @ORM\Column(name="testcaseid", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 */
	private $testcaseid;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="md5sum_input", type="string", length=32, nullable=true)
	 */
	private $md5sumInput;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="md5sum_output", type="string", length=32, nullable=true)
	 */
	private $md5sumOutput;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="input", type="blob", nullable=true)
	 */
	private $input;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="output", type="blob", nullable=true)
	 */
	private $output;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="rank", type="integer", nullable=false)
	 */
	private $rank;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="description", type="string", length=255, nullable=true)
	 */
	private $description;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="image", type="blob", nullable=true)
	 */
	private $image;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="image_thumb", type="blob", nullable=true)
	 */
	private $imageThumb;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="image_type", type="string", length=4, nullable=true)
	 */
	private $imageType;

	/**
	 * @var boolean
	 *
	 * @ORM\Column(name="sample", type="boolean", nullable=false)
	 */
	private $sample;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 *
	 * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\JudgingRun", mappedBy="testcase")
	 */
	private $judgingRuns;

	/**
	 * @var \DOMjudge\MainBundle\Entity\Problem
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Problem")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="probid", referencedColumnName="probid")
	 * })
	 */
	private $problem;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->judgingRuns = new \Doctrine\Common\Collections\ArrayCollection();
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
	 * @return TestCase
	 */
	public function setMd5sumInput($md5sumInput)
	{
		$this->md5sumInput = $md5sumInput;

		return $this;
	}

	/**
	 * Get md5sumInput
	 *
	 * @return string
	 */
	public function getMd5sumInput()
	{
		return $this->md5sumInput;
	}

	/**
	 * Set md5sumOutput
	 *
	 * @param string $md5sumOutput
	 * @return TestCase
	 */
	public function setMd5sumOutput($md5sumOutput)
	{
		$this->md5sumOutput = $md5sumOutput;

		return $this;
	}

	/**
	 * Get md5sumOutput
	 *
	 * @return string
	 */
	public function getMd5sumOutput()
	{
		return $this->md5sumOutput;
	}

	/**
	 * Set input
	 *
	 * @param string $input
	 * @return TestCase
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
	 * @return TestCase
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
	 * Set rank
	 *
	 * @param integer $rank
	 * @return TestCase
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
	 * @return TestCase
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
	 * @return TestCase
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
	 * @return TestCase
	 */
	public function setImageThumb($imageThumb)
	{
		$this->imageThumb = $imageThumb;

		return $this;
	}

	/**
	 * Get imageThumb
	 *
	 * @return string
	 */
	public function getImageThumb()
	{
		return $this->imageThumb;
	}

	/**
	 * Set imageType
	 *
	 * @param string $imageType
	 * @return TestCase
	 */
	public function setImageType($imageType)
	{
		$this->imageType = $imageType;

		return $this;
	}

	/**
	 * Get imageType
	 *
	 * @return string
	 */
	public function getImageType()
	{
		return $this->imageType;
	}

	/**
	 * Set sample
	 *
	 * @param boolean $sample
	 * @return TestCase
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
	 * Add JudgingRuns
	 *
	 * @param \DOMjudge\MainBundle\Entity\JudgingRun $judgingRuns
	 * @return TestCase
	 */
	public function addJudgingRun(\DOMjudge\MainBundle\Entity\judgingRun $judgingRuns)
	{
		$this->judgingRuns[] = $judgingRuns;

		return $this;
	}

	/**
	 * Remove JudgingRuns
	 *
	 * @param \DOMjudge\MainBundle\Entity\JudgingRun $judgingRuns
	 */
	public function removeJudgingRun(\DOMjudge\MainBundle\Entity\judgingRun $judgingRuns)
	{
		$this->judgingRuns->removeElement($judgingRuns);
	}

	/**
	 * Get JudgingRuns
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getJudgingRuns()
	{
		return $this->judgingRuns;
	}

	/**
	 * Set problem
	 *
	 * @param \DOMjudge\MainBundle\Entity\Problem $problem
	 * @return TestCase
	 */
	public function setProblem(\DOMjudge\MainBundle\Entity\Problem $problem = null)
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
