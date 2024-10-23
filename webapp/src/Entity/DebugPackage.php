<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A debug package from a specific judgehost/judging combination.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Debug packages.',
])]
#[ORM\Index(columns: ['judgingid'], name: 'judgingid')]
class DebugPackage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Debug Package ID', 'unsigned' => true])]
    private int $debug_package_id;

    #[ORM\ManyToOne(inversedBy: 'debug_packages')]
    #[ORM\JoinColumn(name: 'judgingid', referencedColumnName: 'judgingid', onDelete: 'CASCADE')]
    private Judging $judging;

    #[ORM\Column(options: ['comment' => 'Name of the file where we stored the debug package.'])]
    private string $filename;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'judgehostid', referencedColumnName: 'judgehostid', onDelete: 'SET NULL')]
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
