<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Files associated to a submission.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Files associated to a submission',
])]
#[ORM\Index(name: 'submitid', columns: ['submitid'])]
#[ORM\UniqueConstraint(name: 'rankindex', columns: ['submitid', 'ranknumber'])]
#[ORM\UniqueConstraint(name: 'filename', columns: ['submitid', 'filename'], options: ['lengths' => [null, 190]])]
class SubmissionFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Submission file ID', 'unsigned' => true])]
    private int $submitfileid;

    #[ORM\Column(options: ['comment' => 'Filename as submitted'])]
    private string $filename;

    #[ORM\Column(options: [
        'comment' => 'Order of the submission files, zero-indexed',
        'unsigned' => true,
    ])]
    private int $ranknumber;

    #[ORM\ManyToOne(inversedBy: 'files')]
    #[ORM\JoinColumn(name: 'submitid', referencedColumnName: 'submitid', onDelete: 'CASCADE')]
    private Submission $submission;

    #[ORM\Column(type: 'blobtext', options: ['comment' => 'Full source code'])]
    private string $sourcecode;

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
