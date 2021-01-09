<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Files associated to a submission.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="submission_file",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Files associated to a submission"},
 *     indexes={@ORM\Index(name="submitid", columns={"submitid"})},
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="rankindex", columns={"submitid", "ranknumber"}),
 *         @ORM\UniqueConstraint(name="filename", columns={"submitid", "filename"}, options={"lengths": {NULL, 190}})
 *     })
 */
class SubmissionFile
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="submitfileid", length=4,
     *     options={"comment"="Submission file ID","unsigned"=true},
     *     nullable=false)
     */
    private $submitfileid;

    /**
     * @var string
     * @ORM\Column(type="string", name="filename", length=255, options={"comment"="Filename as submitted"}, nullable=false)
     */
    private $filename;

    /**
     * @var int
     * @ORM\Column(type="integer", name="ranknumber",
     *     options={"comment"="Order of the submission files, zero-indexed", "unsigned"=true},
     *     nullable=false)
     */
    private $ranknumber;

    /**
     * @ORM\ManyToOne(targetEntity="Submission", inversedBy="files")
     * @ORM\JoinColumn(name="submitid", referencedColumnName="submitid", onDelete="CASCADE")
     */
    private $submission;

    /**
     * @var string
     * @ORM\Column(type="blobtext", name="sourcecode", length=4294967295,
     *     options={"comment"="Full source code"}, nullable=false)
     */
    private $sourcecode;

    public function getSubmitfileid(): int
    {
        return $this->submitfileid;
    }

    public function setFilename(string $filename): SubmissionFile
    {
        $this->filename = $filename;
        return $this;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setRank(int $rank): SubmissionFile
    {
        $this->ranknumber = $rank;
        return $this;
    }

    public function getRank(): int
    {
        return $this->ranknumber;
    }

    public function setSubmission(?Submission $submission = null): SubmissionFile
    {
        $this->submission = $submission;
        return $this;
    }

    public function getSubmission(): Submission
    {
        return $this->submission;
    }

    public function setSourcecode(string $sourcecode): SubmissionFile
    {
        $this->sourcecode = $sourcecode;
        return $this;
    }

    public function getSourcecode(): string
    {
        return $this->sourcecode;
    }
}
