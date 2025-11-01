<?php declare(strict_types=1);

namespace App\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\JsonType as BaseJsonType;

/**
 * We overwrite this class because we want to preserve zero fractions when
 * storing JSON data in the database. Otherwise "5.0" will be converted to "5",
 * which gives some inconsistencies between the API and event feed.
 *
 * Also we always want a LONGTEXT field and not a JSON field, as that is only
 * supported by MySQL 5.7+.
 */
class JsonType extends BaseJsonType
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        if (!empty($column['length'])) {
            if ($column['length'] <= 255) {
                return $platform->getStringTypeDeclarationSQL($column);
            } else {
                return $platform->getClobTypeDeclarationSQL($column);
            }
        }
        return 'LONGTEXT';
    }

    /**
     * @template T
     * @param T $value
     *
     * @return (T is null ? null : string)
     * @throws \JsonException
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
