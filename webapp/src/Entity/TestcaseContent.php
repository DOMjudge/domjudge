<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Contents of a testcase.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Stores contents of testcase',
])]
class TestcaseContent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Testcase content ID', 'unsigned' => true])]
    private int $tc_contentid;

    /**
     * We use a ManyToOne instead of a OneToOne here, because otherwise the
     * reverse of this relation will always be loaded. See the commit message of commit
     * 9e421f96691ec67ed62767fe465a6d8751edd884 for a more elaborate explanation.
     */
    #[ORM\ManyToOne(inversedBy: 'content')]
    #[ORM\JoinColumn(name: 'testcaseid', referencedColumnName: 'testcaseid', onDelete: 'CASCADE')]
    private Testcase $testcase;

    #[ORM\Column(type: 'blobtext', nullable: true, options: ['comment' => 'Input data'])]
    private ?string $input = null;

    #[ORM\Column(type: 'blobtext', nullable: true, options: ['comment' => 'Output data'])]
    private ?string $output = null;

    #[ORM\Column(
        type: 'blobtext',
        nullable: true,
        options: ['comment' => 'A graphical representation of the testcase']
    )]
    private ?string $image = null;

    #[ORM\Column(
        type: 'blobtext',
        nullable: true,
        options: ['comment' => 'Automatically created thumbnail of the image']
    )]
    private ?string $image_thumb = null;

    public function getTestcaseContentId(): int
    {
        return $this->tc_contentid;
    }

    public function setTestcaseContentId(int $tc_contentid): TestcaseContent
    {
        $this->tc_contentid = $tc_contentid;
        return $this;
    }

    public function setTestcase(Testcase $testcase): TestcaseContent
    {
        $this->testcase = $testcase;
        return $this;
    }

    public function getTestcase(): Testcase
    {
        return $this->testcase;
    }

    public function setInput(string $input): TestcaseContent
    {
        $this->input = $input;
        return $this;
    }

    public function getInput(): string
    {
        return $this->input;
    }

    public function setOutput(string $output): TestcaseContent
    {
        $this->output = $output;
        return $this;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function setImage(?string $image): TestcaseContent
    {
        $this->image = $image;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImageThumb(?string $imageThumb): TestcaseContent
    {
        $this->image_thumb = $imageThumb;
        return $this;
    }

    public function getImageThumb(): ?string
    {
        return $this->image_thumb;
    }
}
