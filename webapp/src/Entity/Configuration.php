<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Compile, compare, and run script executable bundles.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Global configuration variables',
])]
#[ORM\UniqueConstraint(name: 'name', columns: ['name'])]
class Configuration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Configuration ID', 'unsigned' => true])]
    private int $configid;

    #[ORM\Column(length: 64, options: ['comment' => 'Name of the configuration variable'])]
    private string $name;

    #[ORM\Column(
        type: 'json',
        options: ['comment' => 'Content of the configuration variable (JSON encoded)'])
    ]
    private mixed $value = null;

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

    public function setValue(mixed $value): Configuration
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
