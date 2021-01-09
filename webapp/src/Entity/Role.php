<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Possible user roles.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="role",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Possible user roles"},
 *     uniqueConstraints={@ORM\UniqueConstraint(name="role", columns={"role"})})
 */
class Role
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="roleid", length=4,
     *     options={"comment"="Role ID","unsigned"=true}, nullable=false)
     */
    private $roleid;

    /**
     * @var string
     * @ORM\Column(type="string", name="role", length=32, options={"comment"="Role name"}, nullable=false)
     */
    private $dj_role;

    /**
     * @var string
     * @ORM\Column(type="string", name="description", length=255, options={"comment"="Description for the web interface"}, nullable=false)
     */
    private $description;

    /**
     * @ORM\ManyToMany(targetEntity="User", mappedBy="user_roles")
     */
    private $users;

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

    public function removeUser(User $user)
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
