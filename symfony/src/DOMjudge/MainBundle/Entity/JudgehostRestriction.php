<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * JudgehostRestriction
 *
 * @ORM\Table(name="judgehost_restriction")
 * @ORM\Entity
 */
class JudgehostRestriction
{
    /**
     * @var integer
     *
     * @ORM\Column(name="restrictionid", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $restrictionid;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255, nullable=false)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="restrictions", type="text", nullable=true)
     */
    private $restrictions;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\judgehost", mappedBy="restriction")
     */
    private $judgehosts;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->judgehosts = new \Doctrine\Common\Collections\ArrayCollection();
    }

}
