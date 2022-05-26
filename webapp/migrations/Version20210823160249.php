<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210823160249 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription() : string
    {
        return 'Remove judgehost field from judgings table.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE judging DROP FOREIGN KEY judging_ibfk_3');
        $this->addSql('DROP INDEX judgehostid ON judging');
        $this->addSql('ALTER TABLE judging DROP judgehostid');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE judging ADD judgehostid INT UNSIGNED DEFAULT NULL COMMENT \'Judgehost ID\'');
        $this->addSql('ALTER TABLE judging ADD CONSTRAINT judging_ibfk_3 FOREIGN KEY (judgehostid) REFERENCES judgehost (judgehostid)');
        $this->addSql('CREATE INDEX judgehostid ON judging (judgehostid)');
    }
}
