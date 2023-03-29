<?php declare(strict_types=1);
namespace App\Entity;

use App\Doctrine\Constants;
use Doctrine\ORM\Mapping as ORM;

/**
 * A debug package from a specific judgehost/judging combination.
 */
#[ORM\Table(
    name: 'debug_package',
    options: [
        'collation' => 'utf8mb4_unicode_ci',
        'charset' => 'utf8mb4',
        'comment' => 'Debug packages.',
    ]
)]
#[ORM\Index(columns: ['judgingid'], name: 'judgingid')]
#[ORM\Entity]
class DebugPackage
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(
        name: 'debug_package_id',
        type: 'integer',
        length: 4,
        nullable: false,
        options: ['comment' => 'Debug Package ID', 'unsigned' => true]
    )]
    private int $debug_package_id;

    #[ORM\ManyToOne(targetEntity: Judging::class, inversedBy: 'debug_packages')]
    #[ORM\JoinColumn(name: 'judgingid', referencedColumnName: 'judgingid', onDelete: 'CASCADE')]
    private Judging $judging;

    #[ORM\Column(
        name: 'filename',
        type: 'string',
        length: Constants::LENGTH_LIMIT_TINYTEXT,
        nullable: false,
        options: ['comment' => 'Name of the file where we stored the debug package.']
    )]
    private string $filename;

    #[ORM\ManyToOne(targetEntity: Judgehost::class)]
    #[ORM\JoinColumn(name: 'judgehostid', referencedColumnName: 'judgehostid')]
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
