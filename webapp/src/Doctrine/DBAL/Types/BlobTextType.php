<?php declare(strict_types=1);

namespace App\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\TextType;

/**
 * Class BlobTextType
 *
 * This type allows us to use a blob but output it as a string in PHP instead
 * of a resource, making it easier to use
 *
 * @package App\Doctrine\DBAL\Types
 */
class BlobTextType extends TextType
{
    public function getName()
    {
        return 'blobtext';
    }

    /**
     * @inheritDoc
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return $platform->getBlobTypeDeclarationSQL($fieldDeclaration);
    }

    /**
     * @inheritDoc
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        return true;
    }
}
