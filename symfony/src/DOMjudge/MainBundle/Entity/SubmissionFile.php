<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * SubmissionFile
 *
 * @ORM\Table(name="submission_file", uniqueConstraints={@ORM\UniqueConstraint(name="rank", columns={"submitid", "rank"}), @ORM\UniqueConstraint(name="filename", columns={"submitid", "filename"})}, indexes={@ORM\Index(name="submitid", columns={"submitid"})})
 * @ORM\Entity
 */
class SubmissionFile
{
    /**
     * @var integer
     *
     * @ORM\Column(name="submitfileid", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $submitfileid;

    /**
     * @var string
     *
     * @ORM\Column(name="sourcecode", type="blob", nullable=false)
     */
    private $sourceCode;

    /**
     * @var string
     *
     * @ORM\Column(name="filename", type="string", length=255, nullable=false)
     */
    private $filename;

    /**
     * @var integer
     *
     * @ORM\Column(name="rank", type="integer", nullable=false)
     */
    private $rank;

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
