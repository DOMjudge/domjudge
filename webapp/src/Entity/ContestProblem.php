<?php declare(strict_types=1);
namespace App\Entity;

use App\Service\EventLogService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Many-to-Many mapping of contests and problems
 * @ORM\Entity()
 * @ORM\Table(
 *     name="contestproblem",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Many-to-Many mapping of contests and problems"},
 *     indexes={
 *         @ORM\Index(name="cid", columns={"cid"}),
 *         @ORM\Index(name="probid", columns={"probid"})
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="shortname", columns={"cid", "shortname"}, options={"lengths"={NULL,"190"}})
 *     })
 * @Serializer\VirtualProperty(
 *     "id",
 *     exp="object.getProblem().getProbid()",
 *     options={@Serializer\Type("string")}
 * )
 * @Serializer\VirtualProperty(
 *     "short_name",
 *     exp="object.getShortname()",
 *     options={@Serializer\Groups("Nonstrict"), @Serializer\Type("string")}
 * )
 */
class ContestProblem
{
    /**
     * @var string
     * @ORM\Column(type="string", name="shortname", length=255,
     *     options={"comment"="Unique problem ID within contest, used to sort problems in the scoreboard and typically a single letter"},
     *     nullable=false)
     * @Serializer\SerializedName("label")
     */
    private $shortname;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="points", length=4,
     *     options={"comment"="Number of points earned by solving this problem",
     *              "unsigned"=true,"default"="1"},
     *     nullable=false)
     * @Serializer\Exclude()
     * @Assert\GreaterThanOrEqual(0)
     */
    private $points = 1;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="allow_submit",
     *     options={"comment"="Are submissions accepted for this problem?",
     *              "default"="1"},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $allowSubmit = true;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="allow_judge",
     *     options={"comment"="Are submissions for this problem judged?",
     *              "default"="1"},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $allowJudge = true;

    /**
     * @var string
     * @ORM\Column(type="string", name="color", length=32,
     *     options={"comment"="Balloon colour to display on the scoreboard",
     *              "default"="NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $color;

    /**
     * @var boolean|null
     * @ORM\Column(type="boolean", name="lazy_eval_results",
     *     options={"comment"="Whether to do lazy evaluation for this problem; if set this overrides the global configuration setting",
     *              "unsigned"="true", "default"="NULL"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $lazyEvalResults;

    /**
     * @ORM\Id()
     * @ORM\ManyToOne(targetEntity="Contest", inversedBy="problems")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private $contest;

    /**
     * @ORM\Id()
     * @ORM\ManyToOne(targetEntity="Problem", inversedBy="contest_problems", fetch="EAGER")
     * @ORM\JoinColumn(name="probid", referencedColumnName="probid", onDelete="CASCADE")
     * @Serializer\Inline()
     */
    private $problem;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="App\Entity\Submission", mappedBy="contest_problem")
     * @Serializer\Exclude()
     */
    private $submissions;

    public function __construct()
    {
        $this->submissions = new ArrayCollection();
    }

    /**
     * Get cid
     *
     * @return integer
     */
    public function getCid()
    {
        return $this->getContest()->getCid();
    }

    /**
     * Get probid
     *
     * @return integer
     */
    public function getProbid()
    {
        return $this->getProblem()->getProbid();
    }

    /**
     * Set shortname
     *
     * @param string $shortname
     *
     * @return ContestProblem
     */
    public function setShortname($shortname)
    {
        $this->shortname = $shortname;

        return $this;
    }

    /**
     * Get shortname
     *
     * @return string
     */
    public function getShortname()
    {
        return $this->shortname;
    }

    /**
     * Set points
     *
     * @param integer $points
     *
     * @return ContestProblem
     */
    public function setPoints($points)
    {
        $this->points = $points;

        return $this;
    }

    /**
     * Get points
     *
     * @return integer
     */
    public function getPoints()
    {
        return $this->points;
    }

    /**
     * Set allowSubmit
     *
     * @param boolean $allowSubmit
     *
     * @return ContestProblem
     */
    public function setAllowSubmit($allowSubmit)
    {
        $this->allowSubmit = $allowSubmit;

        return $this;
    }

    /**
     * Get allowSubmit
     *
     * @return boolean
     */
    public function getAllowSubmit()
    {
        return $this->allowSubmit;
    }

    /**
     * Set allowJudge
     *
     * @param boolean $allowJudge
     *
     * @return ContestProblem
     */
    public function setAllowJudge($allowJudge)
    {
        $this->allowJudge = $allowJudge;

        return $this;
    }

    /**
     * Get allowJudge
     *
     * @return boolean
     */
    public function getAllowJudge()
    {
        return $this->allowJudge;
    }

    /**
     * Set color
     *
     * @param string $color
     *
     * @return ContestProblem
     */
    public function setColor($color)
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Get color
     *
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * Set lazyEvalResults
     *
     * @param boolean $lazyEvalResults
     *
     * @return ContestProblem
     */
    public function setLazyEvalResults($lazyEvalResults)
    {
        $this->lazyEvalResults = $lazyEvalResults;

        return $this;
    }

    /**
     * Get lazyEvalResults
     *
     * @return boolean
     */
    public function getLazyEvalResults()
    {
        return $this->lazyEvalResults;
    }

    /**
     * Set contest
     *
     * @param \App\Entity\Contest $contest
     *
     * @return ContestProblem
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
     * Set problem
     *
     * @param \App\Entity\Problem $problem
     *
     * @return ContestProblem
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
     * Add submission
     *
     * @param Submission $submission
     *
     * @return ContestProblem
     */
    public function addSubmission(Submission $submission)
    {
        $this->submissions->add($submission);

        return $this;
    }

    /**
     * Remove submission
     *
     * @param Submission $submission
     */
    public function removeSubmission(Submission $submission)
    {
        $this->submissions->removeElement($submission);
    }

    /**
     * Get submissions
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getSubmissions()
    {
        return $this->submissions;
    }

    /**
     * Get externalid
     *
     * @return string
     */
    public function getExternalId()
    {
        return $this->getProblem()->getExternalid();
    }

    /**
     * Get the API ID for this entity
     * @param EventLogService        $eventLogService
     * @param EntityManagerInterface $entityManager
     * @return mixed
     * @throws Exception
     */
    public function getApiId(EventLogService $eventLogService)
    {
        return $this->getProblem()->getApiId($eventLogService);
    }
}
