<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Balloons to be handed out.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Balloons to be handed out',
])]
#[ORM\Index(columns: ['submitid'], name: 'submitid')]
class Balloon
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Balloon ID', 'unsigned' => true])]
    private int $balloonid;

    #[ORM\Column(options: ['comment' => 'Has been handed out yet?', 'default' => 0])]
    private bool $done = false;

    #[ORM\ManyToOne(inversedBy: 'balloons')]
    #[ORM\JoinColumn(name: 'submitid', referencedColumnName: 'submitid', onDelete: 'CASCADE')]
    private Submission $submission;

    public function getBalloonid(): int
    {
        return $this->balloonid;
    }

    public function setDone(bool $done): Balloon
    {
        $this->done = $done;
        return $this;
    }

    public function getDone(): bool
    {
        return $this->done;
    }

    public function setSubmission(Submission $submission = null): Balloon
    {
        $this->submission = $submission;
        return $this;
    }

    public function getSubmission(): Submission
    {
        return $this->submission;
    }
}
