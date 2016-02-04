<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Clarification
 *
 * @ORM\Table(name="clarification", indexes={@ORM\Index(name="respid", columns={"respid"}), @ORM\Index(name="probid", columns={"probid"}), @ORM\Index(name="cid", columns={"cid"}), @ORM\Index(name="cid_2", columns={"cid", "answered", "submittime"})})
 * @ORM\Entity
 */
class Clarification
{
    /**
     * @var integer
     *
     * @ORM\Column(name="clarid", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $clarid;

    /**
     * @var string
     *
     * @ORM\Column(name="submittime", type="decimal", precision=32, scale=9, nullable=false)
     */
    private $submitTime;

    /**
     * @var integer
     *
     * @ORM\Column(name="sender", type="integer", nullable=true)
     */
    private $sender;

    /**
     * @var integer
     *
     * @ORM\Column(name="recipient", type="integer", nullable=true)
     */
    private $recipient;

    /**
     * @var string
     *
     * @ORM\Column(name="jury_member", type="string", length=15, nullable=true)
     */
    private $juryMember;

    /**
     * @var string
     *
     * @ORM\Column(name="category", type="string", length=128, nullable=true)
     */
    private $category;

    /**
     * @var string
     *
     * @ORM\Column(name="body", type="text", nullable=false)
     */
    private $body;

    /**
     * @var boolean
     *
     * @ORM\Column(name="answered", type="boolean", nullable=false)
     */
    private $answered;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Clarification", mappedBy="inReplyTo")
     */
    private $responses;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Event", mappedBy="clarification")
     */
    private $events;

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
     *   @ORM\JoinColumn(name="respid", referencedColumnName="clarid")
     * })
     */
    private $inReplyTo;

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
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\ManyToMany(targetEntity="DOMjudge\MainBundle\Entity\Team", mappedBy="unreadClarifications")
     */
    private $unreadTeams;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->responses = new \Doctrine\Common\Collections\ArrayCollection();
        $this->events = new \Doctrine\Common\Collections\ArrayCollection();
        $this->unreadTeams = new \Doctrine\Common\Collections\ArrayCollection();
    }

}
