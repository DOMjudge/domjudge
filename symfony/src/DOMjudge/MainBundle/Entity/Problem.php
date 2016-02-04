<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Problem
 *
 * @ORM\Table(name="problem")
 * @ORM\Entity
 */
class Problem
{
    /**
     * @var integer
     *
     * @ORM\Column(name="probid", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $probid;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255, nullable=false)
     */
    private $name;

    /**
     * @var integer
     *
     * @ORM\Column(name="timelimit", type="integer", nullable=false)
     */
    private $timeLimit;

    /**
     * @var integer
     *
     * @ORM\Column(name="memlimit", type="integer", nullable=true)
     */
    private $memLimit;

    /**
     * @var integer
     *
     * @ORM\Column(name="outputlimit", type="integer", nullable=true)
     */
    private $outputLimit;

    /**
     * @var string
     *
     * @ORM\Column(name="special_run", type="string", length=32, nullable=true)
     */
    private $specialRun;

    /**
     * @var string
     *
     * @ORM\Column(name="special_compare", type="string", length=32, nullable=true)
     */
    private $specialCompare;

    /**
     * @var string
     *
     * @ORM\Column(name="special_compare_args", type="string", length=255, nullable=true)
     */
    private $specialCompareArgs;

    /**
     * @var string
     *
     * @ORM\Column(name="problemtext", type="blob", nullable=true)
     */
    private $problemText;

    /**
     * @var string
     *
     * @ORM\Column(name="problemtext_type", type="string", length=4, nullable=true)
     */
    private $problemTextType;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Clarification", mappedBy="problem")
     */
    private $clarifications;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Event", mappedBy="problem")
     */
    private $events;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Submission", mappedBy="problem")
     */
    private $submissions;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\TestCase", mappedBy="problem")
     */
    private $testcases;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\ManyToMany(targetEntity="DOMjudge\MainBundle\Entity\Contest", mappedBy="problems")
     */
    private $contests;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->clarifications = new \Doctrine\Common\Collections\ArrayCollection();
        $this->events = new \Doctrine\Common\Collections\ArrayCollection();
        $this->submissions = new \Doctrine\Common\Collections\ArrayCollection();
        $this->testcases = new \Doctrine\Common\Collections\ArrayCollection();
        $this->contests = new \Doctrine\Common\Collections\ArrayCollection();
    }

}
