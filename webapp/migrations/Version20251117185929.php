<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251117185929 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE generic_task (taskid INT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT \'Task ID\', judgetaskid INT UNSIGNED DEFAULT NULL COMMENT \'JudgeTask ID\', runtime DOUBLE PRECISION DEFAULT NULL COMMENT \'Running time for this task\', endtime NUMERIC(32, 9) UNSIGNED DEFAULT NULL COMMENT \'Time task ended\', start_time NUMERIC(32, 9) UNSIGNED DEFAULT NULL COMMENT \'Time task started\', INDEX IDX_680437B63CBA64F2 (judgetaskid), PRIMARY KEY(taskid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'Result of a generic task\' ');
        $this->addSql('CREATE TABLE generic_task_output (taskid INT UNSIGNED NOT NULL COMMENT \'Task ID\', output_task LONGBLOB DEFAULT NULL COMMENT \'Output of running the program(DC2Type:blobtext)\', output_error LONGBLOB DEFAULT NULL COMMENT \'Standard error output of the program(DC2Type:blobtext)\', INDEX taskid (taskid), PRIMARY KEY(taskid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'Stores output of generic task\' ');
        $this->addSql('ALTER TABLE generic_task ADD CONSTRAINT FK_680437B63CBA64F2 FOREIGN KEY (judgetaskid) REFERENCES judgetask (judgetaskid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE generic_task_output ADD CONSTRAINT FK_6425C7BE46CBEE95 FOREIGN KEY (taskid) REFERENCES generic_task (taskid) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE generic_task DROP FOREIGN KEY FK_680437B63CBA64F2');
        $this->addSql('ALTER TABLE generic_task_output DROP FOREIGN KEY FK_6425C7BE46CBEE95');
        $this->addSql('DROP TABLE generic_task');
        $this->addSql('DROP TABLE generic_task_output');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
