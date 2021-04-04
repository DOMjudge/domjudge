<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Hostnames of the autojudgers.
 *
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
     *              "unsigned"=true},
     *     nullable=true)
     */
    private $polltime;

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
     * @var boolean
     * @ORM\Column(type="boolean", name="hidden",
     *     options={"comment"="Should this host be hidden in the overview?",
     *              "default"="0"},
     *     nullable=false)
     */
    private $hidden = false;

    public function __construct()
    {
        $this->judgings = new ArrayCollection();
    }

    public function setHostname(string $hostname): Judgehost
    {
        $this->hostname = $hostname;
        return $this;
    }

    public function getHostname(): string
    {
        return $this->hostname;
    }

    public function setActive(bool $active): Judgehost
    {
        $this->active = $active;
        return $this;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    /** @param string|float $polltime */
    public function setPolltime($polltime): Judgehost
    {
        $this->polltime = $polltime;
        return $this;
    }

    /** @return string|float */
    public function getPolltime()
    {
        return $this->polltime;
    }

    public function setRestriction(?JudgehostRestriction $restriction = null): Judgehost
    {
        $this->restriction = $restriction;
        return $this;
    }

    public function getRestriction(): ?JudgehostRestriction
    {
        return $this->restriction;
    }

    public function addJudging(Judging $judging): Judgehost
    {
        $this->judgings[] = $judging;
        return $this;
    }

    public function removeJudging(Judging $judging)
    {
        $this->judgings->removeElement($judging);
    }

    public function getJudgings(): Collection
    {
        return $this->judgings;
    }

    public function setHidden(bool $hidden): Judgehost
    {
        $this->hidden = $hidden;
        return $this;
    }

    public function getHidden(): bool
    {
        return $this->hidden;
    }
}
