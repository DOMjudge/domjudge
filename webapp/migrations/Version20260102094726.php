<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260102094726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Synchronize database after doctrine/dbal 4.x upgrade';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE configuration CHANGE value value LONGTEXT NOT NULL COMMENT \'Content of the configuration variable (JSON encoded)\'');
        $this->addSql('ALTER TABLE contest_problemset_content CHANGE content content LONGBLOB NOT NULL COMMENT \'Problemset document content\'');
        $this->addSql('ALTER TABLE event CHANGE content content LONGBLOB NOT NULL COMMENT \'JSON encoded content of the change, as provided in the event feed\'');
        $this->addSql('ALTER TABLE executable_file CHANGE file_content file_content LONGBLOB NOT NULL COMMENT \'Full file content\'');
        $this->addSql('ALTER TABLE external_run CHANGE runtime runtime DOUBLE PRECISION NOT NULL COMMENT \'Running time on this testcase\'');
        $this->addSql('ALTER TABLE external_source_warning CHANGE content content LONGTEXT NOT NULL COMMENT \'JSON encoded content of the warning. Type-specific.\'');
        $this->addSql('ALTER TABLE internal_error CHANGE disabled disabled TEXT NOT NULL COMMENT \'Disabled stuff, JSON-encoded\', CHANGE status status ENUM(\'open\', \'resolved\', \'ignored\') DEFAULT \'open\' NOT NULL COMMENT \'Status of internal error\'');
        $this->addSql('ALTER TABLE judgetask CHANGE type type ENUM(\'config_check\', \'debug_info\', \'generic_task\', \'judging_run\', \'prefetch\') DEFAULT \'judging_run\' NOT NULL COMMENT \'Type of the judge task.\'');
        $this->addSql('ALTER TABLE judging CHANGE metadata metadata LONGBLOB DEFAULT NULL COMMENT \'Compilation metadata\'');
        $this->addSql('ALTER TABLE judging_run CHANGE runtime runtime DOUBLE PRECISION DEFAULT NULL COMMENT \'Submission running time on this testcase\'');
        $this->addSql('ALTER TABLE judging_run_output CHANGE output_run output_run LONGBLOB DEFAULT NULL COMMENT \'Output of running the program\', CHANGE output_diff output_diff LONGBLOB DEFAULT NULL COMMENT \'Diffing the program output and testcase output\', CHANGE output_error output_error LONGBLOB DEFAULT NULL COMMENT \'Standard error output of the program\', CHANGE output_system output_system LONGBLOB DEFAULT NULL COMMENT \'Judging system output\', CHANGE metadata metadata LONGBLOB DEFAULT NULL COMMENT \'Judging metadata of the run\', CHANGE team_message team_message LONGBLOB DEFAULT NULL COMMENT \'Judge message for the team\', CHANGE validator_metadata validator_metadata LONGBLOB DEFAULT NULL COMMENT \'Judging metadata of the validator\'');
        $this->addSql('ALTER TABLE language CHANGE extensions extensions LONGTEXT DEFAULT NULL COMMENT \'List of recognized extensions (JSON encoded)\', CHANGE time_factor time_factor DOUBLE PRECISION DEFAULT \'1\' NOT NULL COMMENT \'Language-specific factor multiplied by problem run times\', CHANGE compiler_version compiler_version LONGBLOB DEFAULT NULL COMMENT \'Compiler version\', CHANGE runner_version runner_version LONGBLOB DEFAULT NULL COMMENT \'Runner version\'');
        $this->addSql('ALTER TABLE problem CHANGE timelimit timelimit DOUBLE PRECISION UNSIGNED DEFAULT \'0\' NOT NULL COMMENT \'Maximum run time (in seconds) for this problem\'');
        $this->addSql('ALTER TABLE problem_attachment_content CHANGE content content LONGBLOB NOT NULL COMMENT \'Attachment content\'');
        $this->addSql('ALTER TABLE problem_statement_content CHANGE content content LONGBLOB NOT NULL COMMENT \'Statement content\'');
        $this->addSql('ALTER TABLE submission CHANGE expected_results expected_results VARCHAR(255) DEFAULT NULL COMMENT \'JSON encoded list of expected results - used to validate jury submissions\'');
        $this->addSql('ALTER TABLE submission_file CHANGE sourcecode sourcecode LONGBLOB NOT NULL COMMENT \'Full source code\'');
        $this->addSql('ALTER TABLE team_category CHANGE types types INT NOT NULL COMMENT \'Bitmask of category types, default is scoring.\'');
        $this->addSql('ALTER TABLE testcase_content CHANGE input input LONGBLOB DEFAULT NULL COMMENT \'Input data\', CHANGE output output LONGBLOB DEFAULT NULL COMMENT \'Output data\', CHANGE image image LONGBLOB DEFAULT NULL COMMENT \'A graphical representation of the testcase\', CHANGE image_thumb image_thumb LONGBLOB DEFAULT NULL COMMENT \'Automatically created thumbnail of the image\'');
        $this->addSql('ALTER TABLE version CHANGE compiler_version compiler_version LONGBLOB DEFAULT NULL COMMENT \'Compiler version\', CHANGE runner_version runner_version LONGBLOB DEFAULT NULL COMMENT \'Runner version\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE configuration CHANGE value value LONGTEXT NOT NULL COMMENT \'Content of the configuration variable (JSON encoded)(DC2Type:json)\'');
        $this->addSql('ALTER TABLE contest_problemset_content CHANGE content content LONGBLOB NOT NULL COMMENT \'Problemset document content(DC2Type:blobtext)\'');
        $this->addSql('ALTER TABLE event CHANGE content content LONGBLOB NOT NULL COMMENT \'JSON encoded content of the change, as provided in the event feed(DC2Type:binaryjson)\'');
        $this->addSql('ALTER TABLE executable_file CHANGE file_content file_content LONGBLOB NOT NULL COMMENT \'Full file content(DC2Type:blobtext)\'');
        $this->addSql('ALTER TABLE external_run CHANGE runtime runtime FLOAT NOT NULL COMMENT \'Running time on this testcase\'');
        $this->addSql('ALTER TABLE external_source_warning CHANGE content content LONGTEXT NOT NULL COMMENT \'JSON encoded content of the warning. Type-specific.(DC2Type:json)\'');
        $this->addSql('ALTER TABLE internal_error CHANGE disabled disabled TEXT NOT NULL COMMENT \'Disabled stuff, JSON-encoded(DC2Type:json)\', CHANGE status status ENUM(\'open\', \'resolved\', \'ignored\') DEFAULT \'open\' NOT NULL COMMENT \'Status of internal error(DC2Type:internal_error_status)\'');
        $this->addSql('ALTER TABLE judgetask CHANGE type type ENUM(\'judging_run\', \'generic_task\', \'config_check\', \'debug_info\', \'prefetch\') DEFAULT \'judging_run\' NOT NULL COMMENT \'Type of the judge task.(DC2Type:judge_task_type)\'');
        $this->addSql('ALTER TABLE judging CHANGE metadata metadata LONGBLOB DEFAULT NULL COMMENT \'Compilation metadata(DC2Type:blobtext)\'');
        $this->addSql('ALTER TABLE judging_run CHANGE runtime runtime FLOAT DEFAULT NULL COMMENT \'Submission running time on this testcase\'');
        $this->addSql('ALTER TABLE judging_run_output CHANGE output_run output_run LONGBLOB DEFAULT NULL COMMENT \'Output of running the program(DC2Type:blobtext)\', CHANGE output_diff output_diff LONGBLOB DEFAULT NULL COMMENT \'Diffing the program output and testcase output(DC2Type:blobtext)\', CHANGE output_error output_error LONGBLOB DEFAULT NULL COMMENT \'Standard error output of the program(DC2Type:blobtext)\', CHANGE output_system output_system LONGBLOB DEFAULT NULL COMMENT \'Judging system output(DC2Type:blobtext)\', CHANGE team_message team_message LONGBLOB DEFAULT NULL COMMENT \'Judge message for the team(DC2Type:blobtext)\', CHANGE metadata metadata LONGBLOB DEFAULT NULL COMMENT \'Judging metadata of the run(DC2Type:blobtext)\', CHANGE validator_metadata validator_metadata LONGBLOB DEFAULT NULL COMMENT \'Judging metadata of the validator(DC2Type:blobtext)\'');
        $this->addSql('ALTER TABLE language CHANGE extensions extensions LONGTEXT DEFAULT NULL COMMENT \'List of recognized extensions (JSON encoded)(DC2Type:json)\', CHANGE time_factor time_factor FLOAT DEFAULT \'1\' NOT NULL COMMENT \'Language-specific factor multiplied by problem run times\', CHANGE compiler_version compiler_version LONGBLOB DEFAULT NULL COMMENT \'Compiler version(DC2Type:blobtext)\', CHANGE runner_version runner_version LONGBLOB DEFAULT NULL COMMENT \'Runner version(DC2Type:blobtext)\'');
        $this->addSql('ALTER TABLE problem CHANGE timelimit timelimit FLOAT UNSIGNED DEFAULT \'0\' NOT NULL COMMENT \'Maximum run time (in seconds) for this problem\'');
        $this->addSql('ALTER TABLE problem_attachment_content CHANGE content content LONGBLOB NOT NULL COMMENT \'Attachment content(DC2Type:blobtext)\'');
        $this->addSql('ALTER TABLE problem_statement_content CHANGE content content LONGBLOB NOT NULL COMMENT \'Statement content(DC2Type:blobtext)\'');
        $this->addSql('ALTER TABLE submission CHANGE expected_results expected_results VARCHAR(255) DEFAULT NULL COMMENT \'JSON encoded list of expected results - used to validate jury submissions(DC2Type:json)\'');
        $this->addSql('ALTER TABLE submission_file CHANGE sourcecode sourcecode LONGBLOB NOT NULL COMMENT \'Full source code(DC2Type:blobtext)\'');
        $this->addSql('ALTER TABLE team_category CHANGE types types INT DEFAULT 1 NOT NULL COMMENT \'Bitmask of category types, default is scoring.\'');
        $this->addSql('ALTER TABLE testcase_content CHANGE input input LONGBLOB DEFAULT NULL COMMENT \'Input data(DC2Type:blobtext)\', CHANGE output output LONGBLOB DEFAULT NULL COMMENT \'Output data(DC2Type:blobtext)\', CHANGE image image LONGBLOB DEFAULT NULL COMMENT \'A graphical representation of the testcase(DC2Type:blobtext)\', CHANGE image_thumb image_thumb LONGBLOB DEFAULT NULL COMMENT \'Automatically created thumbnail of the image(DC2Type:blobtext)\'');
        $this->addSql('ALTER TABLE version CHANGE compiler_version compiler_version LONGBLOB DEFAULT NULL COMMENT \'Compiler version(DC2Type:blobtext)\', CHANGE runner_version runner_version LONGBLOB DEFAULT NULL COMMENT \'Runner version(DC2Type:blobtext)\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
