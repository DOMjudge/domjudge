<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Language
 *
 * @ORM\Table(name="language")
 * @ORM\Entity
 */
class Language
{
    /**
     * @var string
     *
     * @ORM\Column(name="langid", type="string", length=8)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $langid;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255, nullable=false)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="extensions", type="text", nullable=true)
     */
    private $extensions;

    /**
     * @var boolean
     *
     * @ORM\Column(name="allow_submit", type="boolean", nullable=false)
     */
    private $allowSubmit;

    /**
     * @var boolean
     *
     * @ORM\Column(name="allow_judge", type="boolean", nullable=false)
     */
    private $allowJudge;

    /**
     * @var float
     *
     * @ORM\Column(name="time_factor", type="float", precision=10, scale=0, nullable=false)
     */
    private $timeFactor;

    /**
     * @var string
     *
     * @ORM\Column(name="compile_script", type="string", length=32, nullable=true)
     */
    private $compileScript;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Event", mappedBy="language")
     */
    private $events;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="DOMjudge\MainBundle\Entity\Submission", mappedBy="language")
     */
    private $submissions;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->events = new \Doctrine\Common\Collections\ArrayCollection();
        $this->submissions = new \Doctrine\Common\Collections\ArrayCollection();
    }

}
