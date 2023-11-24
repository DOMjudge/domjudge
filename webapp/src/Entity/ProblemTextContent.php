<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Stores contents of problem texts',
])]
class ProblemTextContent
{
    /**
     * We use a ManyToOne instead of a OneToOne here, because otherwise the
     * reverse of this relation will always be loaded. See the commit message of commit
     * 9e421f96691ec67ed62767fe465a6d8751edd884 for a more elaborate explanation.
     */
    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'problemTextContent')]
    #[ORM\JoinColumn(name: 'probid', referencedColumnName: 'probid', onDelete: 'CASCADE')]
    private Problem $problem;

    #[ORM\Column(type: 'blobtext', options: ['comment' => 'Text content'])]
    private string $content;

    public function getProblem(): Problem
    {
        return $this->problem;
    }

    public function setProblem(Problem $problem): self
    {
        $this->problem = $problem;

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
}
