<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230508095605 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add import error to submissions';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE submission ADD import_error VARCHAR(255) DEFAULT NULL COMMENT \'If this submission was imported during shadowing but had an error while doing so, the error message.\'');
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
