<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Possible user roles
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

    public function getRole()
    {
        return "ROLE_" . strtoupper($this->dj_role);
    }

    /**
     * Get roleid
     *
     * @return integer
     */
    public function getRoleid()
    {
        return $this->roleid;
    }

    /**
     * Set description
     *
     * @param string $description
     *
     * @return Role
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
    * Set djRole
    *
    * @param string $djRole
    *
    * @return Role
    */
    public function setDjRole($djRole)
    {
        $this->dj_role = $djRole;

        return $this;
    }

    /**
    * Get djRole
    *
    * @return string
    */
    public function getDjRole()
    {
        return $this->dj_role;
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->users = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add user
     *
     * @param \App\Entity\User $user
     *
     * @return Role
     */
    public function addUser(\App\Entity\User $user)
    {
        $this->users[] = $user;

        return $this;
    }

    /**
     * Remove user
     *
     * @param \App\Entity\User $user
     */
    public function removeUser(\App\Entity\User $user)
    {
        $this->users->removeElement($user);
    }

    /**
     * Get users
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getUsers()
    {
        return $this->users;
    }

    public function __toString()
    {
        return $this->getRole() . ": " . $this->getDescription();
    }
}
