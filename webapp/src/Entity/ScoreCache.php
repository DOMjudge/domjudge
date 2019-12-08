<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Scoreboard cache
 * @ORM\Entity()
 * @ORM\Table(
 *     name="scorecache",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Scoreboard cache"},
 *     indexes={
 *         @ORM\Index(name="cid", columns={"cid"}),
 *         @ORM\Index(name="teamid", columns={"teamid"}),
 *         @ORM\Index(name="probid", columns={"probid"}),
 *     })
 */
class ScoreCache
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="submissions_restricted", length=4,
     *     options={"comment"="Number of submissions made (restricted audiences)",
     *              "unsigned"=true,"default"="0"},
     *     nullable=false)
     */
    private $submissions_restricted = 0;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="pending_restricted", length=4,
     *     options={"comment"="Number of submissions pending judgement (restricted audience)",
     *              "unsigned"=true,"default"="0"},
     *     nullable=false)
     */
    private $pending_restricted = 0;

    /**
     * @var boolean
     *
     * @ORM\Column(type="boolean", name="is_correct_restricted",
     *     options={"comment"="Has there been a correct submission? (restricted audience)",
     *              "default"="0"},
     *     nullable=false)
     */
    private $is_correct_restricted = false;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="solvetime_restricted",
     *     options={"comment"="Seconds into contest when problem solved (restricted audience)",
     *              "default"="0"},
     *     nullable=false)
     */
    private $solvetime_restricted = 0;


    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="submissions_public", length=4,
     *     options={"comment"="Number of submissions made (public)",
     *              "unsigned"=true,"default"="0"},
     *     nullable=false)
     */
    private $submissions_public = 0;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="pending_public", length=4,
     *     options={"comment"="Number of submissions pending judgement (public)",
     *              "unsigned"=true,"default"="0"},
     *     nullable=false)
     */
    private $pending_public = 0;

    /**
     * @var boolean
     *
     * @ORM\Column(type="boolean", name="is_correct_public",
     *     options={"comment"="Has there been a correct submission? (public)",
     *              "default"="0"},
     *     nullable=false)
     */
    private $is_correct_public = false;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="solvetime_public",
     *     options={"comment"="Seconds into contest when problem solved (public)",
     *              "default"="0"},
     *     nullable=false)
     */
    private $solvetime_public = 0;

    /**
     * @var boolean
     *
     * @ORM\Column(type="boolean", name="is_first_to_solve",
     *     options={"comment"="Is this the first solution to this problem?",
     *              "default"="0"},
     *     nullable=false)
     */
    private $is_first_to_solve = false;

    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="Contest")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="CASCADE")
     */
    private $contest;

    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="Team")
     * @ORM\JoinColumn(name="teamid", referencedColumnName="teamid", onDelete="CASCADE")
     */
    private $team;

    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="Problem")
     * @ORM\JoinColumn(name="probid", referencedColumnName="probid", onDelete="CASCADE")
     */
    private $problem;

    /**
     * Set submissionsRestricted
     *
     * @param integer $submissionsRestricted
     *
     * @return ScoreCache
     */
    public function setSubmissionsRestricted($submissionsRestricted)
    {
        $this->submissions_restricted = $submissionsRestricted;

        return $this;
    }

    /**
     * Get submissionsRestricted
     *
     * @return integer
     */
    public function getSubmissionsRestricted()
    {
        return $this->submissions_restricted;
    }

    /**
     * Set pendingRestricted
     *
     * @param integer $pendingRestricted
     *
     * @return ScoreCache
     */
    public function setPendingRestricted($pendingRestricted)
    {
        $this->pending_restricted = $pendingRestricted;

        return $this;
    }

    /**
     * Get pendingRestricted
     *
     * @return integer
     */
    public function getPendingRestricted()
    {
        return $this->pending_restricted;
    }

    /**
     * Set isCorrectRestricted
     *
     * @param boolean $isCorrectRestricted
     *
     * @return ScoreCache
     */
    public function setIsCorrectRestricted($isCorrectRestricted)
    {
        $this->is_correct_restricted = $isCorrectRestricted;

        return $this;
    }

    /**
     * Get isCorrectRestricted
     *
     * @return boolean
     */
    public function getIsCorrectRestricted()
    {
        return $this->is_correct_restricted;
    }

    /**
     * Set solvetimeRestricted
     *
     * @param double $solvetimeRestricted
     *
     * @return ScoreCache
     */
    public function setSolvetimeRestricted($solvetimeRestricted)
    {
        $this->solvetime_restricted = $solvetimeRestricted;

        return $this;
    }

    /**
     * Get solvetimeRestricted
     *
     * @return string
     */
    public function getSolvetimeRestricted()
    {
        return $this->solvetime_restricted;
    }

    /**
     * Set submissionsPublic
     *
     * @param integer $submissionsPublic
     *
     * @return ScoreCache
     */
    public function setSubmissionsPublic($submissionsPublic)
    {
        $this->submissions_public = $submissionsPublic;

        return $this;
    }

    /**
     * Get submissionsPublic
     *
     * @return integer
     */
    public function getSubmissionsPublic()
    {
        return $this->submissions_public;
    }

    /**
     * Set pendingPublic
     *
     * @param integer $pendingPublic
     *
     * @return ScoreCache
     */
    public function setPendingPublic($pendingPublic)
    {
        $this->pending_public = $pendingPublic;

        return $this;
    }

    /**
     * Get pendingPublic
     *
     * @return integer
     */
    public function getPendingPublic()
    {
        return $this->pending_public;
    }

    /**
     * Set isCorrectPublic
     *
     * @param boolean $isCorrectPublic
     *
     * @return ScoreCache
     */
    public function setIsCorrectPublic($isCorrectPublic)
    {
        $this->is_correct_public = $isCorrectPublic;

        return $this;
    }

    /**
     * Get isCorrectPublic
     *
     * @return boolean
     */
    public function getIsCorrectPublic()
    {
        return $this->is_correct_public;
    }

    /**
     * Set solvetimePublic
     *
     * @param double $solvetimePublic
     *
     * @return ScoreCache
     */
    public function setSolvetimePublic($solvetimePublic)
    {
        $this->solvetime_public = $solvetimePublic;

        return $this;
    }

    /**
     * Get solvetimePublic
     *
     * @return string
     */
    public function getSolvetimePublic()
    {
        return $this->solvetime_public;
    }

    /**
     * Set isFirstToSolve
     *
     * @param boolean $isFirstToSolve
     *
     * @return ScoreCache
     */
    public function setIsFirstToSolve(bool $isFirstToSolve)
    {
        $this->is_first_to_solve = $isFirstToSolve;

        return $this;
    }

    /**
     * Get isFirstToSolve
     *
     * @return boolean
     */
    public function getIsFirstToSolve() : bool
    {
        return $this->is_first_to_solve;
    }

    /**
     * Set contest
     *
     * @param \App\Entity\Contest $contest
     *
     * @return ScoreCache
     */
    public function setContest(\App\Entity\Contest $contest = null)
    {
        $this->contest = $contest;

        return $this;
    }

    /**
     * Get contest
     *
     * @return \App\Entity\Contest
     */
    public function getContest()
    {
        return $this->contest;
    }

    /**
     * Set team
     *
     * @param \App\Entity\Team $team
     *
     * @return ScoreCache
     */
    public function setTeam(\App\Entity\Team $team = null)
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Get team
     *
     * @return \App\Entity\Team
     */
    public function getTeam()
    {
        return $this->team;
    }

    /**
     * Set problem
     *
     * @param \App\Entity\Problem $problem
     *
     * @return ScoreCache
     */
    public function setProblem(\App\Entity\Problem $problem = null)
    {
        $this->problem = $problem;

        return $this;
    }

    /**
     * Get problem
     *
     * @return \App\Entity\Problem
     */
    public function getProblem()
    {
        return $this->problem;
    }

    /**
     * Get the number of public or restricted submissions based on the parameter
     * @param bool $restricted
     * @return int
     */
    public function getSubmissions(bool $restricted): int
    {
        return $restricted ? $this->getSubmissionsRestricted() : $this->getSubmissionsPublic();
    }

    /**
     * Get the number of public or restricted pending submissions based on the parameter
     * @param bool $restricted
     * @return int
     */
    public function getPending(bool $restricted): int
    {
        return $restricted ? $this->getPendingRestricted() : $this->getPendingPublic();
    }

    /**
     * Get the public or restricted solve time based on the parameter
     * @param bool $restricted
     * @return float|string
     */
    public function getSolveTime(bool $restricted)
    {
        return $restricted ? $this->getSolvetimeRestricted() : $this->getSolvetimePublic();
    }

    /**
     * Get whether the problem is publicly or restrictedly correct based on the parameter
     * @param bool $restricted
     * @return bool
     */
    public function getIsCorrect(bool $restricted): bool
    {
        return $restricted ? $this->getIsCorrectRestricted() : $this->getIsCorrectPublic();
    }
}
