<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220726184436 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make external_source_warning.last_event_id nullable.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE external_source_warning CHANGE last_event_id last_event_id VARCHAR(255) DEFAULT NULL COMMENT \'Last event ID this warning happened at\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE external_source_warning CHANGE last_event_id last_event_id VARCHAR(255) NOT NULL COMMENT \'Last event ID this warning happened at\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
