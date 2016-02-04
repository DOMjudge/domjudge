<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Rejudging
 *
 * @ORM\Table(name="rejudging", indexes={@ORM\Index(name="userid_start", columns={"userid_start"}), @ORM\Index(name="userid_finish", columns={"userid_finish"})})
 * @ORM\Entity
 */
class Rejudging
{
    /**
     * @var integer
     *
     * @ORM\Column(name="rejudgingid", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $rejudgingid;

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
     * @ORM\Column(name="reason", type="string", length=255, nullable=false)
     */
    private $reason;

    /**
     * @var boolean
     *
     * @ORM\Column(name="valid", type="boolean", nullable=false)
     */
    private $valid;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Judging", mappedBy="rejudging")
     */
    private $judgings;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Submission", mappedBy="rejudging")
     */
    private $submissions;

    /**
     * @var \DOMjudge\MainBundle\Entity\User
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\User")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="userid_start", referencedColumnName="userid")
     * })
     */
    private $startedByUser;

    /**
     * @var \DOMjudge\MainBundle\Entity\User
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\User")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="userid_finish", referencedColumnName="userid")
     * })
     */
    private $finishedByUser;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->judgings = new \Doctrine\Common\Collections\ArrayCollection();
        $this->submissions = new \Doctrine\Common\Collections\ArrayCollection();
    }

}
