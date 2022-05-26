<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201122080505 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription() : string
    {
        return 'rank is a reserved keyword for MySQL, rename it to non reserved';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX `rank` ON testcase');
        $this->addSql('ALTER TABLE testcase CHANGE `rank` ranknumber INT UNSIGNED NOT NULL COMMENT \'Determines order of the testcases in judging\'');
        $this->addSql('CREATE UNIQUE INDEX rankindex ON testcase (probid, ranknumber)');
        $this->addSql('DROP INDEX `rank` ON submission_file');
        $this->addSql('ALTER TABLE submission_file CHANGE `rank` ranknumber INT UNSIGNED NOT NULL COMMENT \'Order of the submission files, zero-indexed\'');
        $this->addSql('CREATE UNIQUE INDEX rankindex ON submission_file (submitid, ranknumber)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX rankindex ON submission_file');
        $this->addSql('ALTER TABLE submission_file CHANGE ranknumber `rank` INT UNSIGNED NOT NULL COMMENT \'Order of the submission files, zero-indexed\'');
        $this->addSql('CREATE UNIQUE INDEX `rank` ON submission_file (submitid, `rank`)');
        $this->addSql('DROP INDEX rankindex ON testcase');
        $this->addSql('ALTER TABLE testcase CHANGE ranknumber `rank` INT UNSIGNED NOT NULL COMMENT \'Determines order of the testcases in judging\'');
        $this->addSql('CREATE UNIQUE INDEX `rank` ON testcase (probid, `rank`)');
    }
}
