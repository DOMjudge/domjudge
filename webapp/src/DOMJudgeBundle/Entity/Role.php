<?php
namespace DOMJudgeBundle\Entity;
use Doctrine\ORM\Mapping as ORM;
/**
 * Possible user roles
 * @ORM\Entity()
 * @ORM\Table(name="role", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class Role
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer", name="roleid", options={"comment"="Unique ID"}, nullable=false)
     */
    private $roleid;

    /**
     * @var string
     * @ORM\Column(type="string", name="role", length=25, options={"comment"="Role name"}, nullable=false)
     */
    private $role;

    /**
     * @var string
     * @ORM\Column(type="string", name="description", length=255, options={"comment"="Role name"}, nullable=false)
     */
    private $description;

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
     * Set role
     *
     * @param string $role
     *
     * @return Role
     */
    public function setRole($role)
    {
        $this->role = $role;

        return $this;
    }

    /**
     * Get role
     *
     * @return string
     */
    public function getRole()
    {
        return $this->role;
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
}
