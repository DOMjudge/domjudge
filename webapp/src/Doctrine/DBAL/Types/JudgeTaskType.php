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
    const JUDGING_RUN = 'judging_run';
    const GENERIC_TASK = 'generic_task';
    const CONFIG_CHECK = 'config_check';
    const DEBUG_INFO = 'debug_info';
    const ALL_TYPES = [
        self::JUDGING_RUN, self::GENERIC_TASK, self::CONFIG_CHECK, self::DEBUG_INFO
    ];

    /**
     * @inheritDoc
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        $statuses = implode(', ', array_map(function (string $status) {
            return sprintf("'%s'", $status);
        }, self::ALL_TYPES));
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
        if (!in_array($value, self::ALL_TYPES)) {
            throw new InvalidArgumentException("Invalid judgetask type");
        }
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return self::ENUM_JUDGE_TASK_TYPE;
    }

    /**
     * @inheritDoc
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        return true;
    }
}
