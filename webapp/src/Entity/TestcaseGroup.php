<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Stores testcase groups per problem.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="testcase_group",
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Stores testcase groups per problem."},
 *     indexes={
 *         @ORM\Index(name="probid", columns={"probid"})
 *     })
 */
class TestcaseGroup
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="testcasegroupid", length=4,
     *     options={"comment"="Testcase group ID","unsigned"=true},
     *     nullable=false)
     */
    private int $testcasegroupid;

    /**
     * @ORM\ManyToOne(targetEntity="Problem", inversedBy="testcase_groups")
     * @ORM\JoinColumn(name="probid", referencedColumnName="probid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private ?Problem $problem;

    /**
     * @ORM\Column(type="float", name="points_percentage",
     *     options={"comment"="Percentage of problem points this group is worth", "default"="0", "unsigned"=true},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private float $points_percentage;

    /**
     * @ORM\Column(type="string", name="name", length=255,
     *     options={"comment"="Which part of the problem this group tests.", "default"=null},
     *     nullable=true)
     */
    private string $name;

    /**
     * @ORM\OneToMany(targetEntity="Testcase", mappedBy="testcase_group")
     * @ORM\OrderBy({"ranknumber" = "ASC"})
     * @Serializer\Exclude()
     */
    private Collection $testcases;

    public function getTestcasegroupid(): int
    {
        return $this->testcasegroupid;
    }

    public function setTestcasegroupid(int $testcasegroupid): void
    {
        $this->testcasegroupid = $testcasegroupid;
    }

    public function getProblem(): ?Problem
    {
        return $this->problem;
    }

    public function setProblem(?Problem $problem): void
    {
        $this->problem = $problem;
    }

    public function getPointsPercentage(): float
    {
        return $this->points_percentage;
    }

    public function setPointsPercentage(float $points_percentage): void
    {
        $this->points_percentage = $points_percentage;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getShortDescription(): string
    {
        return $this->getName();
    }

    public function getTestcases(): Collection
    {
        return $this->testcases;
    }

    public function setTestcases(Collection $testcases): void
    {
        $this->testcases = $testcases;
    }
}
