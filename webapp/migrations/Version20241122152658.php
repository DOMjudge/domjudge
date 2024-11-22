<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241122152658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix comment for multipass limit field';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE problem CHANGE multipass_limit multipass_limit INT UNSIGNED DEFAULT NULL COMMENT \'Optional limit on the number of rounds; defaults to 1 for traditional problems, 2 for multi-pass problems if not specified.\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE problem CHANGE multipass_limit multipass_limit INT UNSIGNED DEFAULT NULL COMMENT \'Optional limit on the number of rounds for multi-pass problems; defaults to 2 if not specified.\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
