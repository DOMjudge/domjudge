<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221104143222 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add allow submit to contests';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contest ADD allow_submit TINYINT(1) DEFAULT 1 NOT NULL COMMENT \'Are submissions accepted in this contest?\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contest DROP allow_submit');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
