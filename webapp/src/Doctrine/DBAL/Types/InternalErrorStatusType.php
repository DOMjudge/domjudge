<?php declare(strict_types=1);

namespace App\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;

class InternalErrorStatusType extends Type
{
    final public const ENUM_INTERNAL_ERROR_STATUS = 'internal_error_status';
    final public const STATUS_OPEN                = 'open';
    final public const STATUS_RESOLVED            = 'resolved';
    final public const STATUS_IGNORED             = 'ignored';
    final public const ALL_STATUSES = [self::STATUS_OPEN, self::STATUS_RESOLVED, self::STATUS_IGNORED];

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $statuses = implode(', ', array_map(
            fn(string $status) => sprintf("'%s'", $status),
            self::ALL_STATUSES
        ));
        return sprintf("ENUM(%s)", $statuses);
    }

    /**
     * @return mixed
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return $value;
    }

    /**
     * @return mixed
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (!in_array($value, self::ALL_STATUSES)) {
            throw new InvalidArgumentException("Invalid status");
        }
        return $value;
    }

    public function getName(): string
    {
        return self::ENUM_INTERNAL_ERROR_STATUS;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
