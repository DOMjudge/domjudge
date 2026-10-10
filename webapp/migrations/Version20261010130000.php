<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the CCS API content version of every event';
    }

    public function up(Schema $schema): void
    {
        // All events written before this migration have the content of the `2020-03` version.
        $this->addSql('ALTER TABLE event ADD version VARCHAR(16) DEFAULT \'2020-03\' NOT NULL COMMENT \'CCS API version the content is in\'');
        $this->addSql('ALTER TABLE event ALTER version DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP version');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
