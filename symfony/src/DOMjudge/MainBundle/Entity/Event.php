<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Event
 *
 * @ORM\Table(name="event", indexes={@ORM\Index(name="cid", columns={"cid"}), @ORM\Index(name="clarid", columns={"clarid"}), @ORM\Index(name="langid", columns={"langid"}), @ORM\Index(name="probid", columns={"probid"}), @ORM\Index(name="submitid", columns={"submitid"}), @ORM\Index(name="judgingid", columns={"judgingid"}), @ORM\Index(name="teamid", columns={"teamid"})})
 * @ORM\Entity
 */
class Event
{
    /**
     * @var integer
     *
     * @ORM\Column(name="eventid", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $eventid;

    /**
     * @var string
     *
     * @ORM\Column(name="eventtime", type="decimal", precision=32, scale=9, nullable=false)
     */
    private $eventTime;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text", nullable=false)
     */
    private $description;

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
     * @var \DOMjudge\MainBundle\Entity\Clarification
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Clarification")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="clarid", referencedColumnName="clarid")
     * })
     */
    private $clarification;

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
     * @var \DOMjudge\MainBundle\Entity\Problem
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Problem")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="probid", referencedColumnName="probid")
     * })
     */
    private $problem;

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
     * @var \DOMjudge\MainBundle\Entity\Judging
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Judging")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="judgingid", referencedColumnName="judgingid")
     * })
     */
    private $judging;

    /**
     * @var \DOMjudge\MainBundle\Entity\Team
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Team")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="teamid", referencedColumnName="teamid")
     * })
     */
    private $team;


}
