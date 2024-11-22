<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241122150726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add max runtime for verdict to judging';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE judging ADD max_runtime_for_verdict NUMERIC(32, 9) UNSIGNED DEFAULT NULL COMMENT \'The maximum run time for all runs that resulted in the verdict\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE judging DROP max_runtime_for_verdict');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
