<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Balloon
 *
 * @ORM\Table(name="balloon", indexes={@ORM\Index(name="submitid", columns={"submitid"})})
 * @ORM\Entity
 */
class Balloon
{
    /**
     * @var integer
     *
     * @ORM\Column(name="balloonid", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $balloonid;

    /**
     * @var boolean
     *
     * @ORM\Column(name="done", type="boolean", nullable=false)
     */
    private $done;

    /**
     * @var \DOMjudge\MainBundle\Entity\Submission
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Submission")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="submitid", referencedColumnName="submitid")
     * })
     */
    private $submission;


}
