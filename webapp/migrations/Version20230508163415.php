<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230508163415 extends AbstractMigration
{
    // The code that was in this file was moved to Version20230508180000.php so
    // that it will re-run after Version20230508170000.php. This file has been
    // kept as a no-op to prevent warnings about previously executed migrations
    // that are not registered.

    public function getDescription(): string
    {
        return '[Deleted]';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('-- no-op'); // suppress warning "Migration <name> was executed but did not result in any SQL statements."
    }

    public function down(Schema $schema): void
    {
        $this->addSql('-- no-op');
    }
}
