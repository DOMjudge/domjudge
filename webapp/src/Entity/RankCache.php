<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Scoreboard cache rank
 * @ORM\Entity()
 * @ORM\Table(name="rankcache", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class RankCache
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="points_restricted", options={"comment"="Total correctness points (restricted audiences)"}, nullable=false)
     */
    private $points_restricted;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="totaltime_restricted", options={"comment"="Total penalty points (restricted audiences)"}, nullable=false)
     */
    private $totaltime_restricted;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="points_public", options={"comment"="Total correctness points (public)"}, nullable=false)
     */
    private $points_public;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="totaltime_public", options={"comment"="Total penalty points (public)"}, nullable=false)
     */
    private $totaltime_public;


    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="Contest", inversedBy="rankcache")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid")
     */
    private $contest;

    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="Team", inversedBy="rankcache")
     * @ORM\JoinColumn(name="teamid", referencedColumnName="teamid")
     */
    private $team;

    /**
     * Set pointsRestricted
     *
     * @param integer $pointsRestricted
     *
     * @return RankCache
     */
    public function setPointsRestricted($pointsRestricted)
    {
        $this->points_restricted = $pointsRestricted;

        return $this;
    }

    /**
     * Get pointsRestricted
     *
     * @return integer
     */
    public function getPointsRestricted()
    {
        return $this->points_restricted;
    }

    /**
     * Set totaltimeRestricted
     *
     * @param integer $totaltimeRestricted
     *
     * @return RankCache
     */
    public function setTotaltimeRestricted($totaltimeRestricted)
    {
        $this->totaltime_restricted = $totaltimeRestricted;

        return $this;
    }

    /**
     * Get totaltimeRestricted
     *
     * @return integer
     */
    public function getTotaltimeRestricted()
    {
        return $this->totaltime_restricted;
    }

    /**
     * Set pointsPublic
     *
     * @param integer $pointsPublic
     *
     * @return RankCache
     */
    public function setPointsPublic($pointsPublic)
    {
        $this->points_public = $pointsPublic;

        return $this;
    }

    /**
     * Get pointsPublic
     *
     * @return integer
     */
    public function getPointsPublic()
    {
        return $this->points_public;
    }

    /**
     * Set totaltimePublic
     *
     * @param integer $totaltimePublic
     *
     * @return RankCache
     */
    public function setTotaltimePublic($totaltimePublic)
    {
        $this->totaltime_public = $totaltimePublic;

        return $this;
    }

    /**
     * Get totaltimePublic
     *
     * @return integer
     */
    public function getTotaltimePublic()
    {
        return $this->totaltime_public;
    }

    /**
     * Set contest
     *
     * @param Contest $contest
     *
     * @return RankCache
     */
    public function setContest(Contest $contest = null)
    {
        $this->contest = $contest;

        return $this;
    }

    /**
     * Get contest
     *
     * @return Contest
     */
    public function getContest()
    {
        return $this->contest;
    }

    /**
     * Set team
     *
     * @param Team $team
     *
     * @return RankCache
     */
    public function setTeam(Team $team = null)
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Get team
     *
     * @return Team
     */
    public function getTeam()
    {
        return $this->team;
    }
}
