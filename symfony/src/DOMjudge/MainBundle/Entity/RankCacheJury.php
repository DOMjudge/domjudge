<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * RankCacheJury
 *
 * @ORM\Table(name="rankcache_jury", indexes={@ORM\Index(name="order", columns={"cid", "points", "totaltime"})})
 * @ORM\Entity
 */
class RankCacheJury
{
    /**
     * @var integer
     *
     * @ORM\Column(name="cid", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $cid;

    /**
     * @var integer
     *
     * @ORM\Column(name="teamid", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $teamid;

    /**
     * @var integer
     *
     * @ORM\Column(name="points", type="integer", nullable=false)
     */
    private $points;

    /**
     * @var integer
     *
     * @ORM\Column(name="totaltime", type="integer", nullable=false)
     */
    private $totalTime;

    /**
     * @var \DOMjudge\MainBundle\Entity\Contest
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Contest")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="cid", referencedColumnName="cid")
     * })
     */
    private $contest;

    /**
     * @var \DOMjudge\MainBundle\Entity\Team
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Team")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="teamid", referencedColumnName="teamid")
     * })
     */
    private $team;


}
