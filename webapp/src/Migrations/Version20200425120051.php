<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200425120051 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Adds deleted boolean field to testcase.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE testcase ADD deleted TINYINT(1) DEFAULT \'0\' NOT NULL COMMENT \'Deleted testcases are kept for referential integrity.\', CHANGE probid probid INT UNSIGNED DEFAULT NULL COMMENT \'Corresponding problem ID\', CHANGE orig_input_filename orig_input_filename VARCHAR(255) DEFAULT NULL COMMENT \'Original basename of the input file.\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE testcase DROP deleted, CHANGE probid probid INT UNSIGNED NOT NULL COMMENT \'Corresponding problem ID\', CHANGE orig_input_filename orig_input_filename VARCHAR(255) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci COMMENT \'Original basename of the input file.\'');
    }
}
