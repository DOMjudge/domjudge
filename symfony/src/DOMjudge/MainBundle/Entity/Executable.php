<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Executable
 *
 * @ORM\Table(name="executable")
 * @ORM\Entity
 */
class Executable
{
    /**
     * @var string
     *
     * @ORM\Column(name="execid", type="string", length=32)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $execid;

    /**
     * @var string
     *
     * @ORM\Column(name="md5sum", type="string", length=32, nullable=true)
     */
    private $md5sum;

    /**
     * @var string
     *
     * @ORM\Column(name="zipfile", type="blob", nullable=true)
     */
    private $zipfile;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=255, nullable=true)
     */
    private $description;

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", length=8, nullable=false)
     */
    private $type;


}
