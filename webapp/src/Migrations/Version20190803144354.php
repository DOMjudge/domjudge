<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190803144354 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add external judgements and runs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
CREATE TABLE `external_judgement` (
    `extjudgementid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'External judgement ID',
    `externalid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Judgement ID in external system, should be unique inside a single contest',
    `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
    `submitid` int(4) unsigned NOT NULL COMMENT 'Submission ID being judged by external system',
    `result` varchar(32) DEFAULT NULL COMMENT 'Result string as obtained from external system. null if not finished yet',
    `starttime` decimal(32,9) unsigned NOT NULL COMMENT 'Time judging started',
    `endtime` decimal(32,9) unsigned DEFAULT NULL COMMENT 'Time judging ended, null = still busy',
    `valid` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Old external judgement is marked as invalid when receiving a new one',
    PRIMARY KEY  (`extjudgementid`),
    UNIQUE KEY `externalid` (`cid`,`externalid`(190)),
    KEY `submitid` (`submitid`),
    KEY `cid` (`cid`),
    CONSTRAINT `external_judgement_ibfk_1` FOREIGN KEY (`submitid`) REFERENCES `submission` (`submitid`) ON DELETE CASCADE,
    CONSTRAINT `external_judgement_ibfk_2` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Judgement in external system'
SQL
        );
        $this->addSql(<<<SQL
    CREATE TABLE `external_run` (
    `extrunid` int(4) UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'External run ID',
    `extjudgementid` int(4) unsigned NOT NULL COMMENT 'Judging ID this run belongs to',
    `testcaseid` int(4) unsigned NOT NULL COMMENT 'Testcase ID',
    `externalid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Run ID in external system, should be unique inside a single contest',
    `cid` int(4) unsigned NOT NULL COMMENT 'Contest ID',
    `result` varchar(32) NOT NULL COMMENT 'Result string as obtained from external system',
    `endtime` decimal(32,9) unsigned NOT NULL COMMENT 'Time run ended',
    `runtime` float NOT NULL COMMENT 'Running time on this testcase',
    PRIMARY KEY  (`extrunid`),
    UNIQUE KEY `externalid` (`cid`,`externalid`(190)),
    KEY `extjudgementid` (`extjudgementid`),
    KEY `testcaseid` (`testcaseid`),
    CONSTRAINT `external_run_ibfk_1` FOREIGN KEY (`extjudgementid`) REFERENCES `external_judgement` (`extjudgementid`) ON DELETE CASCADE,
    CONSTRAINT `external_run_ibfk_2` FOREIGN KEY (`testcaseid`) REFERENCES `testcase` (`testcaseid`) ON DELETE CASCADE,
    CONSTRAINT `external_run_ibfk_3` FOREIGN KEY (`cid`) REFERENCES `contest` (`cid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Run in external system'
SQL
        );

        // There is no easy way to move the data, as we miss a lot of information so we will not do that

        $this->addSql("ALTER TABLE `submission` DROP COLUMN `externalresult`");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE submission ADD COLUMN externalresult varchar(32) DEFAULT NULL COMMENT 'Result string as returned from external CCS, e.g. Kattis' AFTER externalid");
        // Move the external result back to the submission table from external judgements
        $this->addSql("UPDATE submission INNER JOIN external_judgement ON submission.submitid = external_judgement.submitid AND external_judgement.valid = 1 SET submission.externalresult = external_judgement.result");
        $this->addSql("DROP TABLE external_run, external_judgement");
    }
}
