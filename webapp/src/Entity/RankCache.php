<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Scoreboard cache rank.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="rankcache",
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Scoreboard rank cache"},
 *     indexes={
 *         @ORM\Index(name="order_restricted", columns={"cid","points_restricted","totaltime_restricted"}),
 *         @ORM\Index(name="order_public", columns={"cid","points_public","totaltime_public"}),
 *         @ORM\Index(name="cid", columns={"cid"}),
 *         @ORM\Index(name="teamid", columns={"teamid"})
 *     })
 */
class RankCache
{
    /**
     * @ORM\Column(type="integer", name="points_restricted", length=4,
     *     options={"comment"="Total correctness points (restricted audience)",
     *              "unsigned"=true,"default"="0"},
     *     nullable=false)
     */
    private int $points_restricted = 0;

    /**
     * @ORM\Column(type="integer", name="totaltime_restricted", length=4,
     *     options={"comment"="Total penalty time in minutes (restricted audience)",
     *              "default"="0"},
     *     nullable=false)
     */
    private int $totaltime_restricted = 0;

    /**
     * @ORM\Column(type="integer", name="points_public", length=4,
     *     options={"comment"="Total correctness points (public)",
     *              "unsigned"=true,"default"="0"},
     *     nullable=false)
     */
    private int $points_public = 0;

    /**
     * @ORM\Column(type="integer", name="totaltime_public", length=4,
     *     options={"comment"="Total penalty time in minutes (public)",
     *              "default"="0"},
     *     nullable=false)
     */
    private int $totaltime_public = 0;


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

    public function setPointsRestricted(int $pointsRestricted): RankCache
    {
        $this->points_restricted = $pointsRestricted;
        return $this;
    }

    public function getPointsRestricted(): int
    {
        return $this->points_restricted;
    }

    public function setTotaltimeRestricted(int $totaltimeRestricted): RankCache
    {
        $this->totaltime_restricted = $totaltimeRestricted;
        return $this;
    }

    public function getTotaltimeRestricted(): int
    {
        return $this->totaltime_restricted;
    }

    public function setPointsPublic(int $pointsPublic): RankCache
    {
        $this->points_public = $pointsPublic;
        return $this;
    }

    public function getPointsPublic(): int
    {
        return $this->points_public;
    }

    public function setTotaltimePublic(int $totaltimePublic): RankCache
    {
        $this->totaltime_public = $totaltimePublic;
        return $this;
    }

    public function getTotaltimePublic(): int
    {
        return $this->totaltime_public;
    }

    public function setContest(?Contest $contest = null): RankCache
    {
        $this->contest = $contest;
        return $this;
    }

    public function getContest(): Contest
    {
        return $this->contest;
    }

    public function setTeam(?Team $team = null): RankCache
    {
        $this->team = $team;
        return $this;
    }

    public function getTeam(): Team
    {
        return $this->team;
    }
}
