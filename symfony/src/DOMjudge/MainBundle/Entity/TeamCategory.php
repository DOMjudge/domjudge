<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * TeamCategory
 *
 * @ORM\Table(name="team_category", indexes={@ORM\Index(name="sortorder", columns={"sortorder"})})
 * @ORM\Entity
 */
class TeamCategory
{
    /**
     * @var integer
     *
     * @ORM\Column(name="categoryid", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $categoryid;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255, nullable=false)
     */
    private $name;

    /**
     * @var boolean
     *
     * @ORM\Column(name="sortorder", type="boolean", nullable=false)
     */
    private $sortOrder;

    /**
     * @var string
     *
     * @ORM\Column(name="color", type="string", length=25, nullable=true)
     */
    private $color;

    /**
     * @var boolean
     *
     * @ORM\Column(name="visible", type="boolean", nullable=false)
     */
    private $visible;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Team", mappedBy="category")
     */
    private $teams;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->teams = new \Doctrine\Common\Collections\ArrayCollection();
    }

}
