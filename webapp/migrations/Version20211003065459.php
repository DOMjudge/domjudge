<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211003065459 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription() : string
    {
        return 'Add b2 and b3 to contest.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE contest ADD b2 SMALLINT UNSIGNED DEFAULT 0 COMMENT \'Last team receiving high honors\' AFTER b, ADD b3 SMALLINT UNSIGNED DEFAULT 0 COMMENT \'Last team receiving honors\' AFTER b2');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE contest DROP b2, DROP b3');
    }
}
