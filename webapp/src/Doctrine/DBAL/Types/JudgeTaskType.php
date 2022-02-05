<?php declare(strict_types=1);
namespace App\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;

/**
 * Class JudgeTaskType
 * @package App\Doctrine\DBAL\Types
 */
class JudgeTaskType extends Type
{
    const ENUM_JUDGE_TASK_TYPE = 'judge_task_type';
    const CONFIG_CHECK = 'config_check';
    const DEBUG_INFO = 'debug_info';
    const GENERIC_TASK = 'generic_task';
    const JUDGING_RUN = 'judging_run';
    const PREFETCH = 'prefetch';
    const ALL_TYPES = [
        self::CONFIG_CHECK,
        self::DEBUG_INFO,
        self::GENERIC_TASK,
        self::JUDGING_RUN,
        self::PREFETCH,
    ];

    /**
     * @return mixed
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $statuses = implode(', ', array_map(
            fn(string $status) => sprintf("'%s'", $status),
            self::ALL_TYPES
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
        if (!in_array($value, self::ALL_TYPES)) {
            throw new InvalidArgumentException("Invalid judgetask type");
        }
        return $value;
    }

    public function getName(): string
    {
        return self::ENUM_JUDGE_TASK_TYPE;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
