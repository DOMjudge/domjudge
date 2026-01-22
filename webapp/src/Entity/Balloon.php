<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Balloons to be handed out.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Balloons to be handed out',
])]
#[ORM\Index(name: 'submitid', columns: ['submitid'])]
#[ORM\UniqueConstraint(name: 'unique_problem', columns: ['cid', 'teamid', 'probid'])]
class Balloon
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Balloon ID', 'unsigned' => true])]
    private int $balloonid;

    #[ORM\Column(options: ['comment' => 'Has been handed out yet?', 'default' => 0])]
    private bool $done = false;

    #[ORM\ManyToOne(inversedBy: 'balloons')]
    #[ORM\JoinColumn(name: 'submitid', referencedColumnName: 'submitid', onDelete: 'CASCADE')]
    private Submission $submission;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'teamid', referencedColumnName: 'teamid', onDelete: 'CASCADE')]
    #[Serializer\Exclude]
    private Team $team;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'probid', referencedColumnName: 'probid', onDelete: 'CASCADE')]
    #[Serializer\Exclude]
    private Problem $problem;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'cid', referencedColumnName: 'cid', onDelete: 'CASCADE')]
    #[Serializer\Exclude]
    private Contest $contest;

    public function getBalloonid(): int
    {
        return $this->balloonid;
    }

    public function setDone(bool $done): Balloon
    {
        $this->done = $done;
        return $this;
    }

    public function getDone(): bool
    {
        return $this->done;
    }

    public function setSubmission(?Submission $submission = null): Balloon
    {
        $this->submission = $submission;
        return $this;
    }

    public function getSubmission(): Submission
    {
        return $this->submission;
    }

    public function getTeam(): Team
    {
        return $this->team;
    }

    public function setTeam(Team $team): Balloon
    {
        $this->team = $team;
        return $this;
    }

    public function getProblem(): Problem
    {
        return $this->problem;
    }

    public function setProblem(Problem $problem): Balloon
    {
        $this->problem = $problem;
        return $this;
    }

    public function getContest(): Contest
    {
        return $this->contest;
    }

    public function setContest(Contest $contest): Balloon
    {
        $this->contest = $contest;
        return $this;
    }
}
