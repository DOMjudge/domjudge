<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Submission
 *
 * @ORM\Table(name="submission", indexes={@ORM\Index(name="teamid", columns={"cid", "teamid"}), @ORM\Index(name="judgehost", columns={"cid", "judgehost"}), @ORM\Index(name="teamid_2", columns={"teamid"}), @ORM\Index(name="probid", columns={"probid"}), @ORM\Index(name="langid", columns={"langid"}), @ORM\Index(name="judgehost_2", columns={"judgehost"}), @ORM\Index(name="origsubmitid", columns={"origsubmitid"}), @ORM\Index(name="rejudgingid", columns={"rejudgingid"}), @ORM\Index(name="IDX_DB055AF34B30D9C4", columns={"cid"})})
 * @ORM\Entity
 */
class Submission
{
    /**
     * @var integer
     *
     * @ORM\Column(name="submitid", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $submitid;

    /**
     * @var string
     *
     * @ORM\Column(name="submittime", type="decimal", precision=32, scale=9, nullable=false)
     */
    private $submitTime;

    /**
     * @var boolean
     *
     * @ORM\Column(name="valid", type="boolean", nullable=false)
     */
    private $valid;

    /**
     * @var string
     *
     * @ORM\Column(name="expected_results", type="string", length=255, nullable=true)
     */
    private $expectedResults;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Balloon", mappedBy="submission")
     */
    private $balloons;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Event", mappedBy="submission")
     */
    private $events;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Judging", mappedBy="submission")
     */
    private $judgings;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Submission", mappedBy="originalSubmission")
     */
    private $followUpSubmissions;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\SubmissionFile", mappedBy="submission")
     */
    private $submissionFiles;

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
     * @var \DOMjudge\MainBundle\Entity\Team
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Team")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="teamid", referencedColumnName="teamid")
     * })
     */
    private $team;

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
     * @var \DOMjudge\MainBundle\Entity\Language
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Language")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="langid", referencedColumnName="langid")
     * })
     */
    private $language;

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
     * @var \DOMjudge\MainBundle\Entity\Submission
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Submission")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="origsubmitid", referencedColumnName="submitid")
     * })
     */
    private $originalSubmission;

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
     * Constructor
     */
    public function __construct()
    {
        $this->balloons = new \Doctrine\Common\Collections\ArrayCollection();
        $this->events = new \Doctrine\Common\Collections\ArrayCollection();
        $this->judgings = new \Doctrine\Common\Collections\ArrayCollection();
        $this->followUpSubmissions = new \Doctrine\Common\Collections\ArrayCollection();
        $this->submissionFiles = new \Doctrine\Common\Collections\ArrayCollection();
    }

}
