<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241014185658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        //$this->addSql('ALTER TABLE executable DROP zipfile');
        $this->addSql('ALTER TABLE judgetask ADD output_visualizer_script_id INT UNSIGNED DEFAULT NULL COMMENT \'Output visualizer script ID\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        //$this->addSql('ALTER TABLE executable ADD zipfile LONGBLOB DEFAULT NULL COMMENT \'Zip file\'');
        $this->addSql('ALTER TABLE judgetask DROP output_visualizer_script_id');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
