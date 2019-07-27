<?php declare(strict_types=1);

namespace App\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\JsonType as BaseJsonType;

/**
 * Class JsonType
 *
 * We overwrite this class because we want to preserve zero fractions when
 * storing JSON data in the database. Otherwise "5.0" will be converted to "5",
 * which gives some inconsistencies between the API and event feed.
 *
 * Also we always want a LONGTEXT field and not a JSON field, as that is only
 * supported by MySQL 5.7+
 *
 * @package App\Doctrine\DBAL\Types
 */
class JsonType extends BaseJsonType
{
    /**
     * @inheritDoc
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        if (!empty($fieldDeclaration['length'])) {
            if ($fieldDeclaration['length'] <= 255) {
                return $platform->getVarcharTypeDeclarationSQL($fieldDeclaration);
            } else {
                return $platform->getClobTypeDeclarationSQL($fieldDeclaration);
            }
        }
        return 'LONGTEXT';
    }

    /**
     * @inheritdoc
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if ($value === null) {
            return null;
        }

        $encoded = json_encode($value, JSON_PRESERVE_ZERO_FRACTION);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ConversionException::conversionFailedSerialization($value, 'json', json_last_error_msg());
        }

        return $encoded;
    }

    /**
     * @inheritDoc
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        return true;
    }
}
