<?php declare(strict_types=1);

namespace DOMJudgeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Extends SubmissionFile to also contain submission contents
 * @ORM\Entity()
 * @ORM\Table(name="submission_file", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class FullSubmissionFile extends SubmissionFile
{
    /**
     * @var resource
     * @ORM\Column(type="blob", name="sourcecode", options={"comment"="Full source code"}, nullable=false)
     */
    private $sourcecode;

    /**
     * @ORM\ManyToOne(targetEntity="Submission", inversedBy="full_files")
     * @ORM\JoinColumn(name="submitid", referencedColumnName="submitid")
     */
    private $submission_for_full;

    /**
     * Set sourcecode
     *
     * @param resource|string $sourcecode
     *
     * @return SubmissionFile
     */
    public function setSourcecode($sourcecode)
    {
        $this->sourcecode = $sourcecode;

        return $this;
    }

    /**
     * Get sourcecode
     *
     * @return resource
     */
    public function getSourcecode()
    {
        return $this->sourcecode;
    }

    /**
     * Set submission
     *
     * @param Submission $submission
     *
     * @return SubmissionFile
     */
    public function setSubmissionForFull(Submission $submission = null)
    {
        $this->submission_for_full = $submission;

        return $this;
    }

    /**
     * Get submission
     *
     * @return Submission
     */
    public function getSubmissionForFull()
    {
        return $this->submission_for_full;
    }
}
