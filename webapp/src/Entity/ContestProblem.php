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
 * Many-to-Many mapping of contests and problems.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="contestproblem",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Many-to-Many mapping of contests and problems"},
 *     indexes={
 *         @ORM\Index(name="cid", columns={"cid"}),
 *         @ORM\Index(name="probid", columns={"probid"})
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="shortname", columns={"cid", "shortname"}, options={"lengths"={NULL,190}})
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
     *     options={"comment"="Balloon colour to display on the scoreboard"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $color;

    /**
     * @var boolean|null
     * @ORM\Column(type="boolean", name="lazy_eval_results",
     *     options={"comment"="Whether to do lazy evaluation for this problem; if set this overrides the global configuration setting",
     *              "unsigned"="true"},
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

    public function getCid(): int
    {
        return $this->getContest()->getCid();
    }

    public function getProbid(): int
    {
        return $this->getProblem()->getProbid();
    }

    public function setShortname(string $shortname): ContestProblem
    {
        $this->shortname = $shortname;
        return $this;
    }

    public function getShortname(): ?string
    {
        return $this->shortname;
    }

    public function getShortDescription(): ?string
    {
        return $this->getShortname();
    }

    public function setPoints(int $points): ContestProblem
    {
        $this->points = $points;
        return $this;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setAllowSubmit(bool $allowSubmit): ContestProblem
    {
        $this->allowSubmit = $allowSubmit;
        return $this;
    }

    public function getAllowSubmit(): bool
    {
        return $this->allowSubmit;
    }

    public function setAllowJudge(bool $allowJudge): ContestProblem
    {
        $this->allowJudge = $allowJudge;
        return $this;
    }

    public function getAllowJudge(): bool
    {
        return $this->allowJudge;
    }

    public function setColor(?string $color): ContestProblem
    {
        $this->color = $color;
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setLazyEvalResults(bool $lazyEvalResults): ContestProblem
    {
        $this->lazyEvalResults = $lazyEvalResults;
        return $this;
    }

    public function getLazyEvalResults(): ?bool
    {
        return $this->lazyEvalResults;
    }

    public function setContest(?Contest $contest = null): ContestProblem
    {
        $this->contest = $contest;
        return $this;
    }

    public function getContest(): Contest
    {
        return $this->contest;
    }

    public function setProblem(?Problem $problem = null): ContestProblem
    {
        $this->problem = $problem;
        return $this;
    }

    public function getProblem(): ?Problem
    {
        return $this->problem;
    }

    public function addSubmission(Submission $submission): ContestProblem
    {
        $this->submissions->add($submission);
        return $this;
    }

    public function removeSubmission(Submission $submission)
    {
        $this->submissions->removeElement($submission);
    }

    public function getSubmissions(): Collection
    {
        return $this->submissions;
    }

    public function getExternalId(): string
    {
        return $this->getProblem()->getExternalid();
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getApiId(EventLogService $eventLogService)
    {
        return $this->getProblem()->getApiId($eventLogService);
    }
}
