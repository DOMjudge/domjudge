<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260505093157 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store output visualizer executable & visualization.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE judgetask ADD visualizer_script_id INT UNSIGNED DEFAULT NULL COMMENT \'Output visualizer script ID\', ADD visualizer_config LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin` COMMENT \'The output visualizer config as JSON-blob.\'');
        $this->addSql('ALTER TABLE judging_run_output ADD visualization_judge LONGBLOB DEFAULT NULL COMMENT \'The output visualization for the team output\', ADD visualization_judge_mime VARCHAR(255) DEFAULT NULL COMMENT \'Mime type of output visualization for judges\', ADD visualization_team LONGBLOB DEFAULT NULL COMMENT \'The output visualization for the team output visible for the team\', ADD visualization_team_mime VARCHAR(255) DEFAULT NULL COMMENT \'Mime type of output visualization for team\'');
        $this->addSql('ALTER TABLE problem ADD special_visualizer_args VARCHAR(255) DEFAULT NULL COMMENT \'Optional arguments to special_visualizer script\', ADD visualizer VARCHAR(32) DEFAULT NULL COMMENT \'Executable ID (string)\'');
        $this->addSql('ALTER TABLE problem ADD CONSTRAINT FK_D7E7CCC852315FE7 FOREIGN KEY (visualizer) REFERENCES executable (execid) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_D7E7CCC852315FE7 ON problem (visualizer)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_D7E7CCC852315FE7 ON problem');
        $this->addSql('ALTER TABLE problem DROP FOREIGN KEY FK_D7E7CCC852315FE7');
        $this->addSql('ALTER TABLE problem DROP special_visualizer_args, DROP visualizer');
        $this->addSql('ALTER TABLE judging_run_output DROP visualization_judge, DROP visualization_judge_mime, DROP visualization_team, DROP visualization_team_mime');
        $this->addSql('ALTER TABLE judgetask DROP visualizer_script_id, DROP visualizer_config');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
