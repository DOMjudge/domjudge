<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201110113446 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription() : string
    {
        return 'Remove explicit column definitions for foreign keys.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE language CHANGE compile_script compile_script VARCHAR(32) DEFAULT NULL COMMENT \'Executable ID (string)\'');
        $this->addSql('ALTER TABLE problem CHANGE special_run special_run VARCHAR(32) DEFAULT NULL COMMENT \'Executable ID (string)\', CHANGE special_compare special_compare VARCHAR(32) DEFAULT NULL COMMENT \'Executable ID (string)\'');
        $this->addSql('ALTER TABLE team CHANGE categoryid categoryid INT UNSIGNED DEFAULT NULL COMMENT \'Team category ID\'');
        $this->addSql('ALTER TABLE submission CHANGE origsubmitid origsubmitid INT UNSIGNED DEFAULT NULL COMMENT \'Submission ID\', CHANGE cid cid INT UNSIGNED DEFAULT NULL COMMENT \'Contest ID\', CHANGE teamid teamid INT UNSIGNED DEFAULT NULL COMMENT \'Team ID\', CHANGE probid probid INT UNSIGNED DEFAULT NULL COMMENT \'Problem ID\', CHANGE langid langid VARCHAR(32) DEFAULT NULL COMMENT \'Language ID (string)\', CHANGE judgehost judgehost VARCHAR(64) DEFAULT NULL COMMENT \'Resolvable hostname of judgehost\', CHANGE rejudgingid rejudgingid INT UNSIGNED DEFAULT NULL COMMENT \'Rejudging ID\'');
        $this->addSql('ALTER TABLE clarification CHANGE cid cid INT UNSIGNED DEFAULT NULL COMMENT \'Contest ID\', CHANGE respid respid INT UNSIGNED DEFAULT NULL COMMENT \'Clarification ID\', CHANGE sender sender INT UNSIGNED DEFAULT NULL COMMENT \'Team ID\', CHANGE recipient recipient INT UNSIGNED DEFAULT NULL COMMENT \'Team ID\', CHANGE probid probid INT UNSIGNED DEFAULT NULL COMMENT \'Problem ID\'');
        $this->addSql('ALTER TABLE submission_file CHANGE submitid submitid INT UNSIGNED DEFAULT NULL COMMENT \'Submission ID\'');
        $this->addSql('ALTER TABLE judging CHANGE cid cid INT UNSIGNED DEFAULT NULL COMMENT \'Contest ID\', CHANGE submitid submitid INT UNSIGNED DEFAULT NULL COMMENT \'Submission ID\', CHANGE judgehost judgehost VARCHAR(64) DEFAULT NULL COMMENT \'Resolvable hostname of judgehost\', CHANGE rejudgingid rejudgingid INT UNSIGNED DEFAULT NULL COMMENT \'Rejudging ID\', CHANGE prevjudgingid prevjudgingid INT UNSIGNED DEFAULT NULL COMMENT \'Judging ID\'');
        $this->addSql('ALTER TABLE external_run CHANGE extjudgementid extjudgementid INT UNSIGNED DEFAULT NULL COMMENT \'External judgement ID\', CHANGE testcaseid testcaseid INT UNSIGNED DEFAULT NULL COMMENT \'Testcase ID\', CHANGE cid cid INT UNSIGNED DEFAULT NULL COMMENT \'Contest ID\'');
        $this->addSql('ALTER TABLE event CHANGE cid cid INT UNSIGNED DEFAULT NULL COMMENT \'Contest ID\'');
        $this->addSql('ALTER TABLE balloon CHANGE submitid submitid INT UNSIGNED DEFAULT NULL COMMENT \'Submission ID\'');
        $this->addSql('ALTER TABLE judging_run CHANGE judgingid judgingid INT UNSIGNED DEFAULT NULL COMMENT \'Judging ID\', CHANGE testcaseid testcaseid INT UNSIGNED DEFAULT NULL COMMENT \'Testcase ID\'');
        $this->addSql('ALTER TABLE user CHANGE teamid teamid INT UNSIGNED DEFAULT NULL COMMENT \'Team ID\'');
        $this->addSql('ALTER TABLE removed_interval CHANGE cid cid INT UNSIGNED DEFAULT NULL COMMENT \'Contest ID\'');
        $this->addSql('ALTER TABLE external_judgement CHANGE cid cid INT UNSIGNED DEFAULT NULL COMMENT \'Contest ID\', CHANGE submitid submitid INT UNSIGNED DEFAULT NULL COMMENT \'Submission ID\'');
        $this->addSql('ALTER TABLE judgehost CHANGE restrictionid restrictionid INT UNSIGNED DEFAULT NULL COMMENT \'Judgehost restriction ID\'');
        $this->addSql('ALTER TABLE rejudging CHANGE userid_start userid_start INT UNSIGNED DEFAULT NULL COMMENT \'User ID\', CHANGE userid_finish userid_finish INT UNSIGNED DEFAULT NULL COMMENT \'User ID\', CHANGE repeat_rejudgingid repeat_rejudgingid INT UNSIGNED DEFAULT NULL COMMENT \'Rejudging ID\'');
        $this->addSql('ALTER TABLE testcase CHANGE probid probid INT UNSIGNED DEFAULT NULL COMMENT \'Problem ID\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE balloon CHANGE submitid submitid INT UNSIGNED NOT NULL COMMENT \'Submission for which balloon was earned\'');
        $this->addSql('ALTER TABLE clarification CHANGE probid probid INT UNSIGNED DEFAULT NULL COMMENT \'Problem associated to this clarification\', CHANGE cid cid INT UNSIGNED NOT NULL COMMENT \'Contest ID\', CHANGE respid respid INT UNSIGNED DEFAULT NULL COMMENT \'In reply to clarification ID\', CHANGE sender sender INT UNSIGNED DEFAULT NULL COMMENT \'Team ID, null means jury\', CHANGE recipient recipient INT UNSIGNED DEFAULT NULL COMMENT \'Team ID, null means to jury or to all\'');
        $this->addSql('ALTER TABLE event CHANGE cid cid INT UNSIGNED NOT NULL COMMENT \'Contest ID\'');
        $this->addSql('ALTER TABLE external_judgement CHANGE cid cid INT UNSIGNED NOT NULL COMMENT \'Contest ID\', CHANGE submitid submitid INT UNSIGNED NOT NULL COMMENT \'Submission ID being judged by external system\'');
        $this->addSql('ALTER TABLE external_run CHANGE extjudgementid extjudgementid INT UNSIGNED NOT NULL COMMENT \'Judging ID this run belongs to\', CHANGE testcaseid testcaseid INT UNSIGNED NOT NULL COMMENT \'Testcase ID\', CHANGE cid cid INT UNSIGNED NOT NULL COMMENT \'Contest ID\'');
        $this->addSql('ALTER TABLE judgehost CHANGE restrictionid restrictionid INT UNSIGNED DEFAULT NULL COMMENT \'Optional set of restrictions for this judgehost\'');
        $this->addSql('ALTER TABLE judging CHANGE cid cid INT UNSIGNED DEFAULT 0 NOT NULL COMMENT \'Contest ID\', CHANGE submitid submitid INT UNSIGNED NOT NULL COMMENT \'Submission ID being judged\', CHANGE judgehost judgehost VARCHAR(64) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'Judgehost that performed the judging\', CHANGE rejudgingid rejudgingid INT UNSIGNED DEFAULT NULL COMMENT \'Rejudging ID (if rejudge)\', CHANGE prevjudgingid prevjudgingid INT UNSIGNED DEFAULT NULL COMMENT \'Previous valid judging (if rejudge)\'');
        $this->addSql('ALTER TABLE judging_run CHANGE judgingid judgingid INT UNSIGNED NOT NULL COMMENT \'Judging ID\', CHANGE testcaseid testcaseid INT UNSIGNED NOT NULL COMMENT \'Testcase ID\'');
        $this->addSql('ALTER TABLE language CHANGE compile_script compile_script VARCHAR(32) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'Script to compile source code for this language\'');
        $this->addSql('ALTER TABLE problem CHANGE special_compare special_compare VARCHAR(32) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'Script to compare problem and jury output for this problem\', CHANGE special_run special_run VARCHAR(32) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'Script to run submissions for this problem\'');
        $this->addSql('ALTER TABLE rejudging CHANGE userid_start userid_start INT UNSIGNED DEFAULT NULL COMMENT \'User ID of user who started the rejudge\', CHANGE userid_finish userid_finish INT UNSIGNED DEFAULT NULL COMMENT \'User ID of user who accepted or canceled the rejudge\', CHANGE repeat_rejudgingid repeat_rejudgingid INT UNSIGNED DEFAULT NULL COMMENT \'In case repeat is set, this will mark the first rejudgingid.\'');
        $this->addSql('ALTER TABLE removed_interval CHANGE cid cid INT UNSIGNED NOT NULL COMMENT \'Contest ID\'');
        $this->addSql('ALTER TABLE submission CHANGE judgehost judgehost VARCHAR(64) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'Current/last judgehost judging this submission\', CHANGE cid cid INT UNSIGNED NOT NULL COMMENT \'Contest ID\', CHANGE langid langid VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'Language ID\', CHANGE teamid teamid INT UNSIGNED NOT NULL COMMENT \'Team ID\', CHANGE probid probid INT UNSIGNED NOT NULL COMMENT \'Problem ID\', CHANGE rejudgingid rejudgingid INT UNSIGNED DEFAULT NULL COMMENT \'Rejudging ID (if rejudge)\', CHANGE origsubmitid origsubmitid INT UNSIGNED DEFAULT NULL COMMENT \'If set, specifies original submission in case of edit/resubmit\'');
        $this->addSql('ALTER TABLE submission_file CHANGE submitid submitid INT UNSIGNED NOT NULL COMMENT \'Submission this file belongs to\'');
        $this->addSql('ALTER TABLE team CHANGE categoryid categoryid INT UNSIGNED DEFAULT 0 NOT NULL COMMENT \'Team category ID\'');
        $this->addSql('ALTER TABLE testcase CHANGE probid probid INT UNSIGNED DEFAULT NULL COMMENT \'Corresponding problem ID\'');
        $this->addSql('ALTER TABLE user CHANGE teamid teamid INT UNSIGNED DEFAULT NULL COMMENT \'Team associated with this user\'');
    }
}
