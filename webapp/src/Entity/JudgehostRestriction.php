<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Hostnames of the autojudgers
 * @ORM\Entity()
 * @ORM\Table(
 *     name="judgehost_restriction",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Restrictions for judgehosts"})
 */
class JudgehostRestriction
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="restrictionid", length=4,
     *     options={"comment"="Judgehost restriction ID","unsigned"=true}, nullable=false)
     */
    private $restrictionid;

    /**
     * @var string
     * @ORM\Column(type="string", name="name", length=255, options={"comment"="Descriptive name"}, nullable=false)
     */
    private $name;

    /**
     * @var array
     * @ORM\Column(type="json", name="restrictions",
     *     options={"comment"="JSON-encoded restrictions","default"="NULL"},
     *     nullable=true)
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
     * @param array $restrictions
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
     * @return array
     */
    public function getRestrictions()
    {
        return $this->restrictions;
    }

    /**
     * Set restriction contests
     * @param int[] $contests
     * @return $this
     */
    public function setContests(array $contests)
    {
        $this->restrictions['contest'] = $contests;
        return $this;
    }

    /**
     * Get restriction contests
     * @return int[]
     */
    public function getContests()
    {
        return $this->restrictions['contest'] ?? [];
    }

    /**
     * Set restriction problems
     * @param int[] $problems
     * @return $this
     */
    public function setProblems(array $problems)
    {
        $this->restrictions['problem'] = $problems;
        return $this;
    }

    /**
     * Get restriction problems
     * @return int[]
     */
    public function getProblems()
    {
        return $this->restrictions['problem'] ?? [];
    }

    /**
     * Set restriction languages
     * @param string[] $languages
     * @return $this
     */
    public function setLanguages(array $languages)
    {
        $this->restrictions['language'] = $languages;
        return $this;
    }

    /**
     * Get restriction languages
     * @return string[]
     */
    public function getLanguages()
    {
        return $this->restrictions['language'] ?? [];
    }

    /**
     * Set restriction rejudge own
     * @param bool $rejudgeOwn
     * @return $this
     */
    public function setRejudgeOwn(bool $rejudgeOwn)
    {
        $this->restrictions['rejudge_own'] = $rejudgeOwn;
        return $this;
    }

    /**
     * Get restriction rejudge own
     * @return bool
     */
    public function getRejudgeOwn()
    {
        return $this->restrictions['rejudge_own'] ?? true;
    }

    /**
     * Add judgehost
     *
     * @param \App\Entity\Judgehost $judgehost
     *
     * @return JudgehostRestriction
     */
    public function addJudgehost(\App\Entity\Judgehost $judgehost)
    {
        $this->judgehosts[] = $judgehost;

        return $this;
    }

    /**
     * Remove judgehost
     *
     * @param \App\Entity\Judgehost $judgehost
     */
    public function removeJudgehost(\App\Entity\Judgehost $judgehost)
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
