<?php declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;

class Constants
{
    public const LENGTH_LIMIT_TINYTEXT = AbstractMySQLPlatform::LENGTH_LIMIT_TINYTEXT;
    public const LENGTH_LIMIT_LONGTEXT = 4294967295; // Doctrine doesn't have a constant for this
}
