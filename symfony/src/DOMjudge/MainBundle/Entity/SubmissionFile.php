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



    /**
     * Get submitfileid
     *
     * @return integer 
     */
    public function getSubmitfileid()
    {
        return $this->submitfileid;
    }

    /**
     * Set sourceCode
     *
     * @param string $sourceCode
     * @return SubmissionFile
     */
    public function setSourceCode($sourceCode)
    {
        $this->sourceCode = $sourceCode;

        return $this;
    }

    /**
     * Get sourceCode
     *
     * @return string 
     */
    public function getSourceCode()
    {
        return $this->sourceCode;
    }

    /**
     * Set filename
     *
     * @param string $filename
     * @return SubmissionFile
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * Get filename
     *
     * @return string 
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Set rank
     *
     * @param integer $rank
     * @return SubmissionFile
     */
    public function setRank($rank)
    {
        $this->rank = $rank;

        return $this;
    }

    /**
     * Get rank
     *
     * @return integer 
     */
    public function getRank()
    {
        return $this->rank;
    }

    /**
     * Set submission
     *
     * @param \DOMjudge\MainBundle\Entity\Submission $submission
     * @return SubmissionFile
     */
    public function setSubmission(\DOMjudge\MainBundle\Entity\Submission $submission = null)
    {
        $this->submission = $submission;

        return $this;
    }

    /**
     * Get submission
     *
     * @return \DOMjudge\MainBundle\Entity\Submission 
     */
    public function getSubmission()
    {
        return $this->submission;
    }
}
