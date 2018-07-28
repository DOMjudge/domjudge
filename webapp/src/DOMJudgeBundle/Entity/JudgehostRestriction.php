<?php
namespace DOMJudgeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Hostnames of the autojudgers
 * @ORM\Entity()
 * @ORM\Table(name="judgehost_restriction", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class JudgehostRestriction
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="restrictionid", options={"comment"="Unique ID"}, nullable=false)
     */
    private $restrictionid;

    /**
     * @var string
     * @ORM\Column(type="string", name="name", length=255, options={"comment"="Descriptive name"}, nullable=true)
     */
    private $name;

    /**
     * @var string
     * @ORM\Column(type="text", length=4294967295, name="restrictions", options={"comment"="JSON-encoded restrictions"}, nullable=false)
     */
    private $restrictions;

    /**
     * @ORM\OneToMany(targetEntity="Judgehost", mappedBy="restriction")
     */
    private $judgehosts;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->judgehosts = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set name
     *
     * @param string $name
     *
     * @return JudgehostRestriction
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set restrictions
     *
     * @param string $restrictions
     *
     * @return JudgehostRestriction
     */
    public function setRestrictions($restrictions)
    {
        $this->restrictions = $restrictions;

        return $this;
    }

    /**
     * Get restrictions
     *
     * @return string
     */
    public function getRestrictions()
    {
        return $this->restrictions;
    }

    /**
     * Add judgehost
     *
     * @param \DOMJudgeBundle\Entity\Judgehost $judgehost
     *
     * @return JudgehostRestriction
     */
    public function addJudgehost(\DOMJudgeBundle\Entity\Judgehost $judgehost)
    {
        $this->judgehosts[] = $judgehost;

        return $this;
    }

    /**
     * Remove judgehost
     *
     * @param \DOMJudgeBundle\Entity\Judgehost $judgehost
     */
    public function removeJudgehost(\DOMJudgeBundle\Entity\Judgehost $judgehost)
    {
        $this->judgehosts->removeElement($judgehost);
    }

    /**
     * Get judgehosts
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getJudgehosts()
    {
        return $this->judgehosts;
    }
}
