<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20221104143647 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add new warning_message column to contest';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contest ADD warning_message TEXT DEFAULT NULL COMMENT \'Warning message for this contest shown on the scoreboards\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contest DROP warning_message');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
