<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250519122129 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE scorecache CHANGE optscore_max_restricted optscore_max_restricted DOUBLE PRECISION DEFAULT \'0\' COMMENT \'Max optscore (restricted audience)\', CHANGE optscore_max_public optscore_max_public DOUBLE PRECISION DEFAULT \'0\' COMMENT \'Max optscore (public audience)\', CHANGE optscore_min_restricted optscore_min_restricted DOUBLE PRECISION DEFAULT \'0\' COMMENT \'Min optscore (restricted audience)\', CHANGE optscore_min_public optscore_min_public DOUBLE PRECISION DEFAULT \'0\' COMMENT \'Min optscore (public audience)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE scorecache CHANGE optscore_max_restricted optscore_max_restricted DOUBLE PRECISION DEFAULT \'0\' NOT NULL COMMENT \'Max optscore (restricted audience)\', CHANGE optscore_max_public optscore_max_public DOUBLE PRECISION DEFAULT \'0\' NOT NULL COMMENT \'Max optscore (public audience)\', CHANGE optscore_min_restricted optscore_min_restricted DOUBLE PRECISION DEFAULT \'0\' NOT NULL COMMENT \'Min optscore (restricted audience)\', CHANGE optscore_min_public optscore_min_public DOUBLE PRECISION DEFAULT \'0\' NOT NULL COMMENT \'Min optscore (public audience)\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
