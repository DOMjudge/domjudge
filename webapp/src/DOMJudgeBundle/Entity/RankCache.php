<?php
namespace DOMJudgeBundle\Entity;

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
     * @ORM\Id
     * @ORM\Column(type="integer", name="cid", options={"comment"="Contest ID"}, nullable=false)
     */
    private $cid;

    /**
     * @var int
     * @ORM\Id
     * @ORM\Column(type="integer", name="teamid", options={"comment"="Team ID"}, nullable=false)
     */
    private $teamid;


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
     * @ORM\ManyToOne(targetEntity="Contest", inversedBy="rankcache")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid")
     */
    private $contest;

    /**
     * @ORM\ManyToOne(targetEntity="Team", inversedBy="rankcache")
     * @ORM\JoinColumn(name="teamid", referencedColumnName="teamid")
     */
    private $team;

    /**
     * Set cid
     *
     * @param integer $cid
     *
     * @return RankCache
     */
    public function setCid($cid)
    {
        $this->cid = $cid;

        return $this;
    }

    /**
     * Get cid
     *
     * @return integer
     */
    public function getCid()
    {
        return $this->cid;
    }

    /**
     * Set teamid
     *
     * @param integer $teamid
     *
     * @return RankCache
     */
    public function setTeamid($teamid)
    {
        $this->teamid = $teamid;

        return $this;
    }

    /**
     * Get teamid
     *
     * @return integer
     */
    public function getTeamid()
    {
        return $this->teamid;
    }

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
     * @param \DOMJudgeBundle\Entity\Contest $contest
     *
     * @return RankCache
     */
    public function setContest(\DOMJudgeBundle\Entity\Contest $contest = null)
    {
        $this->contest = $contest;

        return $this;
    }

    /**
     * Get contest
     *
     * @return \DOMJudgeBundle\Entity\Contest
     */
    public function getContest()
    {
        return $this->contest;
    }

    /**
     * Set team
     *
     * @param \DOMJudgeBundle\Entity\Team $team
     *
     * @return RankCache
     */
    public function setTeam(\DOMJudgeBundle\Entity\Team $team = null)
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Get team
     *
     * @return \DOMJudgeBundle\Entity\Team
     */
    public function getTeam()
    {
        return $this->team;
    }
}
