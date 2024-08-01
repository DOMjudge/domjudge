<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Stores testcase groups per problem.
 */
#[ORM\Entity]
#[ORM\Table(
    name: "testcase_group",
    indexes: [new ORM\Index(columns: ["probid"], name: "probid")],
    options: ["collation" => "utf8mb4_unicode_ci", "charset" => "utf8mb4", "comment" => "Stores testcase groups per problem."]
)]
class TestcaseGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(name: "testcasegroupid", type: "integer", nullable: false, options: ["comment" => "Testcase group ID", "unsigned" => true])]
    private int $testcasegroupid;

    #[ORM\Column(name: "points_percentage", type: "float", nullable: false, options: ["comment" => "Percentage of problem points this group is worth", "default" => 0, "unsigned" => true])]
    #[Serializer\Exclude]
    private float $points_percentage;

    #[ORM\Column(name: "name", type: "string", length: 255, nullable: true, options: ["comment" => "Which part of the problem this group tests.", "default" => null])]
    private string $name;

    #[ORM\OneToMany(mappedBy: "testcase_group", targetEntity: Testcase::class)]
    #[ORM\OrderBy(["ranknumber" => "ASC"])]
    #[Serializer\Exclude]
    private Collection $testcases;

    public function getTestcasegroupid(): int
    {
        return $this->testcasegroupid;
    }

    public function setTestcasegroupid(int $testcasegroupid): void
    {
        $this->testcasegroupid = $testcasegroupid;
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
