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


    /**
     * Get langid
     *
     * @return string 
     */
    public function getLangid()
    {
        return $this->langid;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return Language
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string 
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set extensions
     *
     * @param string $extensions
     * @return Language
     */
    public function setExtensions($extensions)
    {
        $this->extensions = $extensions;

        return $this;
    }

    /**
     * Get extensions
     *
     * @return string 
     */
    public function getExtensions()
    {
        return $this->extensions;
    }

    /**
     * Set allowSubmit
     *
     * @param boolean $allowSubmit
     * @return Language
     */
    public function setAllowSubmit($allowSubmit)
    {
        $this->allowSubmit = $allowSubmit;

        return $this;
    }

    /**
     * Get allowSubmit
     *
     * @return boolean 
     */
    public function getAllowSubmit()
    {
        return $this->allowSubmit;
    }

    /**
     * Set allowJudge
     *
     * @param boolean $allowJudge
     * @return Language
     */
    public function setAllowJudge($allowJudge)
    {
        $this->allowJudge = $allowJudge;

        return $this;
    }

    /**
     * Get allowJudge
     *
     * @return boolean 
     */
    public function getAllowJudge()
    {
        return $this->allowJudge;
    }

    /**
     * Set timeFactor
     *
     * @param float $timeFactor
     * @return Language
     */
    public function setTimeFactor($timeFactor)
    {
        $this->timeFactor = $timeFactor;

        return $this;
    }

    /**
     * Get timeFactor
     *
     * @return float 
     */
    public function getTimeFactor()
    {
        return $this->timeFactor;
    }

    /**
     * Set compileScript
     *
     * @param string $compileScript
     * @return Language
     */
    public function setCompileScript($compileScript)
    {
        $this->compileScript = $compileScript;

        return $this;
    }

    /**
     * Get compileScript
     *
     * @return string 
     */
    public function getCompileScript()
    {
        return $this->compileScript;
    }

    /**
     * Add events
     *
     * @param \DOMjudge\MainBundle\Entity\Event $events
     * @return Language
     */
    public function addEvent(\DOMjudge\MainBundle\Entity\Event $events)
    {
        $this->events[] = $events;

        return $this;
    }

    /**
     * Remove events
     *
     * @param \DOMjudge\MainBundle\Entity\Event $events
     */
    public function removeEvent(\DOMjudge\MainBundle\Entity\Event $events)
    {
        $this->events->removeElement($events);
    }

    /**
     * Get events
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * Add submissions
     *
     * @param \DOMjudge\MainBundle\Entity\Submission $submissions
     * @return Language
     */
    public function addSubmission(\DOMjudge\MainBundle\Entity\Submission $submissions)
    {
        $this->submissions[] = $submissions;

        return $this;
    }

    /**
     * Remove submissions
     *
     * @param \DOMjudge\MainBundle\Entity\Submission $submissions
     */
    public function removeSubmission(\DOMjudge\MainBundle\Entity\Submission $submissions)
    {
        $this->submissions->removeElement($submissions);
    }

    /**
     * Get submissions
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getSubmissions()
    {
        return $this->submissions;
    }
}
