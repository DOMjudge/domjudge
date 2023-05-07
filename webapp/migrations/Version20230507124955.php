<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230507124955 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add import error to submissions';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE submission ADD import_error TINYINT(1) NOT NULL COMMENT \'Whether this submission was imported during shadowing but had an error while doing so.\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE submission DROP import_error');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
