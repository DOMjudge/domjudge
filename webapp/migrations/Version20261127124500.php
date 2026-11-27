<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127124500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add last_ip_address column to judgehost table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE judgehost ADD last_ip_address VARCHAR(255) DEFAULT NULL COMMENT \'Last IP address used by judgedaemon to connect\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE judgehost DROP last_ip_address');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
