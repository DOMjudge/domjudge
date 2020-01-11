<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Compile, compare, and run script executable bundles
 * @ORM\Entity()
 * @ORM\Table(name="configuration",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Global configuration variables"},
 *     uniqueConstraints={@ORM\UniqueConstraint(name="name", columns={"name"})})
 */
class Configuration
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="configid", length=4,
     *     options={"comment"="Configuration ID","unsigned"=true}, nullable=false)
     */
    private $configid;

    /**
     * @var string
     * @ORM\Column(type="string", name="name", length=32, options={"comment"="Name of the configuration variable"}, nullable=false)
     */
    private $name;

    /**
     * @var string
     * @ORM\Column(type="json", length=4294967295, name="value",
     *     options={"comment"="Content of the configuration variable (JSON encoded)"},
     *     nullable=false)
     */
    private $value;

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
        // Do not use 'True'/'False' but 1/0 since the former cannot be parsed by the old code.
        if ($value === TRUE) {
            $value = 1;
        } elseif ($value === FALSE) {
            $value = 0;
        }

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
}
