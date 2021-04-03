<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210403084748 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'new judgetasktype';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE judgetask MODIFY type ENUM(\'judging_run\', \'generic_task\', \'config_check\', \'debug_info\', \'prefetch\') DEFAULT \'judging_run\' NOT NULL COMMENT \'Type of the judge task.(DC2Type:judge_task_type)\'');
        $this->addSql('ALTER TABLE judgetask CHANGE jobid jobid INT UNSIGNED DEFAULT NULL COMMENT \'All judgetasks with the same jobid belong together.\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE judgetask CHANGE jobid jobid INT UNSIGNED NOT NULL COMMENT \'All judgetasks with the same jobid belong together.\'');
        $this->addSql('ALTER TABLE judgetask MODIFY type ENUM(\'judging_run\', \'generic_task\', \'config_check\', \'debug_info\') DEFAULT \'judging_run\' NOT NULL COMMENT \'Type of the judge task.(DC2Type:judge_task_type)\'');
    }
}
