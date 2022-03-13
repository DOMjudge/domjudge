<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A debug package from a specific judgehost/judging combination.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="debug_package",
 *     indexes={
 *         @ORM\Index(name="judgingid", columns={"judgingid"}),
 *     },
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Debug packages."}
 *     )
 */
class DebugPackage
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="debug_package_id", length=4,
     *     options={"comment"="Debug Package ID","unsigned"=true},
     *     nullable=false)
     */
    private int $debug_package_id;

    /**
     * @ORM\ManyToOne(targetEntity="Judging", inversedBy="debug_packages")
     * @ORM\JoinColumn(name="judgingid", referencedColumnName="judgingid", onDelete="CASCADE")
     */
    private Judging $judging;

    /**
     * @var string
     * @ORM\Column(type="string", name="filename", length=255,
     *     options={"comment"="Name of the file where we stored the debug package."},
     *     nullable=false)
     */
    private string $filename;

    /**
     * @ORM\ManyToOne(targetEntity="Judgehost")
     * @ORM\JoinColumn(name="judgehostid", referencedColumnName="judgehostid")
     */
    private Judgehost $judgehost;

    public function getDebugPackageId(): int
    {
        return $this->debug_package_id;
    }

    public function getJudging(): Judging
    {
        return $this->judging;
    }

    public function setJudging(Judging $judging): DebugPackage
    {
        $this->judging = $judging;
        return $this;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): DebugPackage
    {
        $this->filename = $filename;
        return $this;
    }

    public function getJudgehost(): Judgehost
    {
        return $this->judgehost;
    }

    public function setJudgehost(Judgehost $judgehost): DebugPackage
    {
        $this->judgehost = $judgehost;
        return $this;
    }
}
