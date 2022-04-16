<?php declare(strict_types=1);

namespace App\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\TextType;

/**
 * Class BlobTextType
 *
 * This type allows us to use a blob but output it as a string in PHP instead
 * of a resource, making it easier to use.
 *
 * @package App\Doctrine\DBAL\Types
 */
class BlobTextType extends TextType
{
    public function getName(): string
    {
        return 'blobtext';
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getBlobTypeDeclarationSQL($column);
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
