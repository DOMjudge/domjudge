<?php
namespace DOMJudgeBundle\Entity;

use Symfony\Component\Security\Core\Role\RoleInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * Possible user roles
 * @ORM\Entity()
 * @ORM\Table(name="role", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class Role implements RoleInterface
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="roleid", options={"comment"="Unique ID"}, nullable=false)
     */
    private $roleid;

    /**
     * @var string
     * @ORM\Column(type="string", name="role", length=32, options={"comment"="Role name"}, nullable=false)
     */
    private $dj_role;

    /**
     * @var string
     * @ORM\Column(type="string", name="description", length=255, options={"comment"="Role name"}, nullable=false)
     */
    private $description;

    /**
     * @ORM\ManyToMany(targetEntity="User", mappedBy="roles")
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
     * @param \DOMJudgeBundle\Entity\User $user
     *
     * @return Role
     */
    public function addUser(\DOMJudgeBundle\Entity\User $user)
    {
        $this->users[] = $user;

        return $this;
    }

    /**
     * Remove user
     *
     * @param \DOMJudgeBundle\Entity\User $user
     */
    public function removeUser(\DOMJudgeBundle\Entity\User $user)
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
}
