<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210914192815 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription() : string
    {
        return 'Add internal error to judging.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE judging ADD errorid INT UNSIGNED DEFAULT NULL COMMENT \'Internal error ID\'');
        $this->addSql('ALTER TABLE judging ADD CONSTRAINT FK_4CA80CED4BCA8D9D FOREIGN KEY (errorid) REFERENCES internal_error (errorid) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_4CA80CED4BCA8D9D ON judging (errorid)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE judging DROP FOREIGN KEY FK_4CA80CED4BCA8D9D');
        $this->addSql('DROP INDEX IDX_4CA80CED4BCA8D9D ON judging');
        $this->addSql('ALTER TABLE judging DROP errorid');
    }
}
