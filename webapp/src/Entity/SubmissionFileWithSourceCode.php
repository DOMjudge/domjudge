<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Source code of submission files
 *
 * This is a seperate class with a OneToOne relationship with SubmissionFile so we can load it separately
 * @ORM\Entity()
 * @ORM\Table(name="submission_file", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class SubmissionFileWithSourceCode
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="submitfileid", options={"comment"="Unique ID"}, nullable=false)
     */
    private $submitfileid;

    /**
     * @var int
     * @ORM\Column(type="integer", name="submitid", options={"comment"="Submission this file belongs to"}, nullable=false)
     */
    private $submitid;

    /**
     * @var string
     * @ORM\Column(type="string", name="filename", length=255, options={"comment"="Filename as submitted"}, nullable=false)
     */
    private $filename;

    /**
     * @var int
     * @ORM\Column(type="integer", name="rank", options={"comment"="Order of the submission files, zero-indexed", "unsigned"=true}, nullable=false)
     */
    private $rank;

    /**
     * @ORM\ManyToOne(targetEntity="Submission", inversedBy="files_with_source_code")
     * @ORM\JoinColumn(name="submitid", referencedColumnName="submitid", onDelete="CASCADE")
     */
    private $submission;

    /**
     * @var string
     * @ORM\Column(type="text", name="sourcecode", options={"comment"="Full source code"}, nullable=false)
     */
    private $sourcecode;

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
     * Set submitid
     *
     * @param integer $submitid
     *
     * @return SubmissionFileWithSourceCode
     */
    public function setSubmitid($submitid)
    {
        $this->submitid = $submitid;

        return $this;
    }

    /**
     * Get submitid
     *
     * @return integer
     */
    public function getSubmitid()
    {
        return $this->submitid;
    }

    /**
     * Set filename
     *
     * @param string $filename
     *
     * @return SubmissionFileWithSourceCode
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
     *
     * @return SubmissionFileWithSourceCode
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
     * @param Submission $submission
     *
     * @return SubmissionFileWithSourceCode
     */
    public function setSubmission(Submission $submission = null)
    {
        $this->submission = $submission;

        return $this;
    }

    /**
     * Get submission
     *
     * @return Submission
     */
    public function getSubmission()
    {
        return $this->submission;
    }

    /**
     * Set sourcecode
     *
     * @param string $sourcecode
     *
     * @return SubmissionFileWithSourceCode
     */
    public function setSourcecode($sourcecode)
    {
        $this->sourcecode = $sourcecode;

        return $this;
    }

    /**
     * Get sourcecode
     *
     * @return string
     */
    public function getSourcecode()
    {
        return $this->sourcecode;
    }
}
