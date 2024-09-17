<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Stores contents of problem attachments',
])]
class ProblemAttachmentContent
{
    /**
     * We use a ManyToOne instead of a OneToOne here, because otherwise the
     * reverse of this relation will always be loaded. See the commit message of commit
     * 9e421f96691ec67ed62767fe465a6d8751edd884 for a more elaborate explanation.
     */
    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'content')]
    #[ORM\JoinColumn(name: 'attachmentid', referencedColumnName: 'attachmentid', onDelete: 'CASCADE')]
    private ProblemAttachment $attachment;

    #[ORM\Column(type: 'blobtext', options: ['comment' => 'Attachment content'])]
    private string $content;

    #[ORM\Column(options: ['comment' => 'Whether this file gets an executable bit.', 'default' => 0])]
    #[Serializer\Exclude]
    private bool $isExecutable = false;

    public function getAttachment(): ProblemAttachment
    {
        return $this->attachment;
    }

    public function setAttachment(ProblemAttachment $attachment): self
    {
        $this->attachment = $attachment;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function setIsExecutable(bool $isExecutable): ProblemAttachmentContent
    {
        $this->isExecutable = $isExecutable;
        return $this;
    }

    public function isExecutable(): bool
    {
        return $this->isExecutable;
    }
}
