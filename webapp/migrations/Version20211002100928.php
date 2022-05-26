<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211002100928 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE queuetask (queuetaskid INT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT \'Queuetask ID\', teamid INT UNSIGNED DEFAULT NULL COMMENT \'Team ID\', jobid INT UNSIGNED DEFAULT NULL COMMENT \'All queuetasks with the same jobid belong together.\', priority INT NOT NULL COMMENT \'Priority; negative means higher priority\', teampriority INT NOT NULL COMMENT \'Team Priority; somewhat magic, lower implies higher priority.\', starttime NUMERIC(32, 9) UNSIGNED DEFAULT NULL COMMENT \'Time started work\', INDEX queuetaskid (queuetaskid), INDEX jobid (jobid), INDEX priority (priority), INDEX teampriority (teampriority), INDEX teamid (teamid), INDEX starttime (starttime), PRIMARY KEY(queuetaskid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'Work items.\' ');
        $this->addSql('ALTER TABLE queuetask ADD CONSTRAINT FK_45E85FF84DD6ABF3 FOREIGN KEY (teamid) REFERENCES team (teamid) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE queuetask');
    }
}
