<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Hostnames of the autojudgers.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Hostnames of the autojudgers',
])]
#[ORM\UniqueConstraint(name: 'hostname', columns: ['hostname'])]
class Judgehost
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Judgehost ID', 'unsigned' => true])]
    #[Serializer\SerializedName('id')]
    #[Serializer\Type('string')]
    private int $judgehostid;

    #[ORM\Column(length: 64, options: ['comment' => 'Resolvable hostname of judgehost'])]
    #[Assert\Regex(pattern: '/^[A-Za-z0-9_\-.]*$/', message: 'Invalid hostname. Only characters in [A-Za-z0-9_\-.] are allowed.')]
    private string $hostname;

    #[ORM\Column(options: ['comment' => 'Should this host take on judgings?', 'default' => 1])]
    private bool $enabled = true;

    #[ORM\Column(
        type: 'decimal',
        precision: 32,
        scale: 9,
        nullable: true,
        options: ['comment' => 'Time of last poll by autojudger', 'unsigned' => true]
    )]
    #[OA\Property(nullable: true)]
    private string|float|null $polltime = null;

    /**
     * @var Collection<int, JudgeTask>
     */
    #[ORM\OneToMany(targetEntity: JudgeTask::class, mappedBy: 'judgehost')]
    #[Serializer\Exclude]
    private Collection $judgetasks;

    #[ORM\Column(options: ['comment' => 'Should this host be hidden in the overview?', 'default' => 0])]
    private bool $hidden = false;

    public function __construct()
    {
        $this->judgetasks = new ArrayCollection();
    }

    public function getJudgehostid(): int
    {
        return $this->judgehostid;
    }

    public function setHostname(string $hostname): Judgehost
    {
        $this->hostname = $hostname;
        return $this;
    }

    public function getHostname(): string
    {
        return $this->hostname;
    }

    public function getShortDescription(): string
    {
        return $this->getHostname();
    }

    public function setEnabled(bool $enabled): Judgehost
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    public function setPolltime(string|float $polltime): Judgehost
    {
        $this->polltime = $polltime;
        return $this;
    }

    public function getPolltime(): string|float|null
    {
        return $this->polltime;
    }

    public function addJudgeTask(JudgeTask $judgeTask): Judgehost
    {
        $this->judgetasks[] = $judgeTask;
        return $this;
    }

    /**
     * @return Collection<int, JudgeTask>
     */
    public function getJudgeTasks(): Collection
    {
        return $this->judgetasks;
    }

    public function setHidden(bool $hidden): Judgehost
    {
        $this->hidden = $hidden;
        return $this;
    }

    public function getHidden(): bool
    {
        return $this->hidden;
    }
}
