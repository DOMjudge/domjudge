<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Compile, compare, and run script executable bundles.
 *
 * @ORM\Entity()
 * @ORM\Table(name="configuration",
 *     options={"collation"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Global configuration variables"},
 *     uniqueConstraints={@ORM\UniqueConstraint(name="name", columns={"name"})})
 */
class Configuration
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="configid", length=4,
     *     options={"comment"="Configuration ID","unsigned"=true}, nullable=false)
     */
    private int $configid;

    /**
     * @ORM\Column(type="string", name="name", length=32, options={"comment"="Name of the configuration variable"}, nullable=false)
     */
    private string $name;

    /**
     * @var mixed
     * @ORM\Column(type="json", length=4294967295, name="value",
     *     options={"comment"="Content of the configuration variable (JSON encoded)"},
     *     nullable=false)
     */
    private $value;

    public function getConfigid(): int
    {
        return $this->configid;
    }

    public function setName(string $name): Configuration
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param mixed $value
     */
    public function setValue($value): Configuration
    {
        // Do not use 'True'/'False' but 1/0 since the former cannot be parsed by the old code.
        if ($value === true) {
            $value = 1;
        } elseif ($value === false) {
            $value = 0;
        }

        $this->value = $value;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}
