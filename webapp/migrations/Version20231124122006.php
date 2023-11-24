<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231124122006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store a history of versions, instead of just the most recent one.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE version ADD active TINYINT(1) DEFAULT 1 NOT NULL COMMENT \'True if this version is active for this judgehost/language combination.\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE version DROP active');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
