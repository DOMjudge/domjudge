<?php declare(strict_types=1);

namespace App\Doctrine\DBAL\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class TinyIntType extends Type
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'tinyint';
    }

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        $declaration = 'TINYINT';

        if (isset($fieldDeclaration['length'])) {
            $declaration .= sprintf('(%d)', $fieldDeclaration['length']);
        }

        if (($fieldDeclaration['unsigned']) ?? false) {
            $declaration .= ' UNSIGNED';
        }

        return $declaration;
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return $value === null ? null : (int)$value;
    }

    /**
     * {@inheritdoc}
     */
    public function getBindingType()
    {
        return ParameterType::INTEGER;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        return true;
    }
}
