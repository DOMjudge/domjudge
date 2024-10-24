<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241018061817 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow storing visualization of team output.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE problem ADD special_output_visualizer VARCHAR(32) DEFAULT NULL COMMENT \'Executable ID (string)\', CHANGE multipass_limit multipass_limit INT UNSIGNED DEFAULT NULL COMMENT \'Optional limit on the number of rounds; defaults to 1 for traditional problems, 2 for multi-pass problems if not specified.\'');
        $this->addSql('ALTER TABLE problem ADD CONSTRAINT FK_D7E7CCC819F5352E FOREIGN KEY (special_output_visualizer) REFERENCES executable (execid) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX special_output_visualizer ON problem (special_output_visualizer)');
        $this->addSql('ALTER TABLE judgetask ADD output_visualizer_script_id INT UNSIGNED DEFAULT NULL COMMENT \'Output visualizer script ID\'');
        $this->addSql('ALTER TABLE `judgetask`
                       MODIFY COLUMN `type` ENUM(\'judging_run\', \'generic_task\', \'config_check\', \'debug_info\', \'prefetch\', \'output_visualization\') DEFAULT \'judging_run\' NOT NULL COMMENT \'Type of the judge task.(DC2Type:judge_task_type)\'');
        $this->addSql('ALTER TABLE judging ADD visualization TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'Explicitly requested to visualize the output.\'');
        $this->addSql('CREATE TABLE visualization (visualization_id INT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT \'Visualization ID\', judgingid INT UNSIGNED DEFAULT NULL COMMENT \'Judging ID\', judgehostid INT UNSIGNED DEFAULT NULL COMMENT \'Judgehost ID\', testcaseid INT UNSIGNED DEFAULT NULL COMMENT \'Testcase ID\', filename VARCHAR(255) NOT NULL COMMENT \'Name of the file where we stored the visualization.\', INDEX IDX_E0936C40E0E4FC3E (judgehostid), INDEX IDX_E0936C40D360BB2B (testcaseid), INDEX judgingid (judgingid), PRIMARY KEY(visualization_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'Team output visualization.\' ');
        $this->addSql('ALTER TABLE visualization ADD CONSTRAINT FK_E0936C405D5FEA72 FOREIGN KEY (judgingid) REFERENCES judging (judgingid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE visualization ADD CONSTRAINT FK_E0936C40E0E4FC3E FOREIGN KEY (judgehostid) REFERENCES judgehost (judgehostid) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE visualization ADD CONSTRAINT FK_E0936C40D360BB2B FOREIGN KEY (testcaseid) REFERENCES testcase (testcaseid) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE visualization DROP FOREIGN KEY FK_E0936C405D5FEA72');
        $this->addSql('ALTER TABLE visualization DROP FOREIGN KEY FK_E0936C40E0E4FC3E');
        $this->addSql('ALTER TABLE visualization DROP FOREIGN KEY FK_E0936C40D360BB2B');
        $this->addSql('DROP TABLE visualization');
        $this->addSql('ALTER TABLE judging DROP visualization');
        $this->addSql('ALTER TABLE `judgetask`
                       MODIFY COLUMN `type` ENUM(\'judging_run\', \'generic_task\', \'config_check\', \'debug_info\', \'prefetch\') DEFAULT \'judging_run\' NOT NULL COMMENT \'Type of the judge task.(DC2Type:judge_task_type)\'');
        $this->addSql('ALTER TABLE judgetask DROP output_visualizer_script_id');
        $this->addSql('ALTER TABLE problem DROP FOREIGN KEY FK_D7E7CCC819F5352E');
        $this->addSql('DROP INDEX special_output_visualizer ON problem');
        $this->addSql('ALTER TABLE problem DROP special_output_visualizer, CHANGE multipass_limit multipass_limit INT UNSIGNED DEFAULT NULL COMMENT \'Optional limit on the number of rounds for multi-pass problems; defaults to 2 if not specified.\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
