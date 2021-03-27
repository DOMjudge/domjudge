<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Restrictions to be applied to judgehosts, e.g. to certain problems or languages.
 *
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
     *     options={"comment"="JSON-encoded restrictions"},
     *     nullable=true)
     */
    private $restrictions;

    /**
     * @ORM\OneToMany(targetEntity="Judgehost", mappedBy="restriction")
     */
    private $judgehosts;

    public function __construct()
    {
        $this->judgehosts = new ArrayCollection();
    }

    public function getRestrictionid(): int
    {
        return $this->restrictionid;
    }

    public function setName(string $name): JudgehostRestriction
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getShortDescription(): string
    {
        return $this->getName();
    }

    public function setRestrictions(array $restrictions): JudgehostRestriction
    {
        $this->restrictions = $restrictions;
        return $this;
    }

    public function getRestrictions(): array
    {
        return $this->restrictions;
    }

    public function setContests(array $contests): JudgehostRestriction
    {
        $this->restrictions['contest'] = $contests;
        return $this;
    }

    public function getContests(): array
    {
        return $this->restrictions['contest'] ?? [];
    }

    public function setProblems(array $problems): JudgehostRestriction
    {
        $this->restrictions['problem'] = $problems;
        return $this;
    }

    public function getProblems(): array
    {
        return $this->restrictions['problem'] ?? [];
    }

    public function setLanguages(array $languages): JudgehostRestriction
    {
        $this->restrictions['language'] = $languages;
        return $this;
    }

    public function getLanguages(): array
    {
        return $this->restrictions['language'] ?? [];
    }

    public function setRejudgeOwn(bool $rejudgeOwn): JudgehostRestriction
    {
        $this->restrictions['rejudge_own'] = $rejudgeOwn;
        return $this;
    }

    public function getRejudgeOwn(): bool
    {
        return $this->restrictions['rejudge_own'] ?? true;
    }

    public function addJudgehost(Judgehost $judgehost): JudgehostRestriction
    {
        $this->judgehosts[] = $judgehost;
        return $this;
    }

    public function removeJudgehost(Judgehost $judgehost)
    {
        $this->judgehosts->removeElement($judgehost);
    }

    public function getJudgehosts(): Collection
    {
        return $this->judgehosts;
    }
}
