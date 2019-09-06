<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Balloons to be handed out
 * @ORM\Entity()
 * @ORM\Table(
 *     name="balloon",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Balloons to be handed out"},
 *     indexes={@ORM\Index(name="submitid", columns={"submitid"})}
 *     )
 */
class Balloon
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", length=4, name="balloonid",
     *     options={"comment"="Balloon ID", "unsigned"=true}, nullable=false)
     */
    private $balloonid;

    /**
     * @var int
     * @ORM\Column(type="integer", name="submitid",
     *     options={"comment"="Submission for which balloon was earned","unsigned"=true},
     *     nullable=false)
     */
    private $submitid;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="done",
     *     options={"comment"="Has been handed out yet?","default"="0"},
     *     nullable=false)
     */
    private $done = false;

    /**
     * @ORM\ManyToOne(targetEntity="Submission", inversedBy="balloons")
     * @ORM\JoinColumn(name="submitid", referencedColumnName="submitid", onDelete="CASCADE")
     */
    private $submission;


    /**
     * Get balloonid
     *
     * @return integer
     */
    public function getBalloonid()
    {
        return $this->balloonid;
    }

    /**
     * Set submitid
     *
     * @param integer $submitid
     *
     * @return Balloon
     */
    public function setSubmitid($submitid)
    {
        $this->submitid = $submitid;

        return $this;
    }

    /**
     * Get submitid
     *
     * @return integer
     */
    public function getSubmitid()
    {
        return $this->submitid;
    }

    /**
     * Set done
     *
     * @param boolean $done
     *
     * @return Balloon
     */
    public function setDone($done)
    {
        $this->done = $done;

        return $this;
    }

    /**
     * Get done
     *
     * @return boolean
     */
    public function getDone()
    {
        return $this->done;
    }

    /**
     * Set submission
     *
     * @param \App\Entity\Submission $submission
     *
     * @return Balloon
     */
    public function setSubmission(\App\Entity\Submission $submission = null)
    {
        $this->submission = $submission;

        return $this;
    }

    /**
     * Get submission
     *
     * @return \App\Entity\Submission
     */
    public function getSubmission()
    {
        return $this->submission;
    }
}
