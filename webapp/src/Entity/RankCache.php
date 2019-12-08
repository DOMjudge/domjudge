<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Scoreboard cache rank
 * @ORM\Entity()
 * @ORM\Table(
 *     name="rankcache",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Scoreboard rank cache"},
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
     * @var int
     *
     * @ORM\Column(type="integer", name="points_restricted", length=4,
     *     options={"comment"="Total correctness points (restricted audience)",
     *              "unsigned"=true,"default"="0"},
     *     nullable=false)
     */
    private $points_restricted = 0;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="totaltime_restricted", length=4,
     *     options={"comment"="Total penalty time in minutes (restricted audience)",
     *              "default"="0"},
     *     nullable=false)
     */
    private $totaltime_restricted = 0;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="points_public", length=4,
     *     options={"comment"="Total correctness points (public)",
     *              "unsigned"=true,"default"="0"},
     *     nullable=false)
     */
    private $points_public = 0;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="totaltime_public", length=4,
     *     options={"comment"="Total penalty time in minutes (public)",
     *              "default"="0"},
     *     nullable=false)
     */
    private $totaltime_public = 0;


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
