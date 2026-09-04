<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260602175403 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Install extra optional chroot directory option for a language.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE language ADD chroot_directory VARCHAR(32) DEFAULT NULL COMMENT \'Custom chroot for executable\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE language DROP chroot_directory');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
