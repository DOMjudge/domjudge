<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Contents of a testcase
 *
 * @ORM\Entity
 * @ORM\Table(
 *     name="testcase_content",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4","comment"="Stores contents of testcase"})
 */
class TestcaseContent
{
    /**
     * We use a ManyToOne instead of a OneToOne here, because otherwise the
     * reverse of this relation will always be loaded. See the commit message of commit
     * 9e421f96691ec67ed62767fe465a6d8751edd884 for a more elaborate explanation
     *
     * @var Testcase
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="Testcase", inversedBy="content")
     * @ORM\JoinColumn(name="testcaseid", referencedColumnName="testcaseid", onDelete="CASCADE")
     */
    private $testcase;

    /**
     * @var string
     * @ORM\Column(type="blobtext", length=4294967295, name="input",
     *     options={"comment"="Input data","default"="NULL"}, nullable=true)
     */
    private $input;

    /**
     * @var string
     * @ORM\Column(type="blobtext", length=4294967295, name="output",
     *     options={"comment"="Output data","default"="NULL"}, nullable=true)
     */
    private $output;

    /**
     * @var string
     * @ORM\Column(type="blobtext", length=4294967295, name="image",
     *     options={"comment"="A graphical representation of the testcase","default"="NULL"},
     *     nullable=true)
     */
    private $image;

    /**
     * @var string
     * @ORM\Column(type="blobtext", length=4294967295, name="image_thumb",
     *     options={"comment"="Automatically created thumbnail of the image","default"="NULL"},
     *     nullable=true)
     */
    private $image_thumb;

    /**
     * @param Testcase $testcase
     *
     * @return TestcaseContent
     */
    public function setTestcase(Testcase $testcase)
    {
        $this->testcase = $testcase;

        return $this;
    }

    /**
     * Get testcase
     *
     * @return Testcase
     */
    public function getTestcase(): Testcase
    {
        return $this->testcase;
    }

    /**
     * Set input
     *
     * @param string $input
     *
     * @return TestcaseContent
     */
    public function setInput($input)
    {
        $this->input = $input;

        return $this;
    }

    /**
     * Get input
     *
     * @return string
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * Set output
     *
     * @param string $output
     *
     * @return TestcaseContent
     */
    public function setOutput($output)
    {
        $this->output = $output;

        return $this;
    }

    /**
     * Get output
     *
     * @return string
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Set image
     *
     * @param string $image
     *
     * @return TestcaseContent
     */
    public function setImage($image)
    {
        $this->image = $image;

        return $this;
    }

    /**
     * Get image
     *
     * @return string
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * Set imageThumb
     *
     * @param string $imageThumb
     *
     * @return TestcaseContent
     */
    public function setImageThumb($imageThumb)
    {
        $this->image_thumb = $imageThumb;

        return $this;
    }

    /**
     * Get imageThumb
     *
     * @return string
     */
    public function getImageThumb()
    {
        return $this->image_thumb;
    }
}
