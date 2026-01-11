<?php declare(strict_types=1);
namespace App\Entity;

use App\Controller\API\AbstractRestController as ARC;
use App\Repository\ClarificationRepository;
use App\Utils\Utils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Clarification requests by teams and responses by the jury.
 */
#[ORM\Entity(repositoryClass: ClarificationRepository::class)]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Clarification requests by teams and responses by the jury',
])]
#[ORM\Index(columns: ['respid'], name: 'respid')]
#[ORM\Index(columns: ['probid'], name: 'probid')]
#[ORM\Index(columns: ['cid'], name: 'cid')]
#[ORM\Index(columns: ['cid', 'answered', 'submittime'], name: 'cid_2')]
#[ORM\Index(columns: ['sender'], name: 'sender')]
#[ORM\Index(columns: ['recipient'], name: 'recipient')]
#[ORM\UniqueConstraint(
    name: 'externalid',
    columns: ['cid', 'externalid'],
    options: ['lengths' => [null, 190]]
)]
#[UniqueEntity(fields: 'externalid')]
class Clarification extends BaseApiEntity implements
    HasExternalIdInterface,
    ExternalIdFromInternalIdInterface,
    PrefixedExternalIdInShadowModeInterface
{
    public const CATEGORY_BASED_SEPARATOR = '#';
    public const PROBLEM_BASED_SEPARATOR = '|';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Clarification ID', 'unsigned' => true])]
    #[Serializer\Exclude]
    protected int $clarid;

    #[ORM\Column(
        nullable: true,
        options: [
            'comment' => 'Clarification ID in an external system, should be unique inside a single contest',
            'collation' => 'utf8mb4_bin',
        ]
    )]
    #[OA\Property(nullable: true)]
    #[Serializer\SerializedName('id')]
    protected ?string $externalid = null;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        options: ['comment' => 'Time sent', 'unsigned' => true]
    )]
    #[Serializer\Exclude]
    private string|float $submittime;

    #[ORM\Column(nullable: true, options: ['comment' => 'Name of jury member who answered this'])]
    #[Serializer\Exclude]
    private ?string $jury_member = null;

    #[ORM\Column(
        nullable: true,
        options: ['comment' => 'Category associated to this clarification; only set for non problem clars']
    )]
    #[Serializer\Exclude]
    private ?string $category = null;

    #[ORM\Column(nullable: true, options: ['comment' => 'Queue associated to this clarification'])]
    #[Serializer\Exclude]
    private ?string $queue = null;

    #[ORM\Column(type: 'text', options: ['comment' => 'Clarification text'])]
    #[Serializer\SerializedName('text')]
    private string $body;

    #[ORM\Column(options: ['comment' => 'Has been answered by jury?', 'default' => 0])]
    #[Serializer\Groups([ARC::GROUP_RESTRICTED_NONSTRICT])]
    private bool $answered = false;

    #[ORM\ManyToOne(inversedBy: 'clarifications')]
    #[ORM\JoinColumn(name: 'probid', referencedColumnName: 'probid', onDelete: 'SET NULL')]
    #[Serializer\Exclude]
    private ?Problem $problem = null;

    #[ORM\ManyToOne(inversedBy: 'clarifications')]
    #[ORM\JoinColumn(name: 'cid', referencedColumnName: 'cid', onDelete: 'CASCADE')]
    #[Serializer\Exclude]
    private Contest $contest;

    #[ORM\ManyToOne(inversedBy: 'replies')]
    #[ORM\JoinColumn(name: 'respid', referencedColumnName: 'clarid', onDelete: 'SET NULL')]
    #[Serializer\Exclude]
    private ?Clarification $in_reply_to = null;

    /**
     * @var Collection<int, Clarification>
     */
    #[ORM\OneToMany(mappedBy: 'in_reply_to', targetEntity: Clarification::class)]
    #[Serializer\Exclude]
    private Collection $replies;

    #[ORM\ManyToOne(inversedBy: 'sent_clarifications')]
    #[ORM\JoinColumn(name: 'sender', referencedColumnName: 'teamid', onDelete: 'CASCADE')]
    #[Serializer\Exclude]
    private ?Team $sender = null;

    #[ORM\ManyToOne(inversedBy: 'received_clarifications')]
    #[ORM\JoinColumn(name: 'recipient', referencedColumnName: 'teamid', onDelete: 'CASCADE')]
    #[Serializer\Exclude]
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

    public function setSubmittime(string|float $submittime): Clarification
    {
        $this->submittime = $submittime;
        return $this;
    }

    public function getSubmittime(): string|float
    {
        return $this->submittime;
    }

    #[OA\Property(nullable: true)]
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('time')]
    #[Serializer\Type('string')]
    public function getAbsoluteSubmitTime(): string
    {
        return Utils::absTime($this->getSubmittime());
    }

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('contest_time')]
    #[Serializer\Type('string')]
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

    public function getContestProblem(): ?ContestProblem
    {
        if (!$this->problem) {
            return null;
        }
        return $this->contest->getContestProblem($this->problem);
    }

    public function getProblemId(): ?int
    {
        return $this->getProblem()?->getProbid();
    }

    #[OA\Property(nullable: true)]
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('problem_id')]
    public function getApiProblemId(): ?string
    {
        return $this->getProblem()?->getExternalid();
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

    #[OA\Property(nullable: true)]
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('reply_to_id')]
    public function getInReplyToId(): ?string
    {
        return $this->getInReplyTo()?->getExternalid();
    }

    public function addReply(Clarification $reply): Clarification
    {
        $this->replies[] = $reply;
        return $this;
    }

    /**
     * @return Collection<int, Clarification>
     */
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

    #[OA\Property(nullable: true)]
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('from_team_id')]
    public function getSenderId(): ?string
    {
        return $this->getSender()?->getExternalid();
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

    #[OA\Property(nullable: true)]
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('to_team_id')]
    public function getRecipientId(): ?string
    {
        return $this->getRecipient()?->getExternalid();
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
        return Utils::cutString(html_entity_decode((empty($newBody) ? $this->getBody() : $newBody)), 120);
    }
}
