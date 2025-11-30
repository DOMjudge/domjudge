<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251130111340 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP email');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD email VARCHAR(255) DEFAULT NULL COMMENT \'Email address\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
