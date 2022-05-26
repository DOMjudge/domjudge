<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210806180453 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription() : string
    {
        return 'Add user reference to submission table.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE submission ADD userid INT UNSIGNED DEFAULT NULL COMMENT \'User ID\' AFTER teamid');
        $this->addSql('ALTER TABLE submission ADD CONSTRAINT FK_DB055AF3F132696E FOREIGN KEY (userid) REFERENCES user (userid) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX userid ON submission (userid)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE submission DROP FOREIGN KEY FK_DB055AF3F132696E');
        $this->addSql('DROP INDEX userid ON submission');
        $this->addSql('ALTER TABLE submission DROP userid');
    }
}
