<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Stringable;

/**
 * Possible user roles.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Possible user roles',
])]
#[ORM\UniqueConstraint(name: 'role', columns: ['role'])]
class Role implements Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Role ID', 'unsigned' => true])]
    private int $roleid;

    #[ORM\Column(name: 'role', length: 32, options: ['comment' => 'Role name'])]
    private string $dj_role;

    #[ORM\Column(options: ['comment' => 'Description for the web interface'])]
    private string $description;

    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'user_roles')]
    private Collection $users;

    public function getRole(): string
    {
        return "ROLE_" . strtoupper($this->dj_role);
    }

    public function getRoleid(): int
    {
        return $this->roleid;
    }

    public function setDescription(string $description): Role
    {
        $this->description = $description;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDjRole(string $djRole): Role
    {
        $this->dj_role = $djRole;
        return $this;
    }

    public function getDjRole(): string
    {
        return $this->dj_role;
    }

    public function __construct()
    {
        $this->users = new ArrayCollection();
    }

    public function addUser(User $user): Role
    {
        $this->users[] = $user;
        return $this;
    }

    public function removeUser(User $user): void
    {
        $this->users->removeElement($user);
    }

    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function __toString(): string
    {
        return $this->getRole() . ": " . $this->getDescription();
    }
}
