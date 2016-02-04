<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Judgehost
 *
 * @ORM\Table(name="judgehost", indexes={@ORM\Index(name="restrictionid", columns={"restrictionid"})})
 * @ORM\Entity
 */
class Judgehost
{
    /**
     * @var string
     *
     * @ORM\Column(name="hostname", type="string", length=50)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $hostname;

    /**
     * @var boolean
     *
     * @ORM\Column(name="active", type="boolean", nullable=false)
     */
    private $active;

    /**
     * @var string
     *
     * @ORM\Column(name="polltime", type="decimal", precision=32, scale=9, nullable=true)
     */
    private $pollTime;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Judging", mappedBy="judgehost")
     */
    private $judgings;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Submission", mappedBy="judgehost")
     */
    private $submissions;

    /**
     * @var \DOMjudge\MainBundle\Entity\JudgehostRestriction
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\JudgehostRestriction")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="restrictionid", referencedColumnName="restrictionid")
     * })
     */
    private $restriction;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->judgings = new \Doctrine\Common\Collections\ArrayCollection();
        $this->submissions = new \Doctrine\Common\Collections\ArrayCollection();
    }

}
