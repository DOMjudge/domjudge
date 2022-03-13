<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Scoreboard cache.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="scorecache",
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Scoreboard cache"},
 *     indexes={
 *         @ORM\Index(name="cid", columns={"cid"}),
 *         @ORM\Index(name="teamid", columns={"teamid"}),
 *         @ORM\Index(name="probid", columns={"probid"}),
 *     })
 */
class ScoreCache
{
    /**
     * @ORM\Column(type="integer", name="submissions_restricted", length=4,
     *     options={"comment"="Number of submissions made (restricted audiences)",
     *              "unsigned"=true,"default"="0"},
     *     nullable=false)
     */
    private int $submissions_restricted = 0;

    /**
     * @ORM\Column(type="integer", name="pending_restricted", length=4,
     *     options={"comment"="Number of submissions pending judgement (restricted audience)",
     *              "unsigned"=true,"default"="0"},
     *     nullable=false)
     */
    private int $pending_restricted = 0;

    /**
     * @ORM\Column(type="boolean", name="is_correct_restricted",
     *     options={"comment"="Has there been a correct submission? (restricted audience)",
     *              "default"="0"},
     *     nullable=false)
     */
    private bool $is_correct_restricted = false;

    /**
     * @var double|string
     * @ORM\Column(type="decimal", precision=32, scale=9, name="solvetime_restricted",
     *     options={"comment"="Seconds into contest when problem solved (restricted audience)",
     *              "default"="0.000000000"},
     *     nullable=false)
     */
    private $solvetime_restricted = 0;


    /**
     * @ORM\Column(type="integer", name="submissions_public", length=4,
     *     options={"comment"="Number of submissions made (public)",
     *              "unsigned"=true,"default"="0"},
     *     nullable=false)
     */
    private int $submissions_public = 0;

    /**
     * @ORM\Column(type="integer", name="pending_public", length=4,
     *     options={"comment"="Number of submissions pending judgement (public)",
     *              "unsigned"=true,"default"="0"},
     *     nullable=false)
     */
    private int $pending_public = 0;

    /**
     * @ORM\Column(type="boolean", name="is_correct_public",
     *     options={"comment"="Has there been a correct submission? (public)",
     *              "default"="0"},
     *     nullable=false)
     */
    private bool $is_correct_public = false;

    /**
     * @var double|string
     * @ORM\Column(type="decimal", precision=32, scale=9, name="solvetime_public",
     *     options={"comment"="Seconds into contest when problem solved (public)",
     *              "default"="0.000000000"},
     *     nullable=false)
     */
    private $solvetime_public = 0;

    /**
     * @ORM\Column(type="boolean", name="is_first_to_solve",
     *     options={"comment"="Is this the first solution to this problem?",
     *              "default"="0"},
     *     nullable=false)
     */
    private bool $is_first_to_solve = false;

    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="Contest")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="CASCADE")
     */
    private Contest $contest;

    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="Team")
     * @ORM\JoinColumn(name="teamid", referencedColumnName="teamid", onDelete="CASCADE")
     */
    private Team $team;

    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="Problem")
     * @ORM\JoinColumn(name="probid", referencedColumnName="probid", onDelete="CASCADE")
     */
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

    /** @param string|float $solvetimeRestricted */
    public function setSolvetimeRestricted($solvetimeRestricted): ScoreCache
    {
        $this->solvetime_restricted = $solvetimeRestricted;
        return $this;
    }

    /** @return string|float */
    public function getSolvetimeRestricted()
    {
        return $this->solvetime_restricted;
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

    /** @param string|float $solvetimePublic */
    public function setSolvetimePublic($solvetimePublic): ScoreCache
    {
        $this->solvetime_public = $solvetimePublic;
        return $this;
    }

    /** @return string|float */
    public function getSolvetimePublic()
    {
        return $this->solvetime_public;
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

    /** @return string|float */
    public function getSolveTime(bool $restricted)
    {
        return $restricted ? $this->getSolvetimeRestricted() : $this->getSolvetimePublic();
    }

    public function getIsCorrect(bool $restricted): bool
    {
        return $restricted ? $this->getIsCorrectRestricted() : $this->getIsCorrectPublic();
    }
}
