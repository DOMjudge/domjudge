<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231006185028 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the last received HTTP code for the external contest source.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE external_contest_source ADD last_httpcode SMALLINT UNSIGNED DEFAULT NULL COMMENT \'Last HTTP code received by event feed reader\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE external_contest_source DROP last_httpcode');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
