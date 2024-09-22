<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240921081301 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add multipass problem support';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE problem ADD is_multipass_problem TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Whether this problem is a multi-pass problem.\', ADD multipass_limit INT UNSIGNED DEFAULT NULL COMMENT \'Optional limit on the number of rounds for multi-pass problems; defaults to 2 if not specified.\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE problem DROP is_multipass_problem, DROP multipass_limit');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
