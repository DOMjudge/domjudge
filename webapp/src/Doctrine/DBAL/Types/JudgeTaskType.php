<?php declare(strict_types=1);
namespace App\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;

class JudgeTaskType extends Type
{
    final public const ENUM_JUDGE_TASK_TYPE = 'judge_task_type';
    final public const CONFIG_CHECK = 'config_check';
    final public const DEBUG_INFO = 'debug_info';
    final public const GENERIC_TASK = 'generic_task';
    final public const JUDGING_RUN = 'judging_run';
    final public const PREFETCH = 'prefetch';
    final public const ALL_TYPES = [
        self::CONFIG_CHECK,
        self::DEBUG_INFO,
        self::GENERIC_TASK,
        self::JUDGING_RUN,
        self::PREFETCH,
    ];

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $statuses = implode(', ', array_map(
            fn(string $status) => sprintf("'%s'", $status),
            self::ALL_TYPES
        ));
        return sprintf("ENUM(%s)", $statuses);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        return $value;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if (!in_array($value, self::ALL_TYPES)) {
            throw new InvalidArgumentException("Invalid judgetask type");
        }
        return $value;
    }

    public function getMappedDatabaseTypes(AbstractPlatform $platform): array
    {
        return ['enum'];
    }
}
