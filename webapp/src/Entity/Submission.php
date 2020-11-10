<?php declare(strict_types=1);

namespace App\Entity;

use App\Utils\Utils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * All incoming submissions
 * @ORM\Entity()
 * @ORM\Table(
 *     name="submission",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4","comment"="All incoming submissions"},
 *     indexes={
 *         @ORM\Index(name="teamid", columns={"cid","teamid"}),
 *         @ORM\Index(name="judgehost", columns={"cid","judgehost"}),
 *         @ORM\Index(name="teamid_2", columns={"teamid"}),
 *         @ORM\Index(name="probid", columns={"probid"}),
 *         @ORM\Index(name="langid", columns={"langid"}),
 *         @ORM\Index(name="judgehost_2", columns={"judgehost"}),
 *         @ORM\Index(name="origsubmitid", columns={"origsubmitid"}),
 *         @ORM\Index(name="rejudgingid", columns={"rejudgingid"}),
 *         @ORM\Index(name="probid_2", columns={"cid","probid"})
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="externalid", columns={"cid", "externalid"}, options={"lengths": {null, 190}}),
 *     })
 * @UniqueEntity("externalid")
 */
class Submission extends BaseApiEntity implements ExternalRelationshipEntityInterface
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", length=4, name="submitid",
     *     options={"comment"="Submission ID","unsigned"=true},
     *     nullable=false)
     * @Serializer\SerializedName("id")
     * @Serializer\Type("string")
     */
    protected $submitid;

    /**
     * @var string
     * @ORM\Column(type="string", name="externalid", length=255,
     *     options={"comment"="Specifies ID of submission if imported from external CCS, e.g. Kattis",
     *              "collation"="utf8mb4_bin"},
     *     nullable=true)
     */
    protected $externalid;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="submittime", options={"comment"="Time submitted",
     *                             "unsigned"=true}, nullable=false)
     * @Serializer\Exclude()
     */
    private $submittime;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="valid",
     *     options={"comment"="If false ignore this submission in all scoreboard calculations",
     *              "default"="1"},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $valid = true;

    /**
     * @var array
     * @ORM\Column(type="json", name="expected_results", length=255,
     *     options={"comment"="JSON encoded list of expected results - used to validate jury submissions"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $expected_results;

    /**
     * @var string
     * @ORM\Column(type="string", name="entry_point", length=255,
     *     options={"comment"="Optional entry point. Can be used e.g. for java main class."},
     *     nullable=true)
     * @Serializer\Expose(if="context.getAttribute('domjudge_service').checkrole('jury')")
     */
    private $entry_point;

    /**
     * @var Judgehost|null
     *
     * @ORM\ManyToOne(targetEntity="Judgehost")
     * @ORM\JoinColumn(name="judgehost", referencedColumnName="hostname", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private $judgehost;

    /**
     * @ORM\ManyToOne(targetEntity="Contest", inversedBy="submissions")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private $contest;

    /**
     * @ORM\ManyToOne(targetEntity="Language", inversedBy="submissions")
     * @ORM\JoinColumn(name="langid", referencedColumnName="langid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private $language;

    /**
     * @ORM\ManyToOne(targetEntity="Team", inversedBy="submissions")
     * @ORM\JoinColumn(name="teamid", referencedColumnName="teamid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private $team;

    /**
     * @ORM\ManyToOne(targetEntity="Problem", inversedBy="submissions")
     * @ORM\JoinColumn(name="probid", referencedColumnName="probid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private $problem;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ContestProblem", inversedBy="submissions")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="CASCADE"),
     *   @ORM\JoinColumn(name="probid", referencedColumnName="probid", onDelete="CASCADE")
     * })
     * @Serializer\Exclude()
     */
    private $contest_problem;

    /**
     * @ORM\OneToMany(targetEntity="Judging", mappedBy="submission")
     * @Serializer\Exclude()
     */
    private $judgings;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ExternalJudgement", mappedBy="submission")
     * @Serializer\Exclude()
     */
    private $external_judgements;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="SubmissionFile", mappedBy="submission")
     * @Serializer\Exclude()
     */
    private $files;

    /**
     * @ORM\OneToMany(targetEntity="Balloon", mappedBy="submission")
     * @Serializer\Exclude()
     */
    private $balloons;

    /**
     * rejudgings have one parent judging
     * @ORM\ManyToOne(targetEntity="Rejudging", inversedBy="submissions")
     * @ORM\JoinColumn(name="rejudgingid", referencedColumnName="rejudgingid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private $rejudging;

    /**
     * @var Submission|null
     * @ORM\ManyToOne(targetEntity="App\Entity\Submission", inversedBy="resubmissions")
     * @ORM\JoinColumn(name="origsubmitid", referencedColumnName="submitid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private $originalSubmission;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="App\Entity\Submission", mappedBy="originalSubmission")
     * @Serializer\Exclude()
     */
    private $resubmissions;

    /**
     * @var string Holds the old result in the case this submission is displayed in a rejudging table
     * @Serializer\Exclude()
     */
    private $old_result;

    public function getResult()
    {
        foreach ($this->judgings as $j) {
            if ($j->getValid()) {
                return $j->getResult();
            }
        }
        return null;
    }

    /**
     * Get submitid
     *
     * @return integer
     */
    public function getSubmitid()
    {
        return $this->submitid;
    }

    /**
     * Set externalid
     *
     * @param string $externalid
     *
     * @return Submission
     */
    public function setExternalid($externalid)
    {
        $this->externalid = $externalid;

        return $this;
    }

    /**
     * Get externalid
     *
     * @return string
     */
    public function getExternalid()
    {
        return $this->externalid;
    }

    /**
     * Get the language ID
     * @return string
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("language_id")
     * @Serializer\Type("string")
     */
    public function getLanguageId()
    {
        return $this->getLanguage()->getExternalid();
    }

    /**
     * Set submittime
     *
     * @param string $submittime
     *
     * @return Submission
     */
    public function setSubmittime($submittime)
    {
        $this->submittime = $submittime;

        return $this;
    }

    /**
     * Get submittime
     *
     * @return string
     */
    public function getSubmittime()
    {
        return $this->submittime;
    }

    /**
     * Get the absolute submit time for this submission
     *
     * @return string
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("time")
     * @Serializer\Type("string")
     */
    public function getAbsoluteSubmitTime()
    {
        return Utils::absTime($this->getSubmittime());
    }

    /**
     * Get the relative submit time for this submission
     *
     * @return string
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("contest_time")
     * @Serializer\Type("string")
     */
    public function getRelativeSubmitTime()
    {
        return Utils::relTime($this->getContest()->getContestTime((float)$this->getSubmittime()));
    }

    /**
     * Set judgehost
     *
     * @param Judgehost|null $judgehost
     *
     * @return Submission
     */
    public function setJudgehost($judgehost)
    {
        $this->judgehost = $judgehost;

        return $this;
    }

    /**
     * Get judgehost
     *
     * @return Judgehost|null
     */
    public function getJudgehost()
    {
        return $this->judgehost;
    }

    /**
     * Set valid
     *
     * @param boolean $valid
     *
     * @return Submission
     */
    public function setValid($valid)
    {
        $this->valid = $valid;

        return $this;
    }

    /**
     * Get valid
     *
     * @return boolean
     */
    public function getValid()
    {
        return $this->valid;
    }

    /**
     * Set expectedResults
     *
     * @param array $expectedResults
     *
     * @return Submission
     */
    public function setExpectedResults($expectedResults)
    {
        $this->expected_results = $expectedResults;

        return $this;
    }

    /**
     * Get expectedResults
     *
     * @return array
     */
    public function getExpectedResults()
    {
        return $this->expected_results;
    }

    /**
     * Set entry_point
     *
     * @param string $entryPoint
     *
     * @return Submission
     */
    public function setEntryPoint($entryPoint)
    {
        $this->entry_point = $entryPoint;

        return $this;
    }

    /**
     * Get entry_point
     *
     * @return string
     */
    public function getEntryPoint()
    {
        return $this->entry_point;
    }

    /**
     * Set team
     *
     * @param \App\Entity\Team $team
     *
     * @return Submission
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
     * Get the team ID
     * @return string
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("team_id")
     * @Serializer\Type("string")
     */
    public function getTeamId()
    {
        return $this->getTeam()->getTeamid();
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->judgings            = new ArrayCollection();
        $this->files               = new ArrayCollection();
        $this->resubmissions       = new ArrayCollection();
        $this->external_judgements = new ArrayCollection();
        $this->balloons = new ArrayCollection();
    }

    /**
     * Add judging
     *
     * @param Judging $judging
     *
     * @return Submission
     */
    public function addJudging(Judging $judging)
    {
        $this->judgings[] = $judging;

        return $this;
    }

    /**
     * Remove judging
     *
     * @param Judging $judging
     */
    public function removeJudging(Judging $judging)
    {
        $this->judgings->removeElement($judging);
    }

    /**
     * Get judgings
     *
     * @return Collection
     */
    public function getJudgings()
    {
        return $this->judgings;
    }

    /**
     * Set language
     *
     * @param Language $language
     *
     * @return Submission
     */
    public function setLanguage(Language $language = null)
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Get language
     *
     * @return Language
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * Add file
     *
     * @param SubmissionFile $file
     *
     * @return Submission
     */
    public function addFile(SubmissionFile $file)
    {
        $this->files->add($file);

        return $this;
    }

    /**
     * Remove file
     *
     * @param SubmissionFile $file
     */
    public function removeFile(SubmissionFile $file)
    {
        $this->files->removeElement($file);
    }

    /**
     * Get files
     *
     * @return Collection
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * Add balloon
     *
     * @param Balloon $balloon
     *
     * @return Submission
     */
    public function addBalloon(Balloon $balloon)
    {
        $this->balloons[] = $balloon;

        return $this;
    }

    /**
     * Remove balloon
     *
     * @param Balloon $balloon
     */
    public function removeBalloon(Balloon $balloon)
    {
        $this->balloons->removeElement($balloon);
    }

    /**
     * Get balloons
     *
     * @return Collection
     */
    public function getBalloons()
    {
        return $this->balloons;
    }

    /**
     * Set contest
     *
     * @param Contest $contest
     *
     * @return Submission
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
     * Set problem
     *
     * @param Problem $problem
     *
     * @return Submission
     */
    public function setProblem(Problem $problem = null)
    {
        $this->problem = $problem;

        return $this;
    }

    /**
     * Get problem
     *
     * @return Problem
     */
    public function getProblem()
    {
        return $this->problem;
    }

    /**
     * Get the problem ID
     * @return string
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("problem_id")
     * @Serializer\Type("string")
     */
    public function getProblemId()
    {
        return $this->getProblem()->getProbid();
    }

    /**
     * Set contest problem
     *
     * @param ContestProblem $contestProblem
     *
     * @return Submission
     */
    public function setContestProblem(ContestProblem $contestProblem = null)
    {
        $this->contest_problem = $contestProblem;

        return $this;
    }

    /**
     * Get contest problem
     *
     * @return ContestProblem
     */
    public function getContestProblem()
    {
        return $this->contest_problem;
    }

    /**
     * Set rejudging
     *
     * @param Rejudging $rejudging
     *
     * @return Submission
     */
    public function setRejudging(Rejudging $rejudging = null)
    {
        $this->rejudging = $rejudging;

        return $this;
    }

    /**
     * Get rejudging
     *
     * @return Rejudging
     */
    public function getRejudging()
    {
        return $this->rejudging;
    }

    /**
     * Get the entities to check for external ID's while serializing.
     *
     * This method should return an array with as keys the JSON field names and as values the actual entity
     * objects that the SetExternalIdVisitor should check for applicable external ID's
     * @return array
     */
    public function getExternalRelationships(): array
    {
        return [
            'language_id' => $this->getLanguage(),
            'problem_id' => $this->getProblem(),
            'team_id' => $this->getTeam(),
        ];
    }

    /**
     * Return whether this submission is after the freeze
     * @return bool
     */
    public function isAfterFreeze(): bool
    {
        return $this->getContest()->getFreezetime() !== null && (float)$this->getSubmittime() >= (float)$this->getContest()->getFreezetime();
    }

    /**
     * @return string
     */
    public function getOldResult(): string
    {
        return $this->old_result;
    }

    /**
     * @param string $old_result
     * @return Submission
     */
    public function setOldResult(string $old_result): Submission
    {
        $this->old_result = $old_result;
        return $this;
    }

    /**
     * Get original submission
     * @return Submission|null
     */
    public function getOriginalSubmission()
    {
        return $this->originalSubmission;
    }

    /**
     * Set original submission
     * @param Submission|null $originalSubmission
     * @return Submission
     */
    public function setOriginalSubmission($originalSubmission): Submission
    {
        $this->originalSubmission = $originalSubmission;
        return $this;
    }

    /**
     * Add resubmission
     *
     * @param Submission $submission
     * @return Submission
     */
    public function addResubmission(Submission $submission)
    {
        $this->resubmissions->add($submission);

        return $this;
    }

    /**
     * Remove resubmission
     *
     * @param Submission $submission
     * @return Submission
     */
    public function removeResubmission(Submission $submission)
    {
        $this->resubmissions->removeElement($submission);

        return $this;
    }

    /**
     * Get resubmissions
     *
     * @return Collection|Submission[]
     */
    public function getResubmissions()
    {
        return $this->resubmissions;
    }

    /**
     * Check whether this submission is for an aborted judging
     * @return bool
     */
    public function isAborted()
    {
        // This logic has been copied from putSubmissions()
        /** @var Judging|null $judging */
        $judging = $this->getJudgings()->first();
        if (!$judging) {
            return false;
        }

        return $judging->getEndtime() === null && !$judging->getValid() &&
            (!$judging->getRejudging() || !$judging->getRejudging()->getValid());
    }

    /**
     * Check whether this submission is still busy while the final result is already known,
     * e.g. with non-lazy evaluation.
     * @return bool
     */
    public function isStillBusy()
    {
        /** @var Judging|null $judging */
        $judging = $this->getJudgings()->first();
        if (!$judging) {
            return false;
        }

        return !empty($judging->getResult()) && empty($judging->getEndtime()) && !$this->isAborted();
    }

    /**
     * Add externalJudgement
     *
     * @param ExternalJudgement $externalJudgement
     *
     * @return Submission
     */
    public function addExternalJudgement(ExternalJudgement $externalJudgement)
    {
        $this->external_judgements[] = $externalJudgement;

        return $this;
    }

    /**
     * Remove externalJudgement
     *
     * @param ExternalJudgement $externalJudgement
     */
    public function removeExternalJudgement(ExternalJudgement $externalJudgement)
    {
        $this->external_judgements->removeElement($externalJudgement);
    }

    /**
     * Get externalJudgements
     *
     * @return Collection
     */
    public function getExternalJudgements()
    {
        return $this->external_judgements;
    }
}
