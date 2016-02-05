<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * JudgingRun
 *
 * @ORM\Table(name="judging_run", uniqueConstraints={@ORM\UniqueConstraint(name="testcaseid", columns={"judgingid", "testcaseid"})}, indexes={@ORM\Index(name="judgingid", columns={"judgingid"}), @ORM\Index(name="testcaseid_2", columns={"testcaseid"})})
 * @ORM\Entity
 */
class JudgingRun
{
	/**
	 * @var integer
	 *
	 * @ORM\Column(name="runid", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 */
	private $runid;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="runresult", type="string", length=25, nullable=true)
	 */
	private $runResult;

	/**
	 * @var float
	 *
	 * @ORM\Column(name="runtime", type="float", precision=10, scale=0, nullable=true)
	 */
	private $runTime;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="output_run", type="blob", nullable=true)
	 */
	private $outputRun;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="output_diff", type="blob", nullable=true)
	 */
	private $outputDiff;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="output_error", type="blob", nullable=true)
	 */
	private $outputError;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="output_system", type="blob", nullable=true)
	 */
	private $outputSystem;

	/**
	 * @var \DOMjudge\MainBundle\Entity\Testcase
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Testcase", inversedBy="judgingRuns")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="testcaseid", referencedColumnName="testcaseid")
	 * })
	 */
	private $testcase;

	/**
	 * @var \DOMjudge\MainBundle\Entity\Judging
	 *
	 * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Judging", inversedBy="judgingRuns")
	 * @ORM\JoinColumns({
	 *   @ORM\JoinColumn(name="judgingid", referencedColumnName="judgingid")
	 * })
	 */
	private $judging;



	/**
	 * Get runid
	 *
	 * @return integer
	 */
	public function getRunid()
	{
		return $this->runid;
	}

	/**
	 * Set runResult
	 *
	 * @param string $runResult
	 * @return JudgingRun
	 */
	public function setRunResult($runResult)
	{
		$this->runResult = $runResult;

		return $this;
	}

	/**
	 * Get runResult
	 *
	 * @return string
	 */
	public function getRunResult()
	{
		return $this->runResult;
	}

	/**
	 * Set runTime
	 *
	 * @param float $runTime
	 * @return JudgingRun
	 */
	public function setRunTime($runTime)
	{
		$this->runTime = $runTime;

		return $this;
	}

	/**
	 * Get runTime
	 *
	 * @return float
	 */
	public function getRunTime()
	{
		return $this->runTime;
	}

	/**
	 * Set outputRun
	 *
	 * @param string $outputRun
	 * @return JudgingRun
	 */
	public function setOutputRun($outputRun)
	{
		$this->outputRun = $outputRun;

		return $this;
	}

	/**
	 * Get outputRun
	 *
	 * @return string
	 */
	public function getOutputRun()
	{
		return $this->outputRun;
	}

	/**
	 * Set outputDiff
	 *
	 * @param string $outputDiff
	 * @return JudgingRun
	 */
	public function setOutputDiff($outputDiff)
	{
		$this->outputDiff = $outputDiff;

		return $this;
	}

	/**
	 * Get outputDiff
	 *
	 * @return string
	 */
	public function getOutputDiff()
	{
		return $this->outputDiff;
	}

	/**
	 * Set outputError
	 *
	 * @param string $outputError
	 * @return JudgingRun
	 */
	public function setOutputError($outputError)
	{
		$this->outputError = $outputError;

		return $this;
	}

	/**
	 * Get outputError
	 *
	 * @return string
	 */
	public function getOutputError()
	{
		return $this->outputError;
	}

	/**
	 * Set outputSystem
	 *
	 * @param string $outputSystem
	 * @return JudgingRun
	 */
	public function setOutputSystem($outputSystem)
	{
		$this->outputSystem = $outputSystem;

		return $this;
	}

	/**
	 * Get outputSystem
	 *
	 * @return string
	 */
	public function getOutputSystem()
	{
		return $this->outputSystem;
	}

	/**
	 * Set testcase
	 *
	 * @param \DOMjudge\MainBundle\Entity\Testcase $testcase
	 * @return JudgingRun
	 */
	public function setTestcase(\DOMjudge\MainBundle\Entity\Testcase $testcase = null)
	{
		$this->testcase = $testcase;

		return $this;
	}

	/**
	 * Get testcase
	 *
	 * @return \DOMjudge\MainBundle\Entity\Testcase
	 */
	public function getTestcase()
	{
		return $this->testcase;
	}

	/**
	 * Set judging
	 *
	 * @param \DOMjudge\MainBundle\Entity\Judging $judging
	 * @return JudgingRun
	 */
	public function setJudging(\DOMjudge\MainBundle\Entity\Judging $judging = null)
	{
		$this->judging = $judging;

		return $this;
	}

	/**
	 * Get judging
	 *
	 * @return \DOMjudge\MainBundle\Entity\Judging
	 */
	public function getJudging()
	{
		return $this->judging;
	}
}
