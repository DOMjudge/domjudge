<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Judging
 *
 * @ORM\Table(name="judging", indexes={@ORM\Index(name="submitid", columns={"submitid"}), @ORM\Index(name="judgehost", columns={"judgehost"}), @ORM\Index(name="cid", columns={"cid"}), @ORM\Index(name="rejudgingid", columns={"rejudgingid"}), @ORM\Index(name="prevjudgingid", columns={"prevjudgingid"})})
 * @ORM\Entity
 */
class Judging
{
    /**
     * @var integer
     *
     * @ORM\Column(name="judgingid", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $judgingid;

    /**
     * @var string
     *
     * @ORM\Column(name="starttime", type="decimal", precision=32, scale=9, nullable=false)
     */
    private $startTime;

    /**
     * @var string
     *
     * @ORM\Column(name="endtime", type="decimal", precision=32, scale=9, nullable=true)
     */
    private $endTime;

    /**
     * @var string
     *
     * @ORM\Column(name="result", type="string", length=25, nullable=true)
     */
    private $result;

    /**
     * @var boolean
     *
     * @ORM\Column(name="verified", type="boolean", nullable=false)
     */
    private $verified;

    /**
     * @var string
     *
     * @ORM\Column(name="jury_member", type="string", length=25, nullable=true)
     */
    private $juryMember;

    /**
     * @var string
     *
     * @ORM\Column(name="verify_comment", type="string", length=255, nullable=true)
     */
    private $verifyComment;

    /**
     * @var boolean
     *
     * @ORM\Column(name="valid", type="boolean", nullable=false)
     */
    private $valid;

    /**
     * @var string
     *
     * @ORM\Column(name="output_compile", type="blob", nullable=true)
     */
    private $outputCompile;

    /**
     * @var boolean
     *
     * @ORM\Column(name="seen", type="boolean", nullable=false)
     */
    private $seen;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Event", mappedBy="judging")
     */
    private $events;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\judging", mappedBy="previousJudging")
     */
    private $nextJudgings;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\judgingRun", mappedBy="judging")
     */
    private $judgingRuns;

    /**
     * @var \DOMjudge\MainBundle\Entity\Contest
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Contest")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="cid", referencedColumnName="cid")
     * })
     */
    private $contest;

    /**
     * @var \DOMjudge\MainBundle\Entity\Submission
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Submission")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="submitid", referencedColumnName="submitid")
     * })
     */
    private $submission;

    /**
     * @var \DOMjudge\MainBundle\Entity\Judgehost
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Judgehost")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="judgehost", referencedColumnName="hostname")
     * })
     */
    private $judgehost;

    /**
     * @var \DOMjudge\MainBundle\Entity\Rejudging
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Rejudging")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="rejudgingid", referencedColumnName="rejudgingid")
     * })
     */
    private $rejudging;

    /**
     * @var \DOMjudge\MainBundle\Entity\Judging
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Judging")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="prevjudgingid", referencedColumnName="judgingid")
     * })
     */
    private $previousJudging;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->events = new \Doctrine\Common\Collections\ArrayCollection();
        $this->nextJudgings = new \Doctrine\Common\Collections\ArrayCollection();
        $this->judgingRuns = new \Doctrine\Common\Collections\ArrayCollection();
    }

}
