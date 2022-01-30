<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190906120011 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Remove unnecessary unsigned=true from booleans.';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql(<<<SQL
ALTER TABLE `judging`
    MODIFY `verified` TINYINT(1) DEFAULT '0' NOT NULL COMMENT 'Result verified by jury member?',
    MODIFY `valid` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Old judging is marked as invalid when rejudging',
    MODIFY `seen` TINYINT(1) DEFAULT '0' NOT NULL COMMENT 'Whether the team has seen this judging'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `submission`
    MODIFY `valid` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'If false ignore this submission in all scoreboard calculations'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `contest`
    MODIFY `enabled` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Whether this contest can be active',
    MODIFY `starttime_enabled` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'If disabled, starttime is not used, e.g. to delay contest start',
    MODIFY `process_balloons` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Will balloons be processed for this contest?',
    MODIFY `public` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Is this contest visible for the public?',
    MODIFY `open_to_all_teams` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Is this contest open to all teams?'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `team`
    MODIFY `enabled` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Whether the team is visible and operational'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `problem`
    MODIFY `combined_run_compare` TINYINT(1) DEFAULT '0' NOT NULL COMMENT 'Use the exit code of the run script to compute the verdict'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `language`
    MODIFY `allow_submit` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Are submissions accepted in this language?',
    MODIFY `allow_judge` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Are submissions in this language judged?',
    MODIFY `filter_compiler_files` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Whether to filter the files passed to the compiler by the extension list.'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `clarification`
    MODIFY `answered` TINYINT(1) DEFAULT '0' NOT NULL COMMENT 'Has been answered by jury?'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `scorecache`
    MODIFY `is_correct_restricted` TINYINT(1) DEFAULT '0' NOT NULL COMMENT 'Has there been a correct submission? (restricted audience)',
    MODIFY `is_correct_public` TINYINT(1) DEFAULT '0' NOT NULL COMMENT 'Has there been a correct submission? (public)',
    MODIFY `is_first_to_solve` TINYINT(1) DEFAULT '0' NOT NULL COMMENT 'Is this the first solution to this problem?'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `team_category`
    MODIFY `visible` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Are teams in this category visible?'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `judgehost`
    MODIFY `active` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Should this host take on judgings?'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `rejudging`
    MODIFY `valid` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Rejudging is marked as invalid if canceled'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `testcase`
    MODIFY `sample` TINYINT(1) DEFAULT '0' NOT NULL COMMENT 'Sample testcases that can be shared with teams'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `user`
    MODIFY `enabled` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Whether the user is able to log in'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `external_judgement`
    MODIFY `valid` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Old external judgement is marked as invalid when receiving a new one'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `balloon`
    MODIFY `done` TINYINT(1) DEFAULT '0' NOT NULL COMMENT 'Has been handed out yet?'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `contestproblem`
    MODIFY `allow_submit` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Are submissions accepted for this problem?',
    MODIFY `allow_judge` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Are submissions for this problem judged?'
SQL
        );
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql(<<<SQL
ALTER TABLE `balloon`
    MODIFY `done` TINYINT(1) DEFAULT '0' NOT NULL COMMENT 'Has been handed out yet?'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `clarification`
    MODIFY `answered` TINYINT(1) DEFAULT '0' NOT NULL COMMENT 'Has been answered by jury?'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `contest`
    MODIFY `starttime_enabled` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'If disabled, starttime is not used, e.g. to delay contest start',
    MODIFY `enabled` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Whether this contest can be active',
    MODIFY `process_balloons` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Will balloons be processed for this contest?',
    MODIFY `public` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Is this contest visible for the public?',
    MODIFY `open_to_all_teams` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Is this contest open to all teams?'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `contestproblem`
    MODIFY `allow_submit` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Are submissions accepted for this problem?',
    MODIFY `allow_judge` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Are submissions for this problem judged?'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `external_judgement`
    MODIFY `valid` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Old external judgement is marked as invalid when receiving a new one'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `judgehost`
    MODIFY `active` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Should this host take on judgings?'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `judging`
    MODIFY `verified` TINYINT(1) DEFAULT '0' NOT NULL COMMENT 'Result verified by jury member?',
    MODIFY `valid` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Old judging is marked as invalid when rejudging',
    MODIFY `seen` TINYINT(1) DEFAULT '0' NOT NULL COMMENT 'Whether the team has seen this judging'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `language`
    MODIFY `filter_compiler_files` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Whether to filter the files passed to the compiler by the extension list',
    MODIFY `allow_submit` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Are submissions accepted in this language?',
    MODIFY `allow_judge` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Are submissions in this language judged?'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `problem`
    MODIFY `combined_run_compare` TINYINT(1) DEFAULT '0' NOT NULL COMMENT 'Use the exit code of the run script to compute the verdict'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `rejudging`
    MODIFY `valid` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Rejudging is marked as invalid if canceled'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `scorecache`
    MODIFY `is_correct_restricted` TINYINT(1) DEFAULT '0' NOT NULL COMMENT 'Has there been a correct submission? (restricted audience)',
    MODIFY `is_correct_public` TINYINT(1) DEFAULT '0' NOT NULL COMMENT 'Has there been a correct submission? (public)',
    MODIFY `is_first_to_solve` TINYINT(1) DEFAULT '0' NOT NULL COMMENT 'Is this the first solution to this problem?'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `submission`
    MODIFY `valid` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'If false ignore this submission in all scoreboard calculations'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `team`
    MODIFY `enabled` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Whether the team is visible and operational'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `team_category`
    MODIFY `visible` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Are teams in this category visible?'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `testcase`
    MODIFY `sample` TINYINT(1) DEFAULT '0' NOT NULL COMMENT 'Sample testcases that can be shared with teams'
SQL
        );
        $this->addSql(<<<SQL
ALTER TABLE `user`
    MODIFY `enabled` TINYINT(1) DEFAULT '1' NOT NULL COMMENT 'Whether the user is able to log in'
SQL
        );
    }
}
