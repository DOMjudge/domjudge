<?php declare(strict_types=1);

namespace App\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;

/**
 * Class InternalErrorStatusType
 * @package App\Doctrine\DBAL\Types
 */
class InternalErrorStatusType extends Type
{
    const ENUM_INTERNAL_ERROR_STATUS = 'internal_error_status';
    const STATUS_OPEN                = 'open';
    const STATUS_RESOLVED            = 'resolved';
    const STATUS_IGNROED             = 'ignored';
    const ALL_STATUSES = [self::STATUS_OPEN, self::STATUS_RESOLVED, self::STATUS_IGNROED];

    /**
     * @inheritDoc
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        $statuses = implode(', ', array_map(function (string $status) {
            return sprintf("'%s'", $status);
        }, self::ALL_STATUSES));
        return sprintf("ENUM(%s)", $statuses);
    }

    /**
     * @inheritDoc
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (!in_array($value, self::ALL_STATUSES)) {
            throw new InvalidArgumentException("Invalid status");
        }
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return self::ENUM_INTERNAL_ERROR_STATUS;
    }

    /**
     * @inheritDoc
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        return true;
    }
}
