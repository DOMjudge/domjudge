<?php declare(strict_types=1);

namespace App\Doctrine\DBAL\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\SchemaManagerFactory as SchemaManagerFactoryInterface;

/**
 * Custom schema manager factory that uses our MySQLSchemaManager for MySQL connections.
 */
class SchemaManagerFactory implements SchemaManagerFactoryInterface
{
    /**
     * @return AbstractSchemaManager<AbstractMySQLPlatform>
     */
    public function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        /** @var AbstractMySQLPlatform $platform */
        $platform = $connection->getDatabasePlatform();

        return new MySQLSchemaManager($connection, $platform);
    }
}
