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
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE judging CHANGE verified verified TINYINT(1) DEFAULT \'0\' NOT NULL COMMENT \'Result verified by jury member?\', CHANGE valid valid TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Old judging is marked as invalid when rejudging\', CHANGE seen seen TINYINT(1) DEFAULT \'0\' NOT NULL COMMENT \'Whether the team has seen this judging\'');
        $this->addSql('ALTER TABLE submission CHANGE valid valid TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'If false ignore this submission in all scoreboard calculations\'');
        $this->addSql('ALTER TABLE contest CHANGE enabled enabled TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Whether this contest can be active\', CHANGE starttime_enabled starttime_enabled TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'If disabled, starttime is not used, e.g. to delay contest start\', CHANGE process_balloons process_balloons TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Will balloons be processed for this contest?\', CHANGE public public TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Is this contest visible for the public?\', CHANGE open_to_all_teams open_to_all_teams TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Is this contest open to all teams?\'');
        $this->addSql('ALTER TABLE team CHANGE enabled enabled TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Whether the team is visible and operational\'');
        $this->addSql('ALTER TABLE problem CHANGE combined_run_compare combined_run_compare TINYINT(1) DEFAULT \'0\' NOT NULL COMMENT \'Use the exit code of the run script to compute the verdict\'');
        $this->addSql('ALTER TABLE language CHANGE allow_submit allow_submit TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Are submissions accepted in this language?\', CHANGE allow_judge allow_judge TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Are submissions in this language judged?\', CHANGE filter_compiler_files filter_compiler_files TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Whether to filter the files passed to the compiler by the extension list.\'');
        $this->addSql('ALTER TABLE clarification CHANGE answered answered TINYINT(1) DEFAULT \'0\' NOT NULL COMMENT \'Has been answered by jury?\'');
        $this->addSql('ALTER TABLE scorecache CHANGE is_correct_restricted is_correct_restricted TINYINT(1) DEFAULT \'0\' NOT NULL COMMENT \'Has there been a correct submission? (restricted audience)\', CHANGE is_correct_public is_correct_public TINYINT(1) DEFAULT \'0\' NOT NULL COMMENT \'Has there been a correct submission? (public)\', CHANGE is_first_to_solve is_first_to_solve TINYINT(1) DEFAULT \'0\' NOT NULL COMMENT \'Is this the first solution to this problem?\'');
        $this->addSql('ALTER TABLE team_category CHANGE visible visible TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Are teams in this category visible?\'');
        $this->addSql('ALTER TABLE judgehost CHANGE active active TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Should this host take on judgings?\'');
        $this->addSql('ALTER TABLE rejudging CHANGE valid valid TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Rejudging is marked as invalid if canceled\'');
        $this->addSql('ALTER TABLE testcase CHANGE sample sample TINYINT(1) DEFAULT \'0\' NOT NULL COMMENT \'Sample testcases that can be shared with teams\'');
        $this->addSql('ALTER TABLE user CHANGE enabled enabled TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Whether the user is able to log in\'');
        $this->addSql('ALTER TABLE external_judgement CHANGE valid valid TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Old external judgement is marked as invalid when receiving a new one\'');
        $this->addSql('ALTER TABLE balloon CHANGE done done TINYINT(1) DEFAULT \'0\' NOT NULL COMMENT \'Has been handed out yet?\'');
        $this->addSql('ALTER TABLE contestproblem CHANGE allow_submit allow_submit TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Are submissions accepted for this problem?\', CHANGE allow_judge allow_judge TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Are submissions for this problem judged?\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE balloon CHANGE done done TINYINT(1) DEFAULT \'0\' NOT NULL COMMENT \'Has been handed out yet?\'');
        $this->addSql('ALTER TABLE clarification CHANGE answered answered TINYINT(1) DEFAULT \'0\' NOT NULL COMMENT \'Has been answered by jury?\'');
        $this->addSql('ALTER TABLE contest CHANGE starttime_enabled starttime_enabled TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'If disabled, starttime is not used, e.g. to delay contest start\', CHANGE enabled enabled TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Whether this contest can be active\', CHANGE process_balloons process_balloons TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Will balloons be processed for this contest?\', CHANGE public public TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Is this contest visible for the public?\', CHANGE open_to_all_teams open_to_all_teams TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Is this contest open to all teams?\'');
        $this->addSql('ALTER TABLE contestproblem CHANGE allow_submit allow_submit TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Are submissions accepted for this problem?\', CHANGE allow_judge allow_judge TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Are submissions for this problem judged?\'');
        $this->addSql('ALTER TABLE external_judgement CHANGE valid valid TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Old external judgement is marked as invalid when receiving a new one\'');
        $this->addSql('ALTER TABLE judgehost CHANGE active active TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Should this host take on judgings?\'');
        $this->addSql('ALTER TABLE judging CHANGE verified verified TINYINT(1) DEFAULT \'0\' NOT NULL COMMENT \'Result verified by jury member?\', CHANGE valid valid TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Old judging is marked as invalid when rejudging\', CHANGE seen seen TINYINT(1) DEFAULT \'0\' NOT NULL COMMENT \'Whether the team has seen this judging\'');
        $this->addSql('ALTER TABLE language CHANGE filter_compiler_files filter_compiler_files TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Whether to filter the files passed to the compiler by the extension list\', CHANGE allow_submit allow_submit TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Are submissions accepted in this language?\', CHANGE allow_judge allow_judge TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Are submissions in this language judged?\'');
        $this->addSql('ALTER TABLE problem CHANGE combined_run_compare combined_run_compare TINYINT(1) DEFAULT \'0\' NOT NULL COMMENT \'Use the exit code of the run script to compute the verdict\'');
        $this->addSql('ALTER TABLE rejudging CHANGE valid valid TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Rejudging is marked as invalid if canceled\'');
        $this->addSql('ALTER TABLE scorecache CHANGE is_correct_restricted is_correct_restricted TINYINT(1) DEFAULT \'0\' NOT NULL COMMENT \'Has there been a correct submission? (restricted audience)\', CHANGE is_correct_public is_correct_public TINYINT(1) DEFAULT \'0\' NOT NULL COMMENT \'Has there been a correct submission? (public)\', CHANGE is_first_to_solve is_first_to_solve TINYINT(1) DEFAULT \'0\' NOT NULL COMMENT \'Is this the first solution to this problem?\'');
        $this->addSql('ALTER TABLE submission CHANGE valid valid TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'If false ignore this submission in all scoreboard calculations\'');
        $this->addSql('ALTER TABLE team CHANGE enabled enabled TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Whether the team is visible and operational\'');
        $this->addSql('ALTER TABLE team_category CHANGE visible visible TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Are teams in this category visible?\'');
        $this->addSql('ALTER TABLE testcase CHANGE sample sample TINYINT(1) DEFAULT \'0\' NOT NULL COMMENT \'Sample testcases that can be shared with teams\'');
        $this->addSql('ALTER TABLE user CHANGE enabled enabled TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Whether the user is able to log in\'');
    }
}
