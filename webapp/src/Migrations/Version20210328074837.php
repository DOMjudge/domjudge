<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210328074837 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE submission DROP FOREIGN KEY submission_ibfk_5');
        $this->addSql('DROP INDEX judgehost_2 ON submission');
        $this->addSql('DROP INDEX judgehost ON submission');
        $this->addSql('ALTER TABLE submission DROP judgehost');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE submission ADD judgehost VARCHAR(64) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'Resolvable hostname of judgehost\'');
        $this->addSql('ALTER TABLE submission ADD CONSTRAINT submission_ibfk_5 FOREIGN KEY (judgehost) REFERENCES judgehost (hostname) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX judgehost_2 ON submission (judgehost)');
        $this->addSql('CREATE INDEX judgehost ON submission (cid, judgehost)');
    }
}
