<?php

namespace DOMjudge\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ScoreCachePublic
 *
 * @ORM\Table(name="scorecache_public")
 * @ORM\Entity
 */
class ScoreCachePublic
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
     * @ORM\Column(name="probid", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $probid;

    /**
     * @var integer
     *
     * @ORM\Column(name="submissions", type="integer", nullable=false)
     */
    private $submissions;

    /**
     * @var integer
     *
     * @ORM\Column(name="pending", type="integer", nullable=false)
     */
    private $pending;

    /**
     * @var integer
     *
     * @ORM\Column(name="totaltime", type="integer", nullable=false)
     */
    private $totalTime;

    /**
     * @var boolean
     *
     * @ORM\Column(name="is_correct", type="boolean", nullable=false)
     */
    private $isCorrect;

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

    /**
     * @var \DOMjudge\MainBundle\Entity\Problem
     *
     * @ORM\ManyToOne(targetEntity="DOMjudge\MainBundle\Entity\Problem")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="probid", referencedColumnName="probid")
     * })
     */
    private $problem;


}
