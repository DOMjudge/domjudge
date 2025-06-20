<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Scoreboard cache.
 */
#[ORM\Entity]
#[ORM\Table(
    name: 'scorecache',
    options: [
        'collation' => 'utf8mb4_unicode_ci',
        'charset' => 'utf8mb4',
        'comment' => 'Scoreboard cache',
    ]
)]
#[ORM\Index(columns: ['cid'], name: 'cid')]
#[ORM\Index(columns: ['teamid'], name: 'teamid')]
#[ORM\Index(columns: ['probid'], name: 'probid')]
class ScoreCache
{
    #[ORM\Column(options: [
        'comment' => 'Number of submissions made (restricted audiences)',
        'unsigned' => true,
        'default' => 0,
    ])]
    private int $submissions_restricted = 0;

    #[ORM\Column(options: [
        'comment' => 'Number of submissions pending judgement (restricted audience)',
        'unsigned' => true,
        'default' => 0,
    ])]
    private int $pending_restricted = 0;

    #[ORM\Column(options: [
        'comment' => 'Has there been a correct submission? (restricted audience)',
        'default' => 0,
    ])]
    private bool $is_correct_restricted = false;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        options: [
            'comment' => 'Seconds into contest when problem solved (restricted audience)',
            'default' => '0.000000000',
        ]
    )]
    private float $solvetime_restricted = 0;

    #[ORM\Column(options: [
        'comment' => 'Runtime in milliseconds (restricted audience)',
        'default' => 0,
    ])]
    private int $runtime_restricted = 0;

    #[ORM\Column(options: [
        'comment' => 'Number of submissions made (public)',
        'unsigned' => true,
        'default' => 0,
    ])]
    private int $submissions_public = 0;

    #[ORM\Column(options: [
        'comment' => 'Number of submissions pending judgement (public)',
        'unsigned' => true,
        'default' => 0,
    ])]
    private int $pending_public = 0;

    #[ORM\Column(options: ['comment' => 'Has there been a correct submission? (public)', 'default' => 0])]
    private bool $is_correct_public = false;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        options: [
            'comment' => 'Seconds into contest when problem solved (public)',
            'default' => '0.000000000',
        ]
    )]
    private float $solvetime_public = 0;

    #[ORM\Column(options: ['comment' => 'Runtime in milliseconds (public)', 'default' => 0])]
    private int $runtime_public = 0;

    #[ORM\Column(options: [
        'comment' => 'Is this the first solution to this problem?',
        'default' => 0,
    ])]
    private bool $is_first_to_solve = false;

    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'cid', referencedColumnName: 'cid', onDelete: 'CASCADE')]
    private Contest $contest;

    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'teamid', referencedColumnName: 'teamid', onDelete: 'CASCADE')]
    private Team $team;

    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'probid', referencedColumnName: 'probid', onDelete: 'CASCADE')]
    private Problem $problem;

    public function setSubmissionsRestricted(int $submissionsRestricted): ScoreCache
    {
        $this->submissions_restricted = $submissionsRestricted;
        return $this;
    }

    public function getSubmissionsRestricted(): int
    {
        return $this->submissions_restricted;
    }

    public function setPendingRestricted(int $pendingRestricted): ScoreCache
    {
        $this->pending_restricted = $pendingRestricted;
        return $this;
    }

    public function getPendingRestricted(): int
    {
        return $this->pending_restricted;
    }

    public function setIsCorrectRestricted(bool $isCorrectRestricted): ScoreCache
    {
        $this->is_correct_restricted = $isCorrectRestricted;
        return $this;
    }

    public function getIsCorrectRestricted(): bool
    {
        return $this->is_correct_restricted;
    }

    public function setSolvetimeRestricted(float $solvetimeRestricted): ScoreCache
    {
        $this->solvetime_restricted = $solvetimeRestricted;
        return $this;
    }

    public function getSolvetimeRestricted(): float
    {
        return $this->solvetime_restricted;
    }

    public function setRuntimeRestricted(int $runtimeRestricted): ScoreCache
    {
        $this->runtime_restricted = $runtimeRestricted;
        return $this;
    }

    public function getRuntimeRestricted(): int
    {
        return $this->runtime_restricted;
    }

    public function setSubmissionsPublic(int $submissionsPublic): ScoreCache
    {
        $this->submissions_public = $submissionsPublic;
        return $this;
    }

    public function getSubmissionsPublic(): int
    {
        return $this->submissions_public;
    }

    public function setPendingPublic(int $pendingPublic): ScoreCache
    {
        $this->pending_public = $pendingPublic;
        return $this;
    }

    public function getPendingPublic(): int
    {
        return $this->pending_public;
    }

    public function setIsCorrectPublic(bool $isCorrectPublic): ScoreCache
    {
        $this->is_correct_public = $isCorrectPublic;
        return $this;
    }

    public function getIsCorrectPublic(): bool
    {
        return $this->is_correct_public;
    }

    public function setSolvetimePublic(float $solvetimePublic): ScoreCache
    {
        $this->solvetime_public = $solvetimePublic;
        return $this;
    }

    public function getSolvetimePublic(): float
    {
        return $this->solvetime_public;
    }

    public function setRuntimePublic(int $runtimePublic): ScoreCache
    {
        $this->runtime_public = $runtimePublic;
        return $this;
    }

    public function getRuntimePublic(): int
    {
        return $this->runtime_public;
    }

    public function setIsFirstToSolve(bool $isFirstToSolve): ScoreCache
    {
        $this->is_first_to_solve = $isFirstToSolve;
        return $this;
    }

    public function getIsFirstToSolve() : bool
    {
        return $this->is_first_to_solve;
    }

    public function setContest(?Contest $contest = null): ScoreCache
    {
        $this->contest = $contest;
        return $this;
    }

    public function getContest(): Contest
    {
        return $this->contest;
    }

    public function setTeam(?Team $team = null): ScoreCache
    {
        $this->team = $team;
        return $this;
    }

    public function getTeam(): Team
    {
        return $this->team;
    }

    public function setProblem(?Problem $problem = null): ScoreCache
    {
        $this->problem = $problem;
        return $this;
    }

    public function getProblem(): Problem
    {
        return $this->problem;
    }

    public function getSubmissions(bool $restricted): int
    {
        return $restricted ? $this->getSubmissionsRestricted() : $this->getSubmissionsPublic();
    }

    public function getPending(bool $restricted): int
    {
        return $restricted ? $this->getPendingRestricted() : $this->getPendingPublic();
    }

    public function getSolveTime(bool $restricted): float
    {
        return $restricted ? $this->getSolvetimeRestricted() : $this->getSolvetimePublic();
    }

    public function getRuntime(bool $restricted): int
    {
        return $restricted ? $this->getRuntimeRestricted() : $this->getRuntimePublic();
    }

    public function getIsCorrect(bool $restricted): bool
    {
        return $restricted ? $this->getIsCorrectRestricted() : $this->getIsCorrectPublic();
    }
}
