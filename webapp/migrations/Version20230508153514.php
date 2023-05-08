<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230508153514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make problem.externalid a unique index.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX externalid ON problem');
        $this->addSql('CREATE UNIQUE INDEX externalid ON problem (externalid(190))');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX externalid ON problem');
        $this->addSql('CREATE INDEX externalid ON problem (externalid(190))');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
