<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250519064616 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE scorecache ADD optscore_max_restricted DOUBLE PRECISION DEFAULT \'0\' NOT NULL COMMENT \'Max optscore (restricted audience)\', ADD optscore_max_public DOUBLE PRECISION DEFAULT \'0\' NOT NULL COMMENT \'Max optscore (public audience)\', ADD optscore_min_restricted DOUBLE PRECISION DEFAULT \'0\' NOT NULL COMMENT \'Min optscore (restricted audience)\', ADD optscore_min_public DOUBLE PRECISION DEFAULT \'0\' NOT NULL COMMENT \'Min optscore (public audience)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE scorecache DROP optscore_max_restricted, DROP optscore_max_public, DROP optscore_min_restricted, DROP optscore_min_public');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
