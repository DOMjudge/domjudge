<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Hostnames of the autojudgers
 * @ORM\Entity()
 * @ORM\Table(
 *     name="judgehost",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Hostnames of the autojudgers"},
 *     indexes={@ORM\Index(name="restrictionid", columns={"restrictionid"})})
 */
class Judgehost
{
    /**
     * @var string
     * @ORM\Id
     * @ORM\Column(type="string", name="hostname", length=64, options={"comment"="Resolvable hostname of judgehost"}, nullable=false)
     * @Assert\Regex("/^[A-Za-z0-9_\-.]*$/", message="Invalid hostname. Only characters in [A-Za-z0-9_\-.] are allowed.")
     */
    private $hostname;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="active",
     *     options={"comment"="Should this host take on judgings?",
     *              "default"="1"},
     *     nullable=false)
     */
    private $active = true;


    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="polltime",
     *     options={"comment"="Time of last poll by autojudger",
     *              "unsigned"=true,"default"="NULL"},
     *     nullable=true)
     */
    private $polltime;

    /**
     * @var int
     * @ORM\Column(type="integer", name="restrictionid",
     *     options={"comment"="Optional set of restrictions for this judgehost",
     *              "unsigned"=true,"default"="NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $restrictionid;

    /**
     * @ORM\ManyToOne(targetEntity="JudgehostRestriction", inversedBy="judgehosts")
     * @ORM\JoinColumn(name="restrictionid", referencedColumnName="restrictionid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private $restriction;


    /**
     * @ORM\OneToMany(targetEntity="Judging", mappedBy="judgehost")
     * @Serializer\Exclude()
     */
    private $judgings;

    /**
     * Set hostname
     *
     * @param string $hostname
     *
     * @return Judgehost
     */
    public function setHostname($hostname)
    {
        $this->hostname = $hostname;

        return $this;
    }

    /**
     * Get hostname
     *
     * @return string
     */
    public function getHostname()
    {
        return $this->hostname;
    }

    /**
     * Set active
     *
     * @param boolean $active
     *
     * @return Judgehost
     */
    public function setActive($active)
    {
        $this->active = $active;

        return $this;
    }

    /**
     * Get active
     *
     * @return boolean
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Set polltime
     *
     * @param string $polltime
     *
     * @return Judgehost
     */
    public function setPolltime($polltime)
    {
        $this->polltime = $polltime;

        return $this;
    }

    /**
     * Get polltime
     *
     * @return string
     */
    public function getPolltime()
    {
        return $this->polltime;
    }

    /**
     * Set restrictionid
     *
     * @param integer $restrictionid
     *
     * @return Judgehost
     */
    public function setRestrictionid($restrictionid)
    {
        $this->restrictionid = $restrictionid;

        return $this;
    }

    /**
     * Get restrictionid
     *
     * @return integer
     */
    public function getRestrictionid()
    {
        return $this->restrictionid;
    }

    /**
     * Set restriction
     *
     * @param \App\Entity\JudgehostRestriction $restriction
     *
     * @return Judgehost
     */
    public function setRestriction(
        \App\Entity\JudgehostRestriction $restriction = null)
    {
        $this->restriction = $restriction;

        return $this;
    }

    /**
     * Get restriction
     *
     * @return \App\Entity\JudgehostRestriction
     */
    public function getRestriction()
    {
        return $this->restriction;
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->judgings = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add judging
     *
     * @param \App\Entity\Judging $judging
     *
     * @return Judgehost
     */
    public function addJudging(\App\Entity\Judging $judging)
    {
        $this->judgings[] = $judging;

        return $this;
    }

    /**
     * Remove judging
     *
     * @param \App\Entity\Judging $judging
     */
    public function removeJudging(\App\Entity\Judging $judging)
    {
        $this->judgings->removeElement($judging);
    }

    /**
     * Get judgings
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getJudgings()
    {
        return $this->judgings;
    }
}
