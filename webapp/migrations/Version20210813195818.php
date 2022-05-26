<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210813195818 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription() : string
    {
        return 'Remove JudgehostRestriction data';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE judgehost DROP FOREIGN KEY judgehost_ibfk_1');
        $this->addSql('DROP TABLE judgehost_restriction');
        $this->addSql('DROP INDEX restrictionid ON judgehost');
        $this->addSql('ALTER TABLE judgehost DROP restrictionid');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE judgehost_restriction (restrictionid INT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT \'Judgehost restriction ID\', name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'Descriptive name\', restrictions LONGTEXT CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_unicode_ci` COMMENT \'JSON-encoded restrictions(DC2Type:json)\', PRIMARY KEY(restrictionid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'Restrictions for judgehosts\' ');
        $this->addSql('ALTER TABLE judgehost ADD restrictionid INT UNSIGNED DEFAULT NULL COMMENT \'Judgehost restriction ID\'');
        $this->addSql('ALTER TABLE judgehost ADD CONSTRAINT judgehost_ibfk_1 FOREIGN KEY (restrictionid) REFERENCES judgehost_restriction (restrictionid) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX restrictionid ON judgehost (restrictionid)');
    }
}
