<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Contents of a testcase.
 *
 * @ORM\Entity
 * @ORM\Table(
 *     name="testcase_content",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4","comment"="Stores contents of testcase"})
 */
class TestcaseContent
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="tc_contentid", length=4,
     *     options={"comment"="Testcase content ID","unsigned"=true},
     *     nullable=false)
     */
    private $tc_contentid;

    /**
     * We use a ManyToOne instead of a OneToOne here, because otherwise the
     * reverse of this relation will always be loaded. See the commit message of commit
     * 9e421f96691ec67ed62767fe465a6d8751edd884 for a more elaborate explanation
     *
     * @var Testcase
     * @ORM\ManyToOne(targetEntity="Testcase", inversedBy="content")
     * @ORM\JoinColumn(name="testcaseid", referencedColumnName="testcaseid", onDelete="CASCADE")
     */
    private $testcase;

    /**
     * @var string
     * @ORM\Column(type="blobtext", length=4294967295, name="input",
     *     options={"comment"="Input data"}, nullable=true)
     */
    private $input;

    /**
     * @var string
     * @ORM\Column(type="blobtext", length=4294967295, name="output",
     *     options={"comment"="Output data"}, nullable=true)
     */
    private $output;

    /**
     * @var string
     * @ORM\Column(type="blobtext", length=4294967295, name="image",
     *     options={"comment"="A graphical representation of the testcase"},
     *     nullable=true)
     */
    private $image;

    /**
     * @var string
     * @ORM\Column(type="blobtext", length=4294967295, name="image_thumb",
     *     options={"comment"="Automatically created thumbnail of the image"},
     *     nullable=true)
     */
    private $image_thumb;

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
