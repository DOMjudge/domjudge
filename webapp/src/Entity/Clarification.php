<?php declare(strict_types=1);
namespace App\Entity;

use App\Utils\Utils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use OpenApi\Annotations as OA;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Clarification requests by teams and responses by the jury.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="clarification",
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Clarification requests by teams and responses by the jury"},
 *     indexes={
 *         @ORM\Index(name="respid", columns={"respid"}),
 *         @ORM\Index(name="probid", columns={"probid"}),
 *         @ORM\Index(name="cid", columns={"cid"}),
 *         @ORM\Index(name="cid_2", columns={"cid","answered","submittime"}),
 *         @ORM\Index(name="sender", columns={"sender"}),
 *         @ORM\Index(name="recipient", columns={"recipient"})
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="externalid", columns={"cid", "externalid"}, options={"lengths": {null, 190}})
 *     })
 * @UniqueEntity("externalid")
 */
class Clarification extends BaseApiEntity implements ExternalRelationshipEntityInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", length=4, name="clarid",
     *     options={"comment"="Clarification ID","unsigned"=true},
     *     nullable=false)
     * @Serializer\SerializedName("id")
     * @Serializer\Type("string")
     */
    protected int $clarid;

    /**
     * @ORM\Column(type="string", name="externalid", length=255,
     *     options={"comment"="Clarification ID in an external system, should be unique inside a single contest",
     *              "collation"="utf8mb4_bin"},
     *     nullable=true)
     * @OA\Property(nullable=true)
     */
    protected ?string $externalid = null;

    /**
     * @var double|string
     * @ORM\Column(type="decimal", precision=32, scale=9, name="submittime", options={"comment"="Time sent", "unsigned"=true}, nullable=false)
     * @Serializer\Exclude()
     */
    private $submittime;

    /**
     * @ORM\Column(type="string", name="jury_member", length=255,
     *     options={"comment"="Name of jury member who answered this"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private ?string $jury_member;

    /**
     * @ORM\Column(type="string", name="category", length=255,
     *     options={"comment"="Category associated to this clarification; only set for non problem clars"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private ?string $category;

    /**
     * @ORM\Column(type="string", name="queue", length=255,
     *     options={"comment"="Queue associated to this clarification"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private ?string $queue;

    /**
     * @ORM\Column(type="text", length=4294967295, name="body",
     *     options={"comment"="Clarification text"},
     *     nullable=false)
     * @Serializer\SerializedName("text")
     */
    private string $body;

    /**
     * @ORM\Column(type="boolean", name="answered",
     *     options={"comment"="Has been answered by jury?","default":"0"},
     *     nullable=false)
     * @Serializer\Groups({"RestrictedNonstrict"})
     */
    private bool $answered = false;

    /**
     * @ORM\ManyToOne(targetEntity="Problem", inversedBy="clarifications")
     * @ORM\JoinColumn(name="probid", referencedColumnName="probid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private ?Problem $problem = null;

    /**
     * @ORM\ManyToOne(targetEntity="Contest", inversedBy="clarifications")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private Contest $contest;

    /**
     * @ORM\ManyToOne(targetEntity="Clarification", inversedBy="replies")
     * @ORM\JoinColumn(name="respid", referencedColumnName="clarid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private ?Clarification $in_reply_to = null;

    /**
     * @ORM\OneToMany(targetEntity="Clarification", mappedBy="in_reply_to")
     * @Serializer\Exclude()
     */
    private Collection $replies;

    /**
     * @ORM\ManyToOne(targetEntity="Team", inversedBy="sent_clarifications")
     * @ORM\JoinColumn(name="sender", referencedColumnName="teamid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private ?Team $sender = null;

    /**
     * @ORM\ManyToOne(targetEntity="Team", inversedBy="received_clarifications")
     * @ORM\JoinColumn(name="recipient", referencedColumnName="teamid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private ?Team $recipient = null;

    public function __construct()
    {
        $this->replies = new ArrayCollection();
    }

    public function setClarid(int $clarid): Clarification
    {
        $this->clarid = $clarid;
        return $this;
    }

    public function getClarid(): int
    {
        return $this->clarid;
    }

    public function setExternalid(?string $externalid): Clarification
    {
        $this->externalid = $externalid;
        return $this;
    }

    public function getExternalid(): ?string
    {
        return $this->externalid;
    }

    /** @param string|float $submittime */
    public function setSubmittime($submittime): Clarification
    {
        $this->submittime = $submittime;
        return $this;
    }

    /** @return string|float */
    public function getSubmittime()
    {
        return $this->submittime;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("time")
     * @Serializer\Type("string")
     */
    public function getAbsoluteSubmitTime(): string
    {
        return Utils::absTime($this->getSubmittime());
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("contest_time")
     * @Serializer\Type("string")
     */
    public function getRelativeSubmitTime(): string
    {
        return Utils::relTime($this->getSubmittime() - $this->getContest()->getStarttime());
    }

    public function setJuryMember(?string $juryMember): Clarification
    {
        $this->jury_member = $juryMember;
        return $this;
    }

    public function getJuryMember(): ?string
    {
        return $this->jury_member;
    }

    public function setCategory(?string $category): Clarification
    {
        $this->category = $category;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setQueue(?string $queue): Clarification
    {
        $this->queue = $queue;
        return $this;
    }

    public function getQueue(): ?string
    {
        return $this->queue;
    }

    public function setBody(string $body): Clarification
    {
        $this->body = $body;
        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setAnswered(bool $answered): Clarification
    {
        $this->answered = $answered;
        return $this;
    }

    public function getAnswered(): bool
    {
        return $this->answered;
    }

    public function setProblem(?Problem $problem): Clarification
    {
        $this->problem = $problem;
        return $this;
    }

    public function getProblem(): ?Problem
    {
        return $this->problem;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("problem_id")
     * @Serializer\Type("string")
     * @OA\Property(nullable=true)
     */
    public function getProblemId(): ?int
    {
        return $this->getProblem() ? $this->getProblem()->getProbid() : null;
    }

    public function setContest(?Contest $contest = null): Clarification
    {
        $this->contest = $contest;
        return $this;
    }

    public function getContest(): Contest
    {
        return $this->contest;
    }

    public function setInReplyTo(?Clarification $inReplyTo = null): Clarification
    {
        $this->in_reply_to = $inReplyTo;
        return $this;
    }

    public function getInReplyTo(): ?Clarification
    {
        return $this->in_reply_to;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("reply_to_id")
     * @Serializer\Type("string")
     * @OA\Property(nullable=true)
     */
    public function getInReplyToId(): ?int
    {
        return $this->getInReplyTo() ? $this->getInReplyTo()->getClarid() : null;
    }

    public function addReply(Clarification $reply): Clarification
    {
        $this->replies[] = $reply;
        return $this;
    }

    public function removeReply(Clarification $reply)
    {
        $this->replies->removeElement($reply);
    }

    public function getReplies(): Collection
    {
        return $this->replies;
    }

    public function setSender(?Team $sender = null): Clarification
    {
        $this->sender = $sender;
        return $this;
    }

    public function getSender(): ?Team
    {
        return $this->sender;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("from_team_id")
     * @Serializer\Type("string")
     * @OA\Property(nullable=true)
     */
    public function getSenderId(): ?int
    {
        return $this->getSender() ? $this->getSender()->getTeamid() : null;
    }

    public function setRecipient(?Team $recipient = null): Clarification
    {
        $this->recipient = $recipient;
        return $this;
    }

    public function getRecipient(): ?Team
    {
        return $this->recipient;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("to_team_id")
     * @Serializer\Type("string")
     * @OA\Property(nullable=true)
     */
    public function getRecipientId(): ?int
    {
        return $this->getRecipient() ? $this->getRecipient()->getTeamid() : null;
    }

    /**
     * Get the entities to check for external ID's while serializing.
     *
     * This method should return an array with as keys the JSON field names and as values the actual entity
     * objects that the SetExternalIdVisitor should check for applicable external ID's
     */
    public function getExternalRelationships(): array
    {
        return [
            'from_team_id' => $this->getSender(),
            'to_team_id'   => $this->getRecipient(),
            'problem_id'   => $this->getProblem(),
            'reply_to_id'  => $this->getInReplyTo()
        ];
    }

    public function getSummary(): string
    {
        // When compiling a summary, try to ignore the quoted text, and replace newlines by spaces.
        $split = explode("\n", $this->getBody());
        $newBody = '';
        foreach ($split as $line) {
            if (strlen($line) > 0 && $line[0] != '>') {
                $newBody .= $line . ' ';
            }
        }
        return Utils::cutString((empty($newBody) ? $this->getBody() : $newBody), 80);
    }
}
