<?php declare(strict_types=1);

namespace DOMJudgeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Source code of submission files
 *
 * This is a seperate class with a OneToOne relationship with SubmissionFile so we can load it separately
 * @ORM\Entity()
 * @ORM\Table(name="submission_file", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class SubmissionFileSourceCode
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
     * @var resource
     * @ORM\Column(type="blob", name="sourcecode", options={"comment"="Full source code"}, nullable=false)
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
     * Set sourcecode
     *
     * @param resource|string $sourcecode
     *
     * @return SubmissionFileSourceCode
     */
    public function setSourcecode($sourcecode)
    {
        $this->sourcecode = $sourcecode;

        return $this;
    }

    /**
     * Get sourcecode
     *
     * @return resource
     */
    public function getSourcecode()
    {
        return $this->sourcecode;
    }
}
