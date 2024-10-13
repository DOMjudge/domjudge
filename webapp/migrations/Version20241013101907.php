<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241013101907 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        //$this->addSql('ALTER TABLE executable DROP zipfile');
        $this->addSql('ALTER TABLE problem ADD special_output_visualizer VARCHAR(32) DEFAULT NULL COMMENT \'Executable ID (string)\', CHANGE multipass_limit multipass_limit INT UNSIGNED DEFAULT NULL COMMENT \'Optional limit on the number of rounds; defaults to 1 for traditional problems, 2 for multi-pass problems if not specified.\'');
        $this->addSql('ALTER TABLE problem ADD CONSTRAINT FK_D7E7CCC819F5352E FOREIGN KEY (special_output_visualizer) REFERENCES executable (execid) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX special_output_visualizer ON problem (special_output_visualizer)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        //$this->addSql('ALTER TABLE executable ADD zipfile LONGBLOB DEFAULT NULL COMMENT \'Zip file\'');
        $this->addSql('ALTER TABLE problem DROP FOREIGN KEY FK_D7E7CCC819F5352E');
        $this->addSql('DROP INDEX special_output_visualizer ON problem');
        $this->addSql('ALTER TABLE problem DROP special_output_visualizer, CHANGE multipass_limit multipass_limit INT UNSIGNED DEFAULT NULL COMMENT \'Optional limit on the number of rounds for multi-pass problems; defaults to 2 if not specified.\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
