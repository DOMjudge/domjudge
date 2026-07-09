<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260313080124 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pass number to reported output & some judgetasks';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE judging_run ADD pass INT UNSIGNED DEFAULT NULL COMMENT \'Pass number for multipass problems\'');
        $this->addSql('ALTER TABLE judgetask ADD pass INT UNSIGNED DEFAULT NULL COMMENT \'Pass number, used for multipass problems\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE judgetask DROP pass');
        $this->addSql('ALTER TABLE judging_run DROP pass');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
