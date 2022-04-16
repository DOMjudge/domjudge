<?php declare(strict_types=1);

namespace App\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Class BinaryJsonType
 *
 * This is a version of the JsonType that stores its data as a LONGBLOB.
 *
 * @package App\Doctrine\DBAL\Types
 */
class BinaryJsonType extends JsonType
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'LONGBLOB';
    }

    public function getName(): string
    {
        return 'binaryjson';
    }
}
