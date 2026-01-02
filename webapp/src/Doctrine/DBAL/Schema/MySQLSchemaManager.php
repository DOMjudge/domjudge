<?php declare(strict_types=1);

namespace App\Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\MySQLSchemaManager as BaseMySQLSchemaManager;
use Doctrine\DBAL\Types\Type;

/**
 * Custom MySQL schema manager that maps TINYINT columns with display width > 1 to
 * TinyIntType (not BooleanType)
 */
class MySQLSchemaManager extends BaseMySQLSchemaManager
{
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- inherited from Doctrine
    protected function _getPortableTableColumnDefinition(array $tableColumn): Column
    {
        $tableColumn = array_change_key_case($tableColumn);

        // Check if this is a non-boolean tinyint (display width != 1)
        // MySQL uses TINYINT(1) for booleans, other widths are actual integers
        $isTinyIntNotBoolean = false;
        if (isset($tableColumn['column_type'])) {
            $columnType = strtolower($tableColumn['column_type']);
            if (preg_match('/^tinyint\((\d+)\)/', $columnType, $matches)) {
                $displayWidth = (int)$matches[1];
                $isTinyIntNotBoolean = $displayWidth !== 1;
            } elseif (str_starts_with($columnType, 'tinyint')) {
                // TINYINT without explicit width - assume it's not a boolean
                $isTinyIntNotBoolean = true;
            }
        }

        $column = parent::_getPortableTableColumnDefinition($tableColumn);

        // Override boolean type with tinyint for non-boolean tinyint columns
        if ($isTinyIntNotBoolean && Type::hasType('tinyint')) {
            $column->setType(Type::getType('tinyint'));
        }

        return $column;
    }
}
