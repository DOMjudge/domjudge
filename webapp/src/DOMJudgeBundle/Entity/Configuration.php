<?php declare(strict_types=1);
namespace DOMJudgeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Compile, compare, and run script executable bundles
 * @ORM\Entity()
 * @ORM\Table(name="configuration", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class Configuration
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="configid", options={"comment"="Unique ID"}, nullable=false)
     */
    private $configid;

    /**
     * @var string
     * @ORM\Column(type="string", name="name", length=32, options={"comment"="Name of the configuration variable"}, nullable=false)
     */
    private $name;

    /**
     * @var string
     * @ORM\Column(type="json_array", length=4294967295, name="value", options={"comment"="Content of the configuration variable (JSON encoded)"}, nullable=true)
     */
    private $value;

    /**
     * @var string
     * @ORM\Column(type="string", name="type", length=32, options={"comment"="Type of the value (metatype for use in the webinterface)"}, nullable=true)
     */
    private $type;

    /**
     * @var bool
     * @ORM\Column(type="boolean", name="public", options={"comment"="Is this variable publicly visible?"}, nullable=false)
     */
    private $public = false;

    /**
     * @var string
     * @ORM\Column(type="string", name="category", length=32, options={"comment"="Category of the option value"}, nullable=true)
     */
    private $category;

    /**
     * @var string
     * @ORM\Column(type="string", name="description", length=255, options={"comment"="Description of this executable"}, nullable=true)
     */
    private $description;

    /**
     * Get configid
     *
     * @return integer
     */
    public function getConfigid()
    {
        return $this->configid;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return Configuration
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set value
     *
     * @param mixed $value
     *
     * @return Configuration
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get value
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Set type
     *
     * @param string $type
     *
     * @return Configuration
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set public
     *
     * @param bool $public
     *
     * @return Configuration
     */
    public function setPublic(bool $public)
    {
        $this->public = $public;

        return $this;
    }

    /**
     * Get public
     *
     * @return bool
     */
    public function getPublic()
    {
        return $this->public;
    }

    /**
     * Set category
     *
     * @param string $category
     *
     * @return Configuration
     */
    public function setCategory($category)
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Get category
     *
     * @return string
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Set description
     *
     * @param string $description
     *
     * @return Configuration
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
