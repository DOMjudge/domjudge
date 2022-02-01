<?php declare(strict_types=1);

namespace App\Doctrine\DBAL\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class TinyIntType extends Type
{
    public function getName(): string
    {
        return 'tinyint';
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $declaration = 'TINYINT';

        if (isset($column['length'])) {
            $declaration .= sprintf('(%d)', $column['length']);
        }

        if (($column['unsigned']) ?? false) {
            $declaration .= ' UNSIGNED';
        }

        return $declaration;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?int
    {
        return $value === null ? null : (int)$value;
    }

    public function getBindingType(): int
    {
        return ParameterType::INTEGER;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
