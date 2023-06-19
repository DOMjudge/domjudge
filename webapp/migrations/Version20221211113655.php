<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221211113655 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Expands configuration name to allow key names up to 64 characters';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE configuration MODIFY `name` varchar(64)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE configuration MODIFY `name` varchar(32)');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
