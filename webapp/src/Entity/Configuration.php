<?php declare(strict_types=1);
namespace App\Entity;

use App\Doctrine\Constants;
use Doctrine\ORM\Mapping as ORM;

/**
 * Compile, compare, and run script executable bundles.
 */
#[ORM\Table(
    name: 'configuration',
    options: [
        'collation' => 'utf8mb4_unicode_ci',
        'charset' => 'utf8mb4',
        'comment' => 'Global configuration variables',
    ]
)]
#[ORM\UniqueConstraint(name: 'name', columns: ['name'])]
#[ORM\Entity]
class Configuration
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(
        name: 'configid',
        type: 'integer',
        length: 4,
        nullable: false,
        options: ['comment' => 'Configuration ID', 'unsigned' => true]
    )]
    private int $configid;

    #[ORM\Column(
        name: 'name',
        type: 'string',
        length: 32,
        nullable: false,
        options: ['comment' => 'Name of the configuration variable']
    )]
    private string $name;

    #[ORM\Column(
        name: 'value',
        type: 'json',
        length: Constants::LENGTH_LIMIT_LONGTEXT,
        nullable: false,
        options: ['comment' => 'Content of the configuration variable (JSON encoded)']
    )]
    private mixed $value;

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
