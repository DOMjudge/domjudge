<?php declare(strict_types=1);

namespace App\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Class BinaryJsonType
 *
 * This is a version of the JsonType that stores its data as a LONGBLOB
 *
 * @package App\Doctrine\DBAL\Types
 */
class BinaryJsonType extends JsonType
{
    /**
     * @inheritDoc
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return 'LONGBLOB';
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'binaryjson';
    }
}
