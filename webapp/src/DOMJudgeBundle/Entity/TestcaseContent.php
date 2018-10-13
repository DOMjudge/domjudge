<?php declare(strict_types=1);

namespace DOMJudgeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Contents of a testcase
 *
 * This is a seperate class with a OneToOne relationship with Testcase so we can load it separately
 * @ORM\Entity()
 * @ORM\Table(name="testcase", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class TestcaseContent
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="testcaseid", options={"comment"="Unique ID"}, nullable=false)
     */
    private $testcaseid;

    /**
     * @var string
     * @ORM\Column(type="blob", name="input", options={"comment"="Input data"}, nullable=false)
     */
    private $input;

    /**
     * @var string
     * @ORM\Column(type="blob", name="output", options={"comment"="Output data"}, nullable=false)
     */
    private $output;

    /**
     * @var string
     * @ORM\Column(type="blob", name="image", options={"comment"="A graphical representation of this testcase"}, nullable=true)
     */
    private $image;

    /**
     * @var string
     * @ORM\Column(type="blob", name="image_thumb", options={"comment"="Automatically created thumbnail of the image"}, nullable=true)
     */
    private $image_thumb;

    /**
     * Get testcaseid
     *
     * @return integer
     */
    public function getTestcaseid()
    {
        return $this->testcaseid;
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
