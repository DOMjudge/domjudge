<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241014163343 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        //$this->addSql('ALTER TABLE executable DROP zipfile');
        $this->addSql('ALTER TABLE judging ADD visualization TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Explicitly requested to visualize the output.\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        //$this->addSql('ALTER TABLE executable ADD zipfile LONGBLOB DEFAULT NULL COMMENT \'Zip file\'');
        $this->addSql('ALTER TABLE judging DROP visualization');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
