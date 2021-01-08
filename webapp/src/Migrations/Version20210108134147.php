<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210108134147 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Add some indices.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE testcase_content ADD CONSTRAINT FK_50A5CCE2D360BB2B FOREIGN KEY (testcaseid) REFERENCES testcase (testcaseid) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX priority ON judgetask (priority)');
        $this->addSql('CREATE INDEX jobid ON judgetask (jobid)');
        $this->addSql('CREATE INDEX submitid ON judgetask (submitid)');
        $this->addSql('CREATE INDEX valid ON judgetask (valid)');
        $this->addSql('DROP INDEX filename ON executable_file');
        $this->addSql('CREATE UNIQUE INDEX filename ON executable_file (immutable_execid, filename(190))');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX filename ON executable_file');
        $this->addSql('CREATE UNIQUE INDEX filename ON executable_file (immutable_execid, filename(190))');
        $this->addSql('DROP INDEX priority ON judgetask');
        $this->addSql('DROP INDEX jobid ON judgetask');
        $this->addSql('DROP INDEX submitid ON judgetask');
        $this->addSql('DROP INDEX valid ON judgetask');
        $this->addSql('ALTER TABLE testcase_content DROP FOREIGN KEY FK_50A5CCE2D360BB2B');
    }
}
