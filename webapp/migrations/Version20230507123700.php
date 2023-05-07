<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230507123700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add compiler and runner versions information.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE version (versionid INT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT \'Version ID\', langid VARCHAR(32) DEFAULT NULL COMMENT \'Language ID (string)\', judgehostid INT UNSIGNED DEFAULT NULL COMMENT \'Judgehost ID\', compiler_version LONGBLOB DEFAULT NULL COMMENT \'Compiler version(DC2Type:blobtext)\', runner_version LONGBLOB DEFAULT NULL COMMENT \'Runner version(DC2Type:blobtext)\', runner_version_command VARCHAR(255) DEFAULT NULL COMMENT \'Runner version command\', compiler_version_command VARCHAR(255) DEFAULT NULL COMMENT \'Compiler version command\', last_changed_time NUMERIC(32, 9) UNSIGNED DEFAULT NULL COMMENT \'Time this version command output was last updated\', INDEX IDX_BF1CD3C32271845 (langid), INDEX IDX_BF1CD3C3E0E4FC3E (judgehostid), PRIMARY KEY(versionid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'Runner and compiler version commands per language.\' ');
        $this->addSql('ALTER TABLE version ADD CONSTRAINT FK_BF1CD3C32271845 FOREIGN KEY (langid) REFERENCES language (langid)');
        $this->addSql('ALTER TABLE version ADD CONSTRAINT FK_BF1CD3C3E0E4FC3E FOREIGN KEY (judgehostid) REFERENCES judgehost (judgehostid)');
        $this->addSql('ALTER TABLE immutable_executable ADD compiler_version LONGBLOB DEFAULT NULL COMMENT \'Compiler version(DC2Type:blobtext)\', ADD compiler_version_command VARCHAR(255) DEFAULT NULL COMMENT \'Compiler version command\', ADD runner_version LONGBLOB DEFAULT NULL COMMENT \'Runner version(DC2Type:blobtext)\', ADD runner_version_command VARCHAR(255) DEFAULT NULL COMMENT \'Runner version command\'');
        $this->addSql('ALTER TABLE language ADD compiler_version LONGBLOB DEFAULT NULL COMMENT \'Compiler version(DC2Type:blobtext)\', ADD runner_version LONGBLOB DEFAULT NULL COMMENT \'Runner version(DC2Type:blobtext)\', ADD runner_version_command VARCHAR(255) DEFAULT NULL COMMENT \'Runner version command\', ADD compiler_version_command VARCHAR(255) DEFAULT NULL COMMENT \'Compiler version command\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE version DROP FOREIGN KEY FK_BF1CD3C32271845');
        $this->addSql('ALTER TABLE version DROP FOREIGN KEY FK_BF1CD3C3E0E4FC3E');
        $this->addSql('DROP TABLE version');
        $this->addSql('ALTER TABLE language DROP compiler_version, DROP runner_version, DROP runner_version_command, DROP compiler_version_command');
        $this->addSql('ALTER TABLE immutable_executable DROP compiler_version, DROP compiler_version_command, DROP runner_version, DROP runner_version_command');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
