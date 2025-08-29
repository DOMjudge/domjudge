<?php declare(strict_types=1);

namespace App\Entity;

use App\Utils\Utils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Attachments belonging to problems',
])]
#[ORM\Index(columns: ['attachmentid', 'name'], name: 'name', options: ['lengths' => [null, 190]])]
class ProblemAttachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Attachment ID', 'unsigned' => true])]
    private ?int $attachmentid = null;

    #[ORM\Column(options: ['comment' => 'Filename of attachment'])]
    private ?string $name = null;

    #[ORM\Column(length: 4, options: ['comment' => 'File type of attachment'])]
    private ?string $type = null;

    #[ORM\Column(options: ['comment' => 'Mime type of attachment'])]
    private ?string $mimeType = null;

    #[ORM\ManyToOne(inversedBy: 'attachments')]
    #[ORM\JoinColumn(name: 'probid', referencedColumnName: 'probid', onDelete: 'CASCADE')]
    private ?Problem $problem = null;

    /**
     * @var Collection<int, ProblemAttachmentContent>
     *
     * We use a OneToMany instead of a OneToOne here, because otherwise this
     * relation will always be loaded. See the commit message of commit
     * 9e421f96691ec67ed62767fe465a6d8751edd884 for a more elaborate explanation
     */
    #[ORM\OneToMany(
        mappedBy: 'attachment',
        targetEntity: ProblemAttachmentContent::class,
        cascade: ['persist'],
        orphanRemoval: true
    )]
    private Collection $content;

    public function __construct()
    {
        $this->content = new ArrayCollection();
    }

    public function getAttachmentid(): ?int
    {
        return $this->attachmentid;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->getName();
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getProblem(): ?Problem
    {
        return $this->problem;
    }

    public function setProblem(?Problem $problem): self
    {
        $this->problem = $problem;

        return $this;
    }

    public function setContent(ProblemAttachmentContent $content): self
    {
        $this->content->clear();
        $this->content->add($content);
        $content->setAttachment($this);

        return $this;
    }

    public function getContent(): ?ProblemAttachmentContent
    {
        return $this->content->first() ?: null;
    }

    public function getStreamedResponse(): StreamedResponse
    {
        $content  = $this->getContent()->getContent();
        $filename = $this->getName();

        return Utils::streamAsBinaryFile($content, $filename, 'octet-stream');
    }
}
