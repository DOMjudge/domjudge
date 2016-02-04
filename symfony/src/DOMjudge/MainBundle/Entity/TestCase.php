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
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\judgingRun", mappedBy="testcase")
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

}
