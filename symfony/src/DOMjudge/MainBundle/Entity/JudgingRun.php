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
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Testcase")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="testcaseid", referencedColumnName="testcaseid")
     * })
     */
    private $testcase;

    /**
     * @var \DOMjudge\MainBundle\Entity\Judging
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Judging")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="judgingid", referencedColumnName="judgingid")
     * })
     */
    private $judging;


}
