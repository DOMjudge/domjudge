<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A team output on a specific testcase.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Team output visualization.',
])]
#[ORM\Index(columns: ['judgingid'], name: 'judgingid')]
class Visualization
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Visualization ID', 'unsigned' => true])]
    private int $visualization_id;

    #[ORM\ManyToOne(inversedBy: 'visualizations')]
    #[ORM\JoinColumn(name: 'judgingid', referencedColumnName: 'judgingid', onDelete: 'CASCADE')]
    private Judging $judging;

    #[ORM\Column(options: ['comment' => 'Name of the file where we stored the visualization.'])]
    private string $filename;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'judgehostid', referencedColumnName: 'judgehostid', onDelete: 'SET NULL')]
    private Judgehost $judgehost;

    #[ORM\ManyToOne(inversedBy: 'visualizations')]
    #[ORM\JoinColumn(name: 'testcaseid', referencedColumnName: 'testcaseid', onDelete: 'CASCADE')]
    private Testcase $testcase;

    public function getVisualizationId(): int
    {
        return $this->visualization_id;
    }

    public function getJudging(): Judging
    {
        return $this->judging;
    }

    public function setJudging(Judging $judging): Visualization
    {
        $this->judging = $judging;
        return $this;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): Visualization
    {
        $this->filename = $filename;
        return $this;
    }

    public function getJudgehost(): Judgehost
    {
        return $this->judgehost;
    }

    public function setJudgehost(Judgehost $judgehost): Visualization
    {
        $this->judgehost = $judgehost;
        return $this;
    }

    public function getTestcase(): Judgehost
    {
        return $this->testcase;
    }

    public function setTestcase(Testcase $testcase): Visualization
    {
        $this->testcase = $testcase;
        return $this;
    }
}
