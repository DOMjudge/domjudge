<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250302070928 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add metadata for the validator.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE judging_run_output ADD validator_metadata LONGBLOB DEFAULT NULL COMMENT \'Judging metadata of the validator(DC2Type:blobtext)\', CHANGE metadata metadata LONGBLOB DEFAULT NULL COMMENT \'Judging metadata of the run(DC2Type:blobtext)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE judging_run_output DROP validator_metadata, CHANGE metadata metadata LONGBLOB DEFAULT NULL COMMENT \'Judging metadata(DC2Type:blobtext)\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
